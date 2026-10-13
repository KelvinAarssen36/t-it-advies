<?php

namespace Tests\Feature;

use App\Models\ContactSubmission;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De bewaartermijn van de contactaanvragen.
 *
 * **Dit is de enige bewaartermijn in dit project die over inhoud van een
 * bezoeker gaat** en niet over een logboek. Hij staat in de
 * privacyverklaring, dus hij is een belofte: deze tests houden vast dat de
 * applicatie hem nakomt.
 *
 * Zie docs/architecture/modules/contact.md en
 * docs/operations/onderhoudstaken.md.
 */
class ContactRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_old_request_is_pruned(): void
    {
        config(['site.contact.retention_days' => 365]);

        $oud = ContactSubmission::factory()->create([
            'created_at' => now()->subDays(366),
        ]);

        $this->artisan('contact:prune')->assertSuccessful();

        $this->assertModelMissing($oud);
    }

    public function test_a_recent_request_stays(): void
    {
        config(['site.contact.retention_days' => 365]);

        $recent = ContactSubmission::factory()->create([
            'created_at' => now()->subDays(364),
        ]);

        $this->artisan('contact:prune')->assertSuccessful();

        $this->assertModelExists($recent);
    }

    /**
     * De termijn komt uit de instelling en niet uit het commando.
     *
     * **Dat is wat de privacyverklaring waar houdt.** Die tekst leest
     * dezelfde waarde; zou het commando er zijn eigen getal op nahouden,
     * dan kunnen de twee uit elkaar gaan lopen zonder dat iemand het merkt.
     */
    public function test_the_command_follows_the_setting(): void
    {
        config(['site.contact.retention_days' => 30]);

        $vanVeertigDagen = ContactSubmission::factory()->create([
            'created_at' => now()->subDays(40),
        ]);

        $vanTwintigDagen = ContactSubmission::factory()->create([
            'created_at' => now()->subDays(20),
        ]);

        $this->artisan('contact:prune')->assertSuccessful();

        $this->assertModelMissing($vanVeertigDagen);
        $this->assertModelExists($vanTwintigDagen);
    }

    /** Een termijn van nul dagen is geen termijn maar een fout. */
    public function test_a_retention_of_zero_days_is_refused(): void
    {
        $aanvraag = ContactSubmission::factory()->create([
            'created_at' => now()->subYears(5),
        ]);

        $this->artisan('contact:prune --days=0')->assertFailed();

        $this->assertModelExists($aanvraag);
    }

    /**
     * De privacyverklaring noemt dezelfde termijn.
     *
     * Die tekst leest de waarde uit de instelling, dus hij kan niet
     * verouderen -- maar dat de prop er is en de juiste waarde heeft, hoort
     * wel vast te liggen.
     */
    public function test_the_privacy_statement_names_the_same_period(): void
    {
        config(['site.contact.retention_days' => 123]);

        $this->get(route('privacy'))
            ->assertInertia(fn ($page) => $page
                ->where('bewaartermijnAanvragen', 123));
    }

    /** En de opruimtaak staat ook echt in de planning. */
    public function test_the_prune_task_is_scheduled(): void
    {
        $taken = collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? '')
            ->filter(fn (string $command) => str_contains($command, 'contact:prune'));

        $this->assertCount(
            1,
            $taken,
            'De opruimtaak voor de contactaanvragen staat niet in routes/console.php.',
        );
    }
}
