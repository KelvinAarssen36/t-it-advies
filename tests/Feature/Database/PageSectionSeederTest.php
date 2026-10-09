<?php

namespace Tests\Feature\Database;

use App\Enums\PageSectionKey;
use App\Models\PageSection;
use Database\Seeders\PageSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De seeder die de onderdelen van de landingspagina klaarzet.
 *
 * **Dit bestand is er door een echte fout**, en die is het onthouden
 * waard omdat hij alleen zichtbaar is in een database die al in gebruik
 * is.
 *
 * `PageSectionKey::standaardPositie()` geldt voor de eerste keer zaaien.
 * Daarna is de volgorde van de klant, en het indelingsscherm nummert bij
 * elke opslag opnieuw door als 1, 2, 3. Zette de seeder een nieuw
 * onderdeel dan op zijn standaardplek, dan botste die met een plek die
 * al bezet was -- en twee onderdelen op dezelfde plek vallen terug op
 * hun `id`. Gevolg: een nieuwe module belandde op een willekeurige plek
 * in zijn eigen pagina. In de echte database stond de nieuwe module
 * daardoor ná het contactformulier.
 *
 * Op een verse database viel er niets te zien, en geen enkele test keek
 * naar een database die al gevuld was.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
class PageSectionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_database_gets_the_order_from_the_code(): void
    {
        $this->seed(PageSectionSeeder::class);

        foreach (PageSectionKey::cases() as $sectie) {
            $this->assertSame(
                $sectie->standaardPositie(),
                (int) PageSection::query()->where('key', $sectie)->value('position'),
                "Het onderdeel {$sectie->value} staat niet op zijn standaardplek.",
            );
        }
    }

    public function test_seeding_twice_changes_nothing(): void
    {
        $this->seed(PageSectionSeeder::class);

        $vooraf = PageSection::query()
            ->orderBy('key')
            ->pluck('position', 'key')
            ->all();

        $this->seed(PageSectionSeeder::class);

        $this->assertSame(
            $vooraf,
            PageSection::query()->orderBy('key')->pluck('position', 'key')->all(),
        );
    }

    /**
     * De volgorde van de klant blijft van de klant.
     *
     * Hij heeft zijn pagina omgegooid; een deploy hoort daar niet
     * dwars door te gaan.
     */
    public function test_an_order_the_owner_set_is_left_alone(): void
    {
        $this->seed(PageSectionSeeder::class);

        /*
         * Zoals het indelingsscherm het opslaat: doorgenummerd vanaf 1.
         *
         * **Álle versleepbare onderdelen staan hier**, en dat is geen
         * volledigheid voor de sier. Dat scherm slaat de hele rij op, dus
         * een lijst met er één te weinig is geen volgorde die de klant ooit
         * kan hebben gezet -- en dan botst het ontbrekende onderdeel met
         * een plek die al bezet is, waarna `herstelDubbelePlekken()`
         * terecht alles opnieuw nummert. Deze test valt dus om zodra er een
         * module bijkomt, en dat is precies de bedoeling.
         */
        $eigen = [
            PageSectionKey::Contact,
            PageSectionKey::Diensten,
            PageSectionKey::Projecten,
            PageSectionKey::Ervaring,
            PageSectionKey::Werkwijze,
            PageSectionKey::Certificaten,
            PageSectionKey::Statistieken,
            PageSectionKey::Faq,
            PageSectionKey::OverMij,
            PageSectionKey::Kerngegevens,
            PageSectionKey::Linkedin,
        ];

        /*
         * En de controle dat die lijst echt compleet is.
         *
         * Zonder deze regel valt de test hierboven om met een melding over
         * een verschoven positie, en dan ga je zoeken in de seeder terwijl
         * het probleem is dat er een onderdeel in deze lijst mist. Nu zegt
         * hij wat er aan de hand is.
         */
        $this->assertCount(
            count(PageSectionKey::verplaatsbaar()),
            $eigen,
            'Er is een versleepbaar onderdeel bijgekomen; zet het ook in deze lijst.',
        );

        foreach ($eigen as $index => $sectie) {
            PageSection::query()
                ->where('key', $sectie)
                ->update(['position' => $index + 1]);
        }

        $this->seed(PageSectionSeeder::class);

        foreach ($eigen as $index => $sectie) {
            $this->assertSame(
                $index + 1,
                (int) PageSection::query()->where('key', $sectie)->value('position'),
                "De eigen plek van {$sectie->value} is veranderd.",
            );
        }
    }

    /**
     * **Dit is de fout zelf.**
     *
     * De klant heeft zijn pagina opgeslagen, dus alles staat
     * doorgenummerd vanaf 1. Daarna komt er een module bij. Die hoort
     * achteraan te komen en niet op een plek die al bezet is.
     */
    public function test_a_new_section_lands_at_the_end_and_not_on_an_occupied_spot(): void
    {
        $this->seed(PageSectionSeeder::class);

        $verplaatsbaar = PageSectionKey::verplaatsbaar();

        // De toestand na één keer opslaan in het indelingsscherm.
        foreach ($verplaatsbaar as $index => $sectie) {
            PageSection::query()
                ->where('key', $sectie)
                ->update(['position' => $index + 1]);
        }

        // En nu "verschijnt" er een nieuw onderdeel: we halen er een weg
        // en laten de seeder hem opnieuw aanmaken.
        $nieuw = PageSectionKey::Statistieken;
        PageSection::query()->where('key', $nieuw)->delete();

        $this->seed(PageSectionSeeder::class);

        $plekken = PageSection::query()
            ->whereIn(
                'key',
                array_map(fn (PageSectionKey $sectie) => $sectie->value, $verplaatsbaar),
            )
            ->pluck('position');

        // Geen twee onderdelen op dezelfde plek.
        $this->assertSame(
            $plekken->count(),
            $plekken->unique()->count(),
            'Er staan twee onderdelen op dezelfde plek.',
        );

        // En de nieuwe staat achteraan, net vóór de voettekst.
        $this->assertSame(
            $plekken->max(),
            (int) PageSection::query()->where('key', $nieuw)->value('position'),
        );
    }

    /**
     * Een database die de fout al heeft, wordt gerepareerd.
     *
     * Opnieuw doornummeren, met de volgorde die er al was. De klant
     * merkt er niets van behalve dat het onderdeel dat ernaast stond
     * weer op zijn eigen plek staat.
     */
    public function test_duplicate_positions_are_renumbered_without_reshuffling(): void
    {
        $this->seed(PageSectionSeeder::class);

        PageSection::query()
            ->where('key', PageSectionKey::Diensten)
            ->update(['position' => 2]);
        PageSection::query()
            ->where('key', PageSectionKey::Werkwijze)
            ->update(['position' => 2]);

        // De volgorde zoals hij nu feitelijk uitpakt, vóór de reparatie.
        $vooraf = PageSection::query()
            ->whereIn(
                'key',
                array_map(
                    fn (PageSectionKey $sectie) => $sectie->value,
                    PageSectionKey::verplaatsbaar(),
                ),
            )
            ->opVolgorde()
            ->pluck('key')
            ->map(fn (PageSectionKey $sleutel) => $sleutel->value)
            ->all();

        $this->seed(PageSectionSeeder::class);

        $erna = PageSection::query()
            ->whereIn(
                'key',
                array_map(
                    fn (PageSectionKey $sectie) => $sectie->value,
                    PageSectionKey::verplaatsbaar(),
                ),
            )
            ->opVolgorde()
            ->pluck('key')
            ->map(fn (PageSectionKey $sleutel) => $sleutel->value)
            ->all();

        // Dezelfde volgorde, maar nu zonder dubbele plekken.
        $this->assertSame($vooraf, $erna);
        $this->assertSame(
            range(1, count($erna)),
            PageSection::query()
                ->whereIn(
                    'key',
                    array_map(
                        fn (PageSectionKey $sectie) => $sectie->value,
                        PageSectionKey::verplaatsbaar(),
                    ),
                )
                ->opVolgorde()
                ->pluck('position')
                ->map(fn (int $plek) => $plek)
                ->all(),
        );
    }

    public function test_a_row_for_a_section_that_no_longer_exists_is_removed(): void
    {
        PageSection::query()->insert([
            'key' => 'verzonnen',
            'position' => 9,
            'visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seed(PageSectionSeeder::class);

        $this->assertSame(
            0,
            PageSection::query()->where('key', 'verzonnen')->count(),
        );
    }
}
