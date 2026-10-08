<?php

namespace Tests\Feature\Mail;

use App\Concerns\VolgtDeMailstijl;
use App\Enums\MailStijl;
use App\Mail\ContactBevestigingMail;
use App\Mail\ContactMessageMail;
use App\Mail\CrashAlertMail;
use App\Mail\InlogadresAangevraagdMail;
use App\Mail\InlogadresBevestigenMail;
use App\Mail\InlogadresGewijzigdMail;
use App\Mail\SecurityAlertMail;
use App\Models\EmailChange;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Security\Anomaly;
use Database\Seeders\ContactSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De stijl waarin de website zijn mail verstuurt.
 *
 * Twee standen: licht of de huisstijl. De eigenaar zet het om op
 * Instellingen → Weergave, en élke mail volgt die keuze -- de bevestiging
 * aan een bezoeker net zo goed als de meldingen aan hemzelf.
 *
 * **De stijl staat op de mailable en niet in de config.** Daardoor kan een
 * mail die in de wachtrij wordt opgebouwd hem nog uitlezen, en kan geen
 * aanroeper hem vergeten: de trait `VolgtDeMailstijl` zet hem in de
 * constructor. `config('mail.markdown.theme')` blijft de terugval voor een
 * mail die die trait niet gebruikt.
 *
 * Zie App\Enums\MailStijl en docs/architecture/mail-en-queues.md.
 */
class MailstijlTest extends TestCase
{
    use RefreshDatabase;

    private function beheerder(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * De stijl zetten, ook als er nog geen rij is.
     *
     * `huidige()` geeft bij een lege tabel een niet-opgeslagen model terug,
     * en daar kun je geen `fresh()` op doen. Dat is geen rare toestand maar
     * de normale begintoestand, dus hij hoort hier opgevangen te worden.
     */
    private function zetStijl(MailStijl $stijl): void
    {
        SiteSetting::huidige()->fill(['mail_style' => $stijl])->save();
    }

    private function bevestiging(): ContactBevestigingMail
    {
        return new ContactBevestigingMail(
            naam: 'Jan de Vries',
            onderwerp: 'We hebben je bericht ontvangen',
            tekst: 'Bedankt voor je bericht.',
            samenvatting: 'Vrijblijvend gesprek',
        );
    }

    /* --- De instelling ----------------------------------------------------- */

    /** Een verse database begint licht: die komt overal goed aan. */
    public function test_light_is_the_default(): void
    {
        $this->assertSame(MailStijl::Licht, SiteSetting::mailstijl());
    }

    public function test_the_owner_can_switch_to_the_house_style(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('appearance.mail-stijl'), ['stijl' => 'huisstijl'])
            ->assertSessionHasNoErrors();

        $this->assertSame(MailStijl::Huisstijl, SiteSetting::mailstijl());
    }

    public function test_and_back_again(): void
    {
        $beheerder = $this->beheerder();

        $this->actingAs($beheerder)->put(route('appearance.mail-stijl'), ['stijl' => 'huisstijl']);
        $this->actingAs($beheerder)->put(route('appearance.mail-stijl'), ['stijl' => 'licht']);

        $this->assertSame(MailStijl::Licht, SiteSetting::mailstijl());
    }

    public function test_an_unknown_style_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('appearance.mail-stijl'), ['stijl' => 'neon'])
            ->assertSessionHasErrors('stijl');

        $this->assertSame(MailStijl::Licht, SiteSetting::mailstijl());
    }

    /** En niet door iemand zonder recht. */
    public function test_it_needs_the_portal_permission(): void
    {
        $this->put(route('appearance.mail-stijl'), ['stijl' => 'huisstijl'])
            ->assertRedirect();

        $this->assertSame(MailStijl::Licht, SiteSetting::mailstijl());
    }

    /* --- Wat de mails ermee doen ------------------------------------------ */

    /**
     * Elke mail volgt de gekozen stijl.
     *
     * Eén test voor allemaal, want de vraag is niet of één mailable het
     * goed doet maar of er geen enkele buiten valt.
     *
     * De lijst hieronder is met de hand bijgehouden, want elke mailable
     * heeft zijn eigen constructor. Dat de lijst compleet blíjft bewaakt
     * `test_no_mailable_forgets_the_trait`; zonder die tweede test zou een
     * nieuwe mail er gewoon buiten vallen en zou deze test daar niets van
     * merken.
     */
    public function test_every_mail_follows_the_chosen_style(): void
    {
        $mails = fn () => [
            ContactBevestigingMail::class => $this->bevestiging(),
            ContactMessageMail::class => new ContactMessageMail(
                senderName: 'Jan de Vries',
                senderEmail: 'jan@voorbeeld.test',
                senderSubject: 'Een vraag',
                body: 'Hallo daar.',
            ),
            CrashAlertMail::class => new CrashAlertMail(
                soort: 'RuntimeException',
                melding: 'iets ging mis',
                plek: 'app/Foo.php:1',
                adres: '/',
                gebruiker: null,
            ),
            SecurityAlertMail::class => new SecurityAlertMail(
                anomalies: [new Anomaly(
                    key: 'failed-logins',
                    title: 'Mislukte inlogpogingen',
                    count: 31,
                    threshold: 25,
                )],
                since: now()->subHour(),
            ),
            InlogadresBevestigenMail::class => new InlogadresBevestigenMail(
                naam: 'Jan de Vries',
                oudAdres: 'oud@voorbeeld.test',
                nieuwAdres: 'nieuw@voorbeeld.test',
                link: 'https://voorbeeld.test/inlogadres/bevestigen/abc',
                geldigeMinuten: EmailChange::BEVESTIGEN_GELDIG,
            ),
            InlogadresAangevraagdMail::class => new InlogadresAangevraagdMail(
                naam: 'Jan de Vries',
                nieuwAdres: 'nieuw@voorbeeld.test',
                afbreekLink: 'https://voorbeeld.test/inlogadres/terugdraaien/abc',
            ),
            InlogadresGewijzigdMail::class => new InlogadresGewijzigdMail(
                naam: 'Jan de Vries',
                oudAdres: 'oud@voorbeeld.test',
                nieuwAdres: 'nieuw@voorbeeld.test',
                geldigeDagen: EmailChange::HERSTELLEN_GELDIG,
            ),
        ];

        foreach (MailStijl::cases() as $stijl) {
            SiteSetting::huidige()->fill(['mail_style' => $stijl])->save();

            foreach ($mails() as $klasse => $mail) {
                $this->assertSame(
                    $stijl->thema(),
                    $mail->theme,
                    "{$klasse} volgt de gekozen mailstijl niet. Gebruikt hij de trait VolgtDeMailstijl?",
                );
            }
        }
    }

    /**
     * Geen enkele mailable vergeet de trait.
     *
     * Dit is de bewaker van de test hierboven. Die werkt met een lijst die
     * je met de hand bijhoudt -- onvermijdelijk, want elke mailable heeft
     * zijn eigen constructor -- en een lijst die je bijhoudt vergeet je.
     *
     * Deze kijkt in plaats daarvan naar de map zelf. Komt er een mail bij
     * zonder `VolgtDeMailstijl`, dan valt hij terug op het standaardthema
     * van Laravel: hij komt gewoon aan, alleen in een andere stijl dan de
     * rest. Dat zie je pas als de klant het je vertelt.
     */
    public function test_no_mailable_forgets_the_trait(): void
    {
        $zonder = [];

        foreach (File::files(app_path('Mail')) as $bestand) {
            $klasse = 'App\\Mail\\'.$bestand->getFilenameWithoutExtension();

            if (! class_exists($klasse) || ! is_subclass_of($klasse, Mailable::class)) {
                continue;
            }

            // `class_uses_recursive` en niet `class_uses`: de trait mag ook
            // via een tussenliggende basisklasse binnenkomen.
            if (! in_array(VolgtDeMailstijl::class, class_uses_recursive($klasse), true)) {
                $zonder[] = $klasse;
            }
        }

        $this->assertSame(
            [],
            $zonder,
            'Deze mails gebruiken de trait VolgtDeMailstijl niet, en volgen '
                .'dus niet de stijl die de eigenaar heeft gekozen: '
                .implode(', ', $zonder),
        );
    }

    /**
     * En het verschil is in de mail zelf te zien.
     *
     * De eigenschap op de mailable is het middel; dit is het doel. Een test
     * op alleen die eigenschap zou een thema goedkeuren dat niet bestaat.
     */
    public function test_the_rendered_mail_really_differs(): void
    {
        $this->seed(ContactSeeder::class);

        SiteSetting::huidige()->fill(['mail_style' => MailStijl::Licht])->save();
        $licht = $this->bevestiging()->render();

        SiteSetting::huidige()->fresh()->fill(['mail_style' => MailStijl::Huisstijl])->save();
        $donker = $this->bevestiging()->render();

        // De kaart is wit in de ene stijl en Deep Navy in de andere.
        $this->assertStringContainsString('background-color: #ffffff', $licht);
        $this->assertStringContainsString('background-color: #0a2340', $donker);

        // En de accentstreep bovenaan heeft per stijl zijn eigen blauw.
        $this->assertStringContainsString('4px solid #0787e8', $licht);
        $this->assertStringContainsString('4px solid #13c7f3', $donker);

        $this->assertNotSame($licht, $donker);
    }

    /** Beide themabestanden bestaan ook echt. */
    public function test_both_themes_exist(): void
    {
        foreach (MailStijl::cases() as $stijl) {
            $pad = resource_path('views/vendor/mail/html/themes/'.$stijl->thema().'.css');

            $this->assertTrue(
                File::exists($pad),
                "Het themabestand voor {$stijl->value} ontbreekt: {$pad}",
            );
        }
    }

    /**
     * Het donkere thema gebruikt geen doorzichtige achtergronden.
     *
     * **Dat is geen stijlregel maar een valkuil.** Waar een mailprogramma
     * `transparent` of `rgba()` niet begrijpt valt het terug op wit, en dan
     * staat lichte tekst op een witte achtergrond. In een donkere mail is
     * dat het verschil tussen mooi en onleesbaar.
     */
    public function test_the_dark_theme_has_no_transparent_backgrounds(): void
    {
        $css = (string) File::get(
            resource_path('views/vendor/mail/html/themes/atit-huisstijl.css'),
        );

        // Alleen de echte regels, niet het commentaar dat dit uitlegt.
        $regels = preg_replace('#/\*.*?\*/#s', '', $css);

        $this->assertStringNotContainsString('background-color: transparent', (string) $regels);
        $this->assertStringNotContainsString('rgba(', (string) $regels);
    }

    /* --- Welke kant de mail op zit ----------------------------------------- */

    /**
     * Een mail zegt zelf of hij licht of donker is.
     *
     * **Dit was een echte fout.** Laravel's maillayout heeft
     * `content="light"` vast erin getypt, twee keer. Een donkerblauwe mail
     * die "licht" zegt krijgt in een browser een witte schuifbalk met
     * pijltjes ernaast -- precies wat er in het voorbeeld onder
     * Instellingen → Weergave te zien was -- en Apple Mail en Outlook.com
     * kijken naar `supported-color-schemes` om te beslissen of ze de mail
     * zélf gaan omkleuren.
     *
     * Vandaar dat `layout.blade.php` is gepubliceerd. Deze test houdt vast
     * dat die twee tegels de gekozen stijl volgen; gaat het sjabloon ooit
     * terug naar de versie van Laravel, dan valt hij om.
     */
    public function test_a_mail_says_which_colour_scheme_it_is(): void
    {
        $this->seed(ContactSeeder::class);

        $verwacht = [
            MailStijl::Licht->value => 'light',
            MailStijl::Huisstijl->value => 'dark',
        ];

        foreach (MailStijl::cases() as $stijl) {
            $this->zetStijl($stijl);

            $html = $this->bevestiging()->render();
            $schema = $verwacht[$stijl->value];

            $this->assertStringContainsString(
                '<meta name="color-scheme" content="'.$schema.'">',
                $html,
                "De mail in de stijl {$stijl->value} hoort {$schema} te zeggen.",
            );

            $this->assertStringContainsString(
                '<meta name="supported-color-schemes" content="'.$schema.'">',
                $html,
            );

            // En hetzelfde als eigenschap, want die wint van de metategel.
            $this->assertStringContainsString('color-scheme: '.$schema, $html);
        }
    }

    /**
     * En de schuifbalk van de mail staat op de `<html>`-tag.
     *
     * Dat is de balk die je in het voorbeeld ziet, en die een ontvanger
     * ziet als hij zijn mail in een browser opent. De kleuren zijn
     * dezelfde als `--scrollbar-thumb` in app.css, zodat het voorbeeld
     * dezelfde balk heeft als het portaal eromheen.
     */
    public function test_a_mail_carries_the_brand_scrollbar(): void
    {
        $this->seed(ContactSeeder::class);

        $verwacht = [
            MailStijl::Licht->value => 'scrollbar-color: #c9d4dd #ffffff',
            MailStijl::Huisstijl->value => 'scrollbar-color: #2d5e83 #061626',
        ];

        foreach (MailStijl::cases() as $stijl) {
            $this->zetStijl($stijl);

            $html = $this->bevestiging()->render();

            $this->assertStringContainsString('scrollbar-width: thin', $html);
            $this->assertStringContainsString($verwacht[$stijl->value], $html);
        }
    }

    /**
     * Een mailthema bevat geen `::-webkit-scrollbar`, en dat kan ook niet.
     *
     * Laravel voegt een thema met CssToInlineStyles in de tags van de mail.
     * Dat gereedschap zet alleen selectors in die een element aanwijzen; een
     * pseudo-element wijst niets aan, dus zo'n regel wordt stil weggegooid en
     * haalt de mail niet eens. Wie hier toch een `::-webkit-scrollbar` neerzet
     * denkt dat hij iets heeft opgelost terwijl er niets verandert -- vandaar
     * deze test, in plaats van een opmerking die je over kunt lezen.
     */
    public function test_a_mail_theme_has_no_webkit_scrollbar_rules(): void
    {
        foreach (MailStijl::cases() as $stijl) {
            $css = (string) File::get(
                resource_path('views/vendor/mail/html/themes/'.$stijl->thema().'.css'),
            );

            // Alleen de echte regels, niet het commentaar dat dit uitlegt.
            $regels = (string) preg_replace('#/\*.*?\*/#s', '', $css);

            $this->assertStringNotContainsString('::-webkit-scrollbar', $regels);
        }
    }

    /** `vanThema()` is `thema()` de andere kant op, met licht als terugval. */
    public function test_an_unknown_theme_counts_as_light(): void
    {
        foreach (MailStijl::cases() as $stijl) {
            $this->assertSame($stijl, MailStijl::vanThema($stijl->thema()));
        }

        $this->assertSame(MailStijl::Licht, MailStijl::vanThema('default'));
        $this->assertSame(MailStijl::Licht, MailStijl::vanThema(null));
    }

    /* --- Het scherm -------------------------------------------------------- */

    public function test_the_appearance_screen_knows_the_current_style(): void
    {
        SiteSetting::huidige()->fill(['mail_style' => MailStijl::Huisstijl])->save();

        $this->actingAs($this->beheerder())
            ->get(route('appearance.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('mailStijl', 'huisstijl')
                ->has('mailStijlen', 2));
    }

    /**
     * Het scherm staat in de brede kolom, en dat is geen smaak.
     *
     * De instellingen staan standaard smal (`max-w-xl`, 576 pixels), en dat
     * is goed voor een formulier. Maar de mail zet zichzelf onder 600
     * pixels om naar zijn smalle vorm -- dat staat in de maillayout van
     * Laravel:
     *
     *     @media (max-width: 600px) { .inner-body { width: 100% !important } }
     *
     * Het `iframe` is zijn eigen venster, dus die grens gold voor het
     * voorbeeld: de eigenaar keek naar hoe zijn mail op een telefoon staat
     * en kon dat niet weten. Haalt iemand `breed` hier weg, dan komt die
     * fout terug zonder dat je hem ziet -- vandaar deze test.
     *
     * Een `defineOptions` komt niet in het antwoord van Inertia terecht,
     * dus dit leest het bestand. Zelfde aanpak als PasskeyScreenTest.
     */
    public function test_the_appearance_screen_uses_the_wide_column(): void
    {
        $scherm = (string) File::get(
            resource_path('js/pages/settings/Appearance.vue'),
        );

        // Zonder commentaar, anders keurt de uitleg hierboven zichzelf goed.
        $scherm = (string) preg_replace('#/\*.*?\*/#s', '', $scherm);
        $scherm = (string) preg_replace('#<!--.*?-->#s', '', $scherm);

        $this->assertStringContainsString(
            'breed: true',
            $scherm,
            'Het scherm Weergave staat weer in de smalle kolom, en dan toont '
            .'het mailvoorbeeld de smalle vorm van de mail.',
        );
    }

    /**
     * En het voorbeeld volgt hem.
     *
     * Een voorbeeld dat in de lichte stijl blijft staan nadat je de
     * huisstijl hebt gekozen is erger dan geen voorbeeld: dan vertrouw je
     * iets dat niet klopt.
     */
    public function test_the_preview_follows_the_chosen_style(): void
    {
        $this->seed(ContactSeeder::class);

        $beheerder = $this->beheerder();

        SiteSetting::huidige()->fill(['mail_style' => MailStijl::Huisstijl])->save();

        $bevestiging = $this->actingAs($beheerder)->get(route('appearance.mail'));
        $onderdelen = $this->actingAs($beheerder)->get(route('appearance.mail-onderdelen'));

        $this->assertStringContainsString('#0a2340', $bevestiging->content());
        $this->assertStringContainsString('#0a2340', $onderdelen->content());
    }

    /* --- De kop van de mail ------------------------------------------------ */

    /**
     * Het logo staat in de kop, als PNG en met een alt-tekst.
     *
     * PNG omdat Outlook geen WebP kan, en met alt zodat een postbus die
     * beelden blokkeert alsnog de naam van het bedrijf laat zien -- net als
     * toen hier nog gewone tekst stond.
     */
    public function test_the_header_uses_the_logo_with_an_alt_text(): void
    {
        $this->seed(ContactSeeder::class);

        $html = $this->bevestiging()->render();

        $this->assertStringContainsString('logo-mail.png', $html);
        $this->assertStringContainsString('alt="'.config('app.name').'"', $html);
        $this->assertStringNotContainsString('.webp', $html);
    }

    /** En dat bestand bestaat, in een maat die in een mail past. */
    public function test_the_mail_logo_is_small_enough(): void
    {
        $pad = public_path('images/logo-mail.png');

        $this->assertTrue(File::exists($pad), 'public/images/logo-mail.png ontbreekt.');

        $this->assertLessThan(
            100 * 1024,
            File::size($pad),
            'Het logo in de mail is te zwaar; het origineel is 873 kB en hoort hier niet.',
        );
    }
}
