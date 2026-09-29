<?php

namespace Tests\Feature\Database;

use App\Enums\PageSectionKey;
use App\Models\ActivityEntry;
use App\Models\Experience;
use Database\Seeders\ExperienceSeeder;
use Database\Seeders\PageSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De loopbaan van de eigenaar, die met elke deploy meegaat.
 *
 * Dit is **productiedata**: de echte werkervaring, geen voorbeeld. De twee
 * dingen die daarbij mis kunnen gaan zijn precies wat hier wordt bewaakt:
 * een deploy die alles dubbel zet, en een deploy die overschrijft wat de
 * klant zelf heeft aangepast. Het eerste maakt de tijdlijn onleesbaar, het
 * tweede gooit zijn werk weg -- en allebei merk je pas op de website.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
class ExperienceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_career_is_seeded(): void
    {
        $this->seed(ExperienceSeeder::class);

        $this->assertSame(26, Experience::query()->count());

        // De lopende functie: geen einddatum, dus "tot heden", en die
        // hoort bovenaan de tijdlijn te staan.
        $eerste = Experience::query()->opTijdlijn()->firstOrFail();

        $this->assertSame('Eni', $eerste->organisation);
        $this->assertTrue($eerste->loopt());
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        /*
         * Elke deploy draait de seeders opnieuw. Zou hij op iets anders dan
         * een vaste combinatie zoeken, dan staat de hele loopbaan er na de
         * tweede deploy twee keer in.
         */
        $this->seed(ExperienceSeeder::class);
        $this->seed(ExperienceSeeder::class);

        $this->assertSame(26, Experience::query()->count());
    }

    public function test_it_never_overwrites_what_the_owner_changed(): void
    {
        $this->seed(ExperienceSeeder::class);

        $ervaring = Experience::query()
            ->where('organisation', 'Eni')
            ->firstOrFail();

        $ervaring->update(['description_nl' => 'Zelf herschreven.']);

        $this->seed(ExperienceSeeder::class);

        $this->assertSame('Zelf herschreven.', $ervaring->fresh()?->description_nl);
    }

    public function test_seeding_stays_out_of_the_activity_log(): void
    {
        // Zesentwintig regels "Systeem heeft een ervaring aangemaakt" zou
        // het logboek bij de eerste deploy onleesbaar maken.
        $this->seed(ExperienceSeeder::class);

        $this->assertSame(
            0,
            ActivityEntry::query()->where('subject_type', Experience::class)->count(),
        );
    }

    public function test_every_entry_has_an_english_title(): void
    {
        /*
         * Zonder Engelse titel valt de site terug op het Nederlands en zet
         * het portaal er "Nog niet vertaald" bij. Bij zesentwintig regels
         * zou dat betekenen dat de eigenaar een waarschuwing ziet die niets
         * meer betekent.
         */
        $this->seed(ExperienceSeeder::class);

        $this->assertSame(0, Experience::query()->whereNull('role_en')->count());

        // En ze zijn met de hand geschreven, niet door de vertaaldienst.
        $this->assertSame(
            0,
            Experience::query()->whereNotNull('machine_translated_at')->count(),
        );
    }

    public function test_the_timeline_appears_on_the_website_after_seeding(): void
    {
        // De hele keten in één test: zaaien vult de tabel, de teller ziet
        // dat er inhoud is, en daarmee staat de sectie op de landing.
        $this->seed(PageSectionSeeder::class);
        $this->seed(ExperienceSeeder::class);

        $props = $this->get(route('home'))->viewData('page')['props'];

        $this->assertContains(PageSectionKey::Ervaring->value, $props['sections']);
        $this->assertCount(26, $props['experiences']);
    }
}
