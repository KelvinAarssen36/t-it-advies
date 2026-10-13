<?php

namespace Tests\Feature;

use App\Enums\ContactVeld;
use App\Enums\ContactVeldStatus;
use App\Models\ContactField;
use Database\Seeders\ContactSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Spatie\Honeypot\EncryptedTime;
use Tests\TestCase;

/**
 * De meldingen die Laravel zelf meelevert, in de taal van de lezer.
 *
 * **Hier zat een scheur waar je zo langs kijkt.** De taal staat op `nl`,
 * maar Laravel levert alleen een `en`-map mee, dus viel elke validatie- en
 * inlogmelding terug op het Engels. Een bezoeker die een verplicht veld
 * leeg liet kreeg:
 *
 *     "The telefoonnummer field is required."
 *
 * Een Engelse zin met een Nederlandse veldnaam erin -- want die veldnaam
 * kwam wél uit onze eigen `attributes()`. Het mengsel maakte het juist
 * moeilijk te zien: er stond genoeg Nederlands in om te denken dat het goed
 * was ingericht.
 *
 * De oplossing is `lang/nl/`, dat de andere kant op vertaalt dan
 * `lang/en.json`. Die afspraak staat in docs/architecture/vertalingen.md,
 * en deze tests leggen hem vast.
 */
class ValidatietaalTest extends TestCase
{
    use RefreshDatabase;

    /* --- Het publieke formulier ------------------------------------------- */

    /**
     * Een bezoeker die een verplicht veld leeg laat, leest Nederlands.
     *
     * Via het echte formulier en niet via een losse validator: dit is de
     * plek waar het fout stond en waar het zichtbaar is.
     */
    public function test_a_visitor_reads_a_dutch_message(): void
    {
        $this->seed(ContactSeeder::class);
        Mail::fake();

        ContactField::query()
            ->where('key', ContactVeld::Telefoon)
            ->update(['status' => ContactVeldStatus::Verplicht]);

        $this->post(route('contact.store'), [
            'name' => 'Kees Jansen',
            'email' => 'kees@example.com',
            'subject_text' => 'Vraag over een migratie',
            'message' => 'Graag advies over onze overstap naar een nieuwe omgeving.',
            config('honeypot.name_field_name') => '',
            config('honeypot.valid_from_field_name') => EncryptedTime::create(now()->subMinute()),
        ])->assertSessionHasErrors([
            'phone' => 'Vul telefoonnummer in.',
        ]);
    }

    /** Een ongeldig e-mailadres ook. */
    public function test_an_invalid_email_address_gets_a_dutch_message(): void
    {
        $this->seed(ContactSeeder::class);
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Kees Jansen',
            'email' => 'geen-adres',
            'subject_text' => 'Vraag over een migratie',
            'message' => 'Graag advies over onze overstap naar een nieuwe omgeving.',
            config('honeypot.name_field_name') => '',
            config('honeypot.valid_from_field_name') => EncryptedTime::create(now()->subMinute()),
        ])->assertSessionHasErrors([
            'email' => 'Vul een geldig e-mailadres in.',
        ]);
    }

    /* --- De regels zelf --------------------------------------------------- */

    /**
     * De regels die dit project gebruikt, elk met een Nederlandse melding.
     *
     * Eén test en geen twintig: het gaat om één bestand, en de lijst in de
     * foutmelding is duidelijker dan twintig testnamen. Komt er een regel
     * bij in een FormRequest, zet hem dan hier én in `lang/nl/validation.php`.
     */
    public function test_every_rule_this_project_uses_has_a_dutch_message(): void
    {
        $gevallen = [
            'required' => ['veld' => null],
            'string' => ['veld' => ['x']],
            'integer' => ['veld' => 'tekst'],
            'numeric' => ['veld' => 'tekst'],
            'boolean' => ['veld' => 'misschien'],
            'array' => ['veld' => 'tekst'],
            'email' => ['veld' => 'geen-adres'],
            'url' => ['veld' => 'geen-link'],
            'date' => ['veld' => 'geen-datum'],
            'regex:/^\d+$/' => ['veld' => 'abc'],
            'in:ja,nee' => ['veld' => 'wat anders'],
            'max:2' => ['veld' => 'veel te lang'],
            'min:20' => ['veld' => 'kort'],
            'filled' => ['veld' => ''],
            'confirmed' => ['veld' => 'a'],
            'same:ander' => ['veld' => 'a', 'ander' => 'b'],
            'after:2030-01-01' => ['veld' => '2020-01-01'],
        ];

        $engels = [];

        foreach ($gevallen as $regel => $gegevens) {
            $validator = Validator::make($gegevens, ['veld' => [$regel]]);

            $melding = (string) $validator->errors()->first('veld');

            if ($melding === '') {
                $engels[$regel] = 'deze regel gaf geen foutmelding -- de testopzet klopt niet';

                continue;
            }

            // Laravel's eigen meldingen beginnen allemaal met "The ".
            if (str_starts_with($melding, 'The ')) {
                $engels[$regel] = $melding;
            }
        }

        $this->assertSame(
            [],
            $engels,
            'Deze validatieregels geven nog een Engelse melding. Zet ze in lang/nl/validation.php: '
                .json_encode($engels, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    /**
     * Een regel die we níet vertaald hebben valt netjes terug op het Engels.
     *
     * Dat is waarom `lang/nl/validation.php` met opzet niet compleet hoeft
     * te zijn: de terugval werkt per sleutel. Zonder deze test zou niemand
     * durven vertrouwen dat een onvolledig bestand veilig is.
     */
    public function test_an_untranslated_rule_falls_back_to_english(): void
    {
        $melding = (string) Validator::make(['veld' => 'abc'], ['veld' => ['uuid']])
            ->errors()
            ->first('veld');

        $this->assertStringStartsWith('The ', $melding);
    }

    /* --- Inloggen en wachtwoorden ----------------------------------------- */

    /**
     * De schermen die de eigenaar zelf gebruikt.
     *
     * `auth.failed` stond in het Engels op het scherm waar hij elke dag
     * binnenkomt.
     */
    public function test_the_login_and_password_messages_are_dutch(): void
    {
        foreach ([
            'auth.failed',
            'auth.password',
            'auth.throttle',
            'passwords.reset',
            'passwords.sent',
            'passwords.throttled',
            'passwords.token',
            'passwords.user',
        ] as $sleutel) {
            $melding = (string) __($sleutel);

            $this->assertNotSame(
                $sleutel,
                $melding,
                "De sleutel {$sleutel} heeft geen Nederlandse tekst.",
            );

            $this->assertStringNotContainsString(
                'password reset link',
                $melding,
                "De melding {$sleutel} is nog Engels.",
            );

            $this->assertStringNotContainsString(
                'our records',
                $melding,
                "De melding {$sleutel} is nog Engels.",
            );
        }
    }
}
