<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\PageSection;
use App\Support\Page\SectionContent;
use Database\Seeders\PageSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De landingspagina volgt wat de eigenaar in het portaal heeft ingesteld.
 *
 * Dit is de andere helft van PageLayoutTest: daar wordt de indeling
 * opgeslagen, hier wordt hij gelezen. De twee horen bij elkaar, want een
 * volgorde die wel wordt bewaard maar niet wordt getoond is net zo stuk als
 * een die niet wordt bewaard.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
class LandingSectionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function sectiesOpDeLanding(): array
    {
        $response = $this->get(route('home'));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Welcome'));

        return $response->viewData('page')['props']['sections'];
    }

    /**
     * De volgorde zetten zoals het indelingsscherm dat zou doen.
     *
     * @param  array<int, PageSectionKey>  $volgorde
     */
    private function zetVolgorde(array $volgorde): void
    {
        foreach ($volgorde as $index => $sectie) {
            PageSection::query()
                ->where('key', $sectie)
                ->update(['position' => $index + 1]);
        }
    }

    public function test_the_landing_follows_the_order_from_the_database(): void
    {
        $this->seed(PageSectionSeeder::class);

        $this->zetVolgorde([
            PageSectionKey::Contact,
            PageSectionKey::Diensten,
            PageSectionKey::Werkwijze,
        ]);

        $this->assertSame(
            ['contact', 'diensten', 'werkwijze'],
            $this->sectiesOpDeLanding(),
        );
    }

    public function test_a_section_the_owner_switched_off_is_gone(): void
    {
        $this->seed(PageSectionSeeder::class);

        PageSection::query()
            ->where('key', PageSectionKey::Werkwijze)
            ->update(['visible' => false]);

        $this->assertSame(
            ['diensten', 'contact'],
            $this->sectiesOpDeLanding(),
        );
    }

    public function test_a_section_without_content_is_gone_even_though_it_is_on(): void
    {
        /*
         * Een kopje met niets eronder is slordiger dan geen kopje. Op het
         * indelingsscherm ziet de eigenaar wél dat dit onderdeel aanstaat
         * en waarom het toch niet op zijn site staat; zie PageLayoutTest.
         */
        $this->seed(PageSectionSeeder::class);

        app(SectionContent::class)->telt(PageSectionKey::Diensten, fn () => 0);

        $this->assertSame(
            ['werkwijze', 'contact'],
            $this->sectiesOpDeLanding(),
        );
    }

    public function test_a_section_with_content_stays(): void
    {
        // De andere kant op: zonder deze test zou de vorige ook slagen als
        // de teller altijd "leeg" zou zeggen.
        $this->seed(PageSectionSeeder::class);

        app(SectionContent::class)->telt(PageSectionKey::Diensten, fn () => 3);

        $this->assertContains('diensten', $this->sectiesOpDeLanding());
    }

    public function test_an_unseeded_database_falls_back_to_the_order_from_the_code(): void
    {
        /*
         * Zonder deze terugval levert een vergeten `db:seed` een publieke
         * website op die alleen nog uit een kop en een voettekst bestaat --
         * en dat merk je niet in het portaal maar bij de bezoeker.
         */
        $this->assertSame(0, PageSection::query()->count());

        $this->assertSame(
            ['diensten', 'werkwijze', 'contact'],
            $this->sectiesOpDeLanding(),
        );
    }

    public function test_the_fixed_sections_are_never_in_the_list(): void
    {
        /*
         * De kop staat los boven de lijst en de voettekst zit in de layout.
         * Zouden ze hier wél in staan, dan verschijnen ze twee keer op de
         * pagina.
         */
        $this->seed(PageSectionSeeder::class);

        $secties = $this->sectiesOpDeLanding();

        $this->assertNotContains(PageSectionKey::Hero->value, $secties);
        $this->assertNotContains(PageSectionKey::Footer->value, $secties);
    }
}
