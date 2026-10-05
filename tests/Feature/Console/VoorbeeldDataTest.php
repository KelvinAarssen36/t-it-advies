<?php

namespace Tests\Feature\Console;

use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Models\MailLog;
use Database\Seeders\ContactSeeder;
use Database\Seeders\VoorbeeldDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * De verzonnen data en het opruimen daarvan.
 *
 * **Het gevaar van deze seeder is niet dat hij stuk is maar dat hij blijft
 * staan.** Zijn rijen zien er in het portaal precies zo uit als echte
 * aanvragen van echte mensen. Daarom twee dingen die vast moeten liggen: hij
 * draait nergens anders dan lokaal, en het opruimen raakt alleen wat van hem
 * is.
 *
 * Zie database/seeders/VoorbeeldDataSeeder.php.
 */
class VoorbeeldDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContactSeeder::class);
    }

    /** De opdracht waarmee je hem normaal aanroept. */
    public function test_the_command_seeds_and_reports(): void
    {
        $this->artisan('voorbeeld:zaaien')
            ->expectsOutputToContain('@voorbeeld.test')
            ->assertSuccessful();

        $this->assertSame(9, ContactSubmission::query()->count());
    }

    public function test_the_seeder_puts_every_state_on_the_screen(): void
    {
        $this->seed(VoorbeeldDataSeeder::class);

        $this->assertSame(5, ContactSubject::query()->count());
        $this->assertSame(9, ContactSubmission::query()->count());

        // Eén offline onderwerp, dus vier op de site.
        $this->assertSame(4, ContactSubject::query()->online()->count());

        // En één zonder Engelse naam, die op de Engelse site terugvalt.
        $this->assertSame(1, ContactSubject::query()->whereNull('label_en')->count());

        /*
         * De standen waar het scherm om vraagt. Een seeder die negen keer
         * hetzelfde neerzet is net zo nutteloos als een lege database.
         */
        $this->assertGreaterThan(0, ContactSubmission::query()->ongelezen()->count());
        $this->assertGreaterThan(0, ContactSubmission::query()->whereNotNull('answered_at')->count());
        $this->assertGreaterThan(0, ContactSubmission::query()->where('locale', 'en')->count());
        $this->assertGreaterThan(0, ContactSubmission::query()->whereNotNull('company')->count());
        $this->assertGreaterThan(0, ContactSubmission::query()->whereNotNull('phone')->count());
        $this->assertGreaterThan(0, ContactSubmission::query()->where('subject_custom', true)->count());

        // Een aanvraag waarvan het onderwerp is verwijderd: tekst wel,
        // verwijzing niet.
        $this->assertSame(
            1,
            ContactSubmission::query()
                ->where('subject_custom', false)
                ->whereNull('subject_id')
                ->count(),
        );

        // En een bounce plus een verzending die nooit vertrok.
        $this->assertSame(1, MailLog::query()->where('status', 'bounced')->count());
        $this->assertSame(
            1,
            MailLog::query()->where('status', 'failed')->whereNull('sent_at')->count(),
        );
    }

    /** Elke aanvraag staat op een adres dat nooit echt kan bestaan. */
    public function test_every_address_is_unmistakably_fake(): void
    {
        $this->seed(VoorbeeldDataSeeder::class);

        $echt = ContactSubmission::query()
            ->where('email', 'not like', '%@voorbeeld.test')
            ->pluck('email')
            ->all();

        $this->assertSame(
            [],
            $echt,
            'Elke verzonnen aanvraag hoort op @voorbeeld.test te staan, anders kan voorbeeld:opruimen hem niet vinden.',
        );
    }

    /**
     * En buiten local gaat hij er niet in.
     *
     * Dit is de belangrijkste test van het bestand. Draait hij ooit in
     * productie, dan staan er negen verzonnen aanvragen tussen de echte en
     * is er geen manier om te zien welke welke zijn.
     */
    public function test_the_seeder_refuses_outside_local(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('local en testing');

        /*
         * Rechtstreeks en niet via `$this->seed()`. Die gaat langs
         * `db:seed`, en dat commando vraagt in productie eerst om
         * bevestiging -- dan struikelt de test op de prompt en niet op de
         * controle die we willen bewijzen.
         */
        (new VoorbeeldDataSeeder)->run();
    }

    /** En de opdracht meldt dat netjes in plaats van om te vallen. */
    public function test_the_command_refuses_outside_local(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('voorbeeld:zaaien')->assertFailed();

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    /* --- Opruimen --------------------------------------------------------- */

    public function test_cleaning_up_removes_the_example_data(): void
    {
        $this->seed(VoorbeeldDataSeeder::class);

        $this->artisan('voorbeeld:opruimen --force')->assertSuccessful();

        $this->assertSame(0, ContactSubmission::query()->count());
        $this->assertSame(0, ContactSubject::query()->count());
        $this->assertSame(0, MailLog::query()->where('message_id', 'like', 'voorbeeld-%')->count());
    }

    /**
     * Maar niet wat van de klant is.
     *
     * **Dit is waarom het opruimen op het adres afgaat en niet op een datum
     * of een aantal.** Een echte aanvraag tussen de verzonnen rijen mag er
     * niet mee verdwijnen, en dat kan alleen als er iets aan de rij zelf te
     * zien is.
     */
    public function test_cleaning_up_leaves_real_rows_alone(): void
    {
        $this->seed(VoorbeeldDataSeeder::class);

        $echt = ContactSubmission::factory()->create([
            'email' => 'een.echte.bezoeker@example.com',
            'name' => 'Een echte bezoeker',
        ]);

        $eigenMail = MailLog::query()->create([
            'message_id' => 'een-echte-mail',
            'mailable' => 'App\\Mail\\ContactBevestigingMail',
            'mailer' => 'smtp',
            'subject' => 'Bevestiging aan een bezoeker',
            'to' => ['een.echte.bezoeker@example.com'],
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->artisan('voorbeeld:opruimen --force')->assertSuccessful();

        $this->assertModelExists($echt);
        $this->assertModelExists($eigenMail);
        $this->assertSame(1, ContactSubmission::query()->count());
    }

    public function test_cleaning_up_says_so_when_there_is_nothing_to_clean(): void
    {
        $this->artisan('voorbeeld:opruimen --force')
            ->expectsOutputToContain('geen voorbeelddata')
            ->assertSuccessful();
    }

    public function test_cleaning_up_refuses_outside_local(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('voorbeeld:opruimen --force')->assertFailed();
    }
}
