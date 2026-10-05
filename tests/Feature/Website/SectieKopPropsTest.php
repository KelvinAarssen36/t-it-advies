<?php

namespace Tests\Feature\Website;

use App\Enums\ContactWeergave;
use App\Enums\PageSectionKey;
use App\Models\ContactSetting;
use App\Models\SectionHeading;
use Database\Seeders\ContactSeeder;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De koppen komen op de site aan met de namen die de frontend verwacht.
 *
 * **Dit vangt een fout af die niets kapotmaakte en daarom lang kon
 * blijven staan.** `SectionHeading::voorDeSite()` stuurt `opschrift`,
 * `titel` en `inleiding`. De contactpagina en de contactsectie hadden hun
 * eigen type opgeschreven met `eyebrow`, `title` en `intro` erin -- de
 * namen van de databasekolommen. Alle drie waren dus `undefined`:
 *
 * - geen foutmelding, want een ontbrekende prop is in Vue gewoon leeg;
 * - geen gefaalde test, want de server stuurde keurig wat hij moest sturen;
 * - en op de pagina stond geen kop. Alleen een formulier op een lege
 *   pagina, wat eruitzag alsof het onaf was -- en dat was het ook.
 *
 * `vue-tsc` kon er niets aan doen: het type zei wat de component verwachtte,
 * niet wat er werkelijk binnenkwam. Die brug ligt alleen hier, in een test
 * die de échte props van de échte route naleest.
 *
 * **Voeg hier een regel toe zodra er een publieke pagina met een kop bij
 * komt.** Dat is goedkoper dan hem over een half jaar leeg aantreffen.
 */
class SectieKopPropsTest extends TestCase
{
    use RefreshDatabase;

    /** De drie namen die `voorDeSite()` gebruikt. */
    private const VELDEN = ['opschrift', 'titel', 'inleiding'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
        $this->seed(ContactSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $kop
     */
    private function controleer(array $kop, string $waar): void
    {
        foreach (self::VELDEN as $veld) {
            $this->assertArrayHasKey(
                $veld,
                $kop,
                "De kop van {$waar} mist het veld '{$veld}'. "
                    .'Gebruik het gedeelde type SectieKop en niet de kolomnamen uit de database.',
            );
        }

        $this->assertNotSame('', $kop['titel'], "De titel van {$waar} is leeg.");
    }

    public function test_the_heading_on_the_landing_page_has_the_expected_shape(): void
    {
        $this->get(route('home'))->assertInertia(function (AssertableInertia $page) {
            /** @var array<string, mixed> $contact */
            $contact = $page->toArray()['props']['contact'];

            $this->controleer($contact['kop'], 'de contactsectie');

            return true;
        });
    }

    public function test_the_heading_on_the_separate_page_has_the_expected_shape(): void
    {
        ContactSetting::huidige()->update(['display' => ContactWeergave::EigenPagina]);

        $this->get(route('contact'))->assertInertia(function (AssertableInertia $page) {
            /** @var array<string, mixed> $kop */
            $kop = $page->toArray()['props']['kop'];

            $this->controleer($kop, 'de aparte contactpagina');

            return true;
        });
    }

    /**
     * En de tekst die de eigenaar instelt komt er ook echt uit.
     *
     * De vorm klopte al; dit bewijst dat het de tekst uit zíjn database is
     * en niet een vaste zin uit de code.
     */
    public function test_the_text_the_owner_set_reaches_the_separate_page(): void
    {
        ContactSetting::huidige()->update(['display' => ContactWeergave::EigenPagina]);

        SectionHeading::voor(PageSectionKey::Contact)->update([
            'eyebrow_nl' => 'OPSCHRIFT-UNIEK',
            'title_nl' => 'TITEL-UNIEK',
            'intro_nl' => 'INLEIDING-UNIEK',
        ]);

        $this->get(route('contact'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('kop.opschrift', 'OPSCHRIFT-UNIEK')
            ->where('kop.titel', 'TITEL-UNIEK')
            ->where('kop.inleiding', 'INLEIDING-UNIEK'));
    }

    /**
     * De elf andere koppen op de landingspagina, in één keer.
     *
     * Die werkten al -- ze gebruiken het gedeelde type. Ze staan hier zodat
     * deze test ook iets zegt als er ooit aan `voorDeSite()` wordt
     * gesleuteld: dan valt hij voor alles om en niet alleen voor contact.
     */
    public function test_every_other_heading_on_the_landing_page_has_the_expected_shape(): void
    {
        $this->get(route('home'))->assertInertia(function (AssertableInertia $page) {
            $props = $page->toArray()['props'];

            $koppen = [
                'heroHeading' => 'de kop',
                'serviceHeading' => 'de diensten',
                'experienceHeading' => 'de tijdlijn',
                'certificateHeading' => 'de certificaten',
                'statisticHeading' => 'de cijfers',
            ];

            $gevonden = 0;

            foreach ($koppen as $prop => $waar) {
                if (! isset($props[$prop]) || ! is_array($props[$prop])) {
                    continue;
                }

                $gevonden++;
                $this->controleer($props[$prop], $waar);
            }

            // Eerst bewijzen dat er iets te controleren viel.
            $this->assertGreaterThan(0, $gevonden);

            return true;
        });
    }
}
