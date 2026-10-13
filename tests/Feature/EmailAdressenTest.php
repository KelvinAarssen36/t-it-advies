<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * De twee e-mailadressen van de klant, en het verschil ertussen.
 *
 * Dit is uitdrukkelijk afgesproken en het is precies het soort ding dat
 * je per ongeluk door elkaar haalt -- vandaar deze test en niet alleen
 * een regel commentaar:
 *
 * | Adres                   | Waarvoor                                  |
 * | ----------------------- | ----------------------------------------- |
 * | `aarssen@atitadvies.nl` | **Alleen inloggen** in het portaal.       |
 * | `info@atitadvies.nl`    | **Al het andere**: de contactgegevens op de website, en waar het contactformulier naartoe gaat. |
 *
 * Het inlogadres hoort nergens op de publieke site te staan, en het
 * publieke adres hoort nergens als inlog te worden gebruikt.
 *
 * **Waarom dit een test is en geen afspraak op papier.** Het
 * ontvangstadres van het contactformulier viel eerst terug op het
 * from-adres, en dat stond op een no-reply op een verkeerd domein. Een
 * bericht van een bezoeker kwam dan binnen op een postbus die niemand
 * leest, en daar komt niemand achter -- behalve de klant die zich
 * afvraagt waarom er nooit iemand mailt.
 *
 * Zie config/site.php voor de uitleg, en docs/architecture/mail-en-queues.md.
 */
class EmailAdressenTest extends TestCase
{
    /** Het adres waarmee de eigenaar inlogt. */
    private const INLOG = 'aarssen@atitadvies.nl';

    /** Het adres dat de buitenwereld gebruikt. */
    private const PUBLIEK = 'info@atitadvies.nl';

    public function test_the_login_address_is_the_one_we_agreed_on(): void
    {
        $this->assertSame(
            self::INLOG,
            config('security.portal_account.email'),
        );
    }

    public function test_the_public_address_is_the_one_we_agreed_on(): void
    {
        $this->assertSame(self::PUBLIEK, config('site.email'));
    }

    /**
     * De twee zijn niet hetzelfde.
     *
     * Een flauwe test op het eerste gezicht, maar hij dekt de fout die
     * het meest waarschijnlijk is: iemand vult op één plek het andere
     * adres in omdat hij het verschil niet kende.
     */
    public function test_the_two_addresses_are_not_the_same(): void
    {
        $this->assertNotSame(
            config('security.portal_account.email'),
            config('site.email'),
        );
    }

    /**
     * Het contactformulier komt binnen op het publieke adres.
     *
     * Hier gaat het echt om. `MAIL_CONTACT_ADDRESS` mag leeg blijven --
     * dat is zelfs de bedoeling -- en dan hoort de terugval het publieke
     * adres te zijn en niet het afzendadres.
     */
    public function test_the_contact_form_arrives_at_the_public_address(): void
    {
        $this->assertSame(self::PUBLIEK, config('mail.contact_address'));
    }

    /**
     * En de mail gaat vanaf datzelfde adres de deur uit.
     *
     * Niet vanaf een verzonnen no-reply: op gedeelde hosting komt mail
     * van een adres dat niet als echte postbus bestaat eerder in de
     * spam terecht.
     *
     * **Deze test heeft al één keer iets gevonden.** De terugval stond
     * als tweede parameter van `env()`, en die slaat niet aan bij een
     * `MAIL_FROM_ADDRESS=` zonder waarde -- dat is een lege string en
     * geen null. Het afzendadres bleef dus leeg, en Symfony weigert dan
     * de hele mail.
     */
    public function test_mail_is_sent_from_the_public_address(): void
    {
        $this->assertSame(self::PUBLIEK, config('mail.from.address'));
    }

    /**
     * Het inlogadres staat nergens in de publieke onderdelen.
     *
     * Een tekstcontrole, want dit is nu juist de fout die geen enkele
     * andere test zou opmerken: een adres dat op de voorpagina belandt
     * doet daar niets verkeerd -- tot iemand het als inlog gebruikt of
     * er post naartoe stuurt die nooit wordt gelezen.
     */
    public function test_the_login_address_appears_nowhere_on_the_public_site(): void
    {
        $mappen = [
            resource_path('js/components/site'),
            resource_path('js/pages/Welcome.vue'),
            resource_path('js/layouts'),
        ];

        $gevonden = [];

        foreach ($mappen as $pad) {
            foreach ($this->bestanden($pad) as $bestand) {
                if (str_contains((string) file_get_contents($bestand), self::INLOG)) {
                    $gevonden[] = $bestand;
                }
            }
        }

        $this->assertSame(
            [],
            $gevonden,
            'Het inlogadres staat in een publiek onderdeel. Gebruik daar '
                .self::PUBLIEK.'; zie config/site.php.',
        );
    }

    /**
     * Alle bestanden onder een pad, of het pad zelf als het een bestand is.
     *
     * @return array<int, string>
     */
    private function bestanden(string $pad): array
    {
        if (is_file($pad)) {
            return [$pad];
        }

        if (! is_dir($pad)) {
            return [];
        }

        $uitkomst = [];

        $bestanden = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pad, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($bestanden as $bestand) {
            if (in_array($bestand->getExtension(), ['vue', 'ts'], true)) {
                $uitkomst[] = $bestand->getPathname();
            }
        }

        return $uitkomst;
    }
}
