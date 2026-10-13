<?php

namespace Tests\Feature\Website;

use App\Enums\ContactVeld;
use App\Enums\ContactVeldStatus;
use App\Models\ContactField;
use App\Models\ContactSubmission;
use App\Models\User;
use App\Support\Contact\Contactformulier;
use Database\Seeders\ContactSeeder;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Honeypot\EncryptedTime;
use Tests\TestCase;

/**
 * De instelbare velden van het contactformulier.
 *
 * **Hier zit de hele beveiliging van deze module in.** De eigenaar bepaalt
 * welke velden er staan, en dat mag nooit betekenen dat een bezoeker iets
 * kan meesturen wat niet gevraagd is, of dat een vast veld weg te krijgen
 * is.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class ContactFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(ContactSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /** De stand van één veld omzetten. */
    private function zet(ContactVeld $veld, ContactVeldStatus $stand): void
    {
        $velden = ContactField::query()
            ->get()
            ->map(fn (ContactField $rij) => [
                'key' => $rij->key->value,
                'status' => $rij->key === $veld
                    ? $stand->value
                    : $rij->status->value,
            ])
            ->all();

        $this->actingAs($this->beheerder())
            ->put(route('website.contact.velden'), ['velden' => $velden]);
    }

    /**
     * @return array<string, mixed>
     */
    private function inzending(array $anders = []): array
    {
        return [
            'name' => 'Kees Jansen',
            'email' => 'kees@example.com',
            'subject_text' => 'Vraag over een migratie',
            'message' => 'Graag advies over onze overstap naar een nieuwe omgeving.',
            config('honeypot.name_field_name') => '',
            config('honeypot.valid_from_field_name') => EncryptedTime::create(now()->subMinute()),
            ...$anders,
        ];
    }

    /* --- De standaardstanden --------------------------------------------- */

    /**
     * De seeder zet het formulier neer zoals het al was.
     *
     * **Dat is met opzet**: deze module verandert niets aan wat er op de
     * site staat tot de eigenaar er zelf aan draait. Bedrijfsnaam en
     * telefoonnummer zijn nieuw en staan uit; een formulier dat opeens meer
     * vraagt is een formulier dat minder wordt ingevuld.
     */
    public function test_the_form_starts_as_it_was(): void
    {
        $formulier = app(Contactformulier::class);

        $namen = array_column($formulier->voorDeSite(), 'naam');

        $this->assertSame(['name', 'email', 'subject', 'message'], $namen);
    }

    /* --- Een veld aanzetten ---------------------------------------------- */

    public function test_switching_a_field_on_puts_it_on_the_form(): void
    {
        $this->zet(ContactVeld::Telefoon, ContactVeldStatus::Optioneel);

        $formulier = app(Contactformulier::class);

        $this->assertContains(
            'phone',
            array_column($formulier->voorDeSite(), 'naam'),
        );
    }

    public function test_an_optional_field_may_be_left_empty(): void
    {
        Mail::fake();

        $this->zet(ContactVeld::Telefoon, ContactVeldStatus::Optioneel);

        $this->post(route('contact.store'), $this->inzending())
            ->assertSessionHasNoErrors();

        $this->assertNull(ContactSubmission::query()->sole()->phone);
    }

    public function test_a_required_field_must_be_filled(): void
    {
        Mail::fake();

        $this->zet(ContactVeld::Telefoon, ContactVeldStatus::Verplicht);

        $this->post(route('contact.store'), $this->inzending())
            ->assertSessionHasErrors('phone');

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    public function test_a_filled_field_is_saved(): void
    {
        Mail::fake();

        $this->zet(ContactVeld::Telefoon, ContactVeldStatus::Optioneel);

        $this->post(route('contact.store'), $this->inzending([
            'phone' => '06 12 34 56 78',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('06 12 34 56 78', ContactSubmission::query()->sole()->phone);
    }

    /* --- Een uitgezet veld ----------------------------------------------- */

    /**
     * Een uitgezet veld meesturen levert niets op.
     *
     * **Geen foutmelding maar stilte**, en dat is de bedoeling: de waarde
     * wordt door de validator weggehaald met `exclude`. Een foutmelding zou
     * een bezoeker die net te laat op versturen drukte straffen voor een
     * wijziging van de eigenaar.
     */
    public function test_a_field_that_is_off_is_ignored(): void
    {
        Mail::fake();

        // Telefoonnummer staat standaard uit; zie ContactVeld.
        $this->post(route('contact.store'), $this->inzending([
            'phone' => '06 12 34 56 78',
            'company' => 'Stiekem BV',
        ]))->assertSessionHasNoErrors();

        $aanvraag = ContactSubmission::query()->sole();

        $this->assertNull($aanvraag->phone);
        $this->assertNull($aanvraag->company);
    }

    /** En hij staat ook niet in de momentopname van de velden. */
    public function test_the_snapshot_only_holds_the_fields_that_were_on(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending());

        $this->assertSame(
            ['name', 'email', 'subject', 'message'],
            ContactSubmission::query()->sole()->shown,
        );
    }

    /* --- De vaste velden ------------------------------------------------- */

    /**
     * Een vast veld is niet uit te zetten, ook niet via een eigen verzoek.
     *
     * **Dit is de test die ertoe doet.** Het schuifje staat in het scherm
     * vergrendeld, maar dat is opmaak; als de server het wél zou aannemen,
     * is die vergrendeling versiering. Zonder naam, adres en bericht kun je
     * niemand antwoorden.
     */
    public function test_a_fixed_field_cannot_be_switched_off(): void
    {
        foreach ([ContactVeld::Naam, ContactVeld::Email, ContactVeld::Bericht] as $veld) {
            $this->zet($veld, ContactVeldStatus::Uit);

            $rij = ContactField::query()->where('key', $veld)->sole();

            $this->assertSame(
                ContactVeldStatus::Verplicht,
                $rij->stand(),
                "Het veld [{$veld->value}] is uit te zetten, en dat mag niet.",
            );
        }
    }

    /** En hij blijft verplicht op het formulier. */
    public function test_a_fixed_field_stays_required(): void
    {
        Mail::fake();

        $this->zet(ContactVeld::Bericht, ContactVeldStatus::Optioneel);

        $this->post(route('contact.store'), $this->inzending(['message' => '']))
            ->assertSessionHasErrors('message');

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    /**
     * Zelfs een rij die met de hand is omgezet wordt genegeerd.
     *
     * De waarheid staat in `ContactVeld::vast()` en dus in de code, niet in
     * de database. Zie ContactField::stand().
     */
    public function test_a_row_changed_by_hand_is_ignored(): void
    {
        ContactField::query()
            ->where('key', ContactVeld::Naam)
            ->update(['status' => ContactVeldStatus::Uit->value]);

        $formulier = app(Contactformulier::class);

        $this->assertSame(
            ContactVeldStatus::Verplicht,
            $formulier->stand(ContactVeld::Naam),
        );

        $this->assertContains(
            'name',
            array_column($formulier->voorDeSite(), 'naam'),
        );
    }

    /* --- Validatie van de instelling ------------------------------------- */

    public function test_an_unknown_status_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.contact.velden'), [
                'velden' => [
                    ['key' => ContactVeld::Telefoon->value, 'status' => 'misschien'],
                ],
            ])
            ->assertSessionHasErrors('velden.0.status');
    }

    public function test_an_unknown_field_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.contact.velden'), [
                'velden' => [['key' => 'bsn', 'status' => 'verplicht']],
            ])
            ->assertSessionHasErrors('velden.0.key');
    }

    /* --- De lengtegrenzen ------------------------------------------------ */

    public function test_text_that_is_too_long_is_refused(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending([
            'name' => str_repeat('a', 121),
        ]))->assertSessionHasErrors('name');

        $this->post(route('contact.store'), $this->inzending([
            'message' => str_repeat('a', 5001),
        ]))->assertSessionHasErrors('message');

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    /** Een telefoonnummer vol letters wordt geweigerd. */
    public function test_a_phone_number_full_of_letters_is_refused(): void
    {
        Mail::fake();

        $this->zet(ContactVeld::Telefoon, ContactVeldStatus::Verplicht);

        $this->post(route('contact.store'), $this->inzending([
            'phone' => 'bel me maar',
        ]))->assertSessionHasErrors('phone');
    }
}
