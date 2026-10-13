<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

/**
 * De beloftes over gegevens van bezoekers moeten waar blijven.
 *
 * **Dit is een vangnet voor een fout die al één keer is gemaakt.** Toen de
 * module Contact erbij kwam, begon de website berichten van bezoekers te
 * bewaren. Daarmee werden een paar zinnen onwaar die op vier plekken
 * stonden. Drie daarvan zijn meteen aangepast -- de privacyverklaring, de
 * antwoordtekst en de stappen bij "Niets gevonden" -- en de vierde, de
 * handleiding in het portaal, bleef maanden het tegendeel vertellen:
 *
 *     "Zijn bericht via het contactformulier staat in je mailbox,
 *      nergens anders. Op de website zelf staat het niet."
 *
 * Geen test voelde dat, want het is tekst op een scherm en niet gedrag.
 * Dezelfde soort fout als bij de zijbalk, en daarom dezelfde soort test:
 * grof, maar hij kan niet verlopen. Zie AppSidebarTest.
 *
 * **Voeg hier een zin aan toe zodra je een belofte ziet die kon kloppen en
 * nu niet meer klopt.** Dat is goedkoper dan hem over een jaar opnieuw
 * tegenkomen.
 *
 * Zie docs/architecture/modules/contact.md en
 * docs/security/verzoeken-van-bezoekers.md.
 */
class PrivacybeloftesTest extends TestCase
{
    /**
     * De plekken waar tegen de klant of de bezoeker wordt uitgelegd waar
     * zijn gegevens staan.
     */
    private const BESTANDEN = [
        'resources/js/pages/settings/Documentatie.vue',
        'resources/js/pages/admin/Juridisch.vue',
        'resources/js/pages/public/Privacy.vue',
        'app/Support/Juridisch/Antwoordtekst.php',
    ];

    /**
     * Zinnen die waar waren vóór de module Contact en het nu niet meer
     * zijn, met de reden erbij. Die reden komt in de foutmelding terecht,
     * zodat wie hem tegenkomt niet hoeft te raden wat eraan mankeert.
     *
     * @var array<string, string>
     */
    private const ONWAAR = [
        'nergens anders' => 'Een bericht uit het contactformulier staat wél ergens anders: in contact_submissions, te zien onder Beheer → Aanvragen.',
        'Op de website zelf staat het niet' => 'Het staat juist wél op de website, in het beheergedeelte, met een bewaartermijn.',
        'Een bezoeker staat hier niet in' => 'Sinds de bevestigingsmail staat het adres van de bezoeker als ontvanger in mail_logs.',
        'alleen jouw eigen adres als ontvanger' => 'Niet meer: de bevestiging gaat naar de bezoeker, dus zijn adres staat er ook in.',
        'staat het alleen nog in onze mailbox' => 'Het staat daarna ook in het beheergedeelte van de website.',
    ];

    public function test_no_screen_still_makes_a_promise_that_became_untrue(): void
    {
        $gevonden = [];

        foreach (self::BESTANDEN as $pad) {
            $inhoud = $this->zonderCommentaar($pad);

            foreach (self::ONWAAR as $zin => $waarom) {
                if (str_contains($inhoud, $zin)) {
                    $gevonden[] = "{$pad}: \"{$zin}\" -- {$waarom}";
                }
            }
        }

        $this->assertSame(
            [],
            $gevonden,
            'Deze beloftes zijn niet meer waar:'.PHP_EOL.implode(PHP_EOL, $gevonden),
        );
    }

    /**
     * En de andere kant op: staat er nog wél wat er hóórt te staan?
     *
     * Zonder deze test zou het weghalen van de hele uitleg ook groen zijn.
     * Een belofte die niet meer klopt los je op door hem te herschrijven en
     * niet door hem te verwijderen.
     */
    public function test_the_manual_does_point_at_the_requests_screen(): void
    {
        $handleiding = $this->zonderCommentaar(
            'resources/js/pages/settings/Documentatie.vue',
        );

        $this->assertStringContainsString(
            'Beheer → Aanvragen',
            $handleiding,
            'De handleiding wijst de eigenaar niet meer naar het scherm waar de berichten van zijn bezoekers staan.',
        );

        /*
         * De antwoordtekst noemt met opzet géén plek -- een bezoeker heeft
         * niets aan "Beheer → Aanvragen". Wat hij wél moet weten is dat er
         * iets bewaard wordt en hoe lang, en dat getal komt uit de
         * instelling die het ook echt bepaalt. Dáár is deze belofte aan te
         * herkennen.
         */
        $antwoord = $this->zonderCommentaar('app/Support/Juridisch/Antwoordtekst.php');

        $this->assertStringContainsString(
            'bewaren we :dagen dagen',
            $antwoord,
            'De antwoordtekst zegt een bezoeker niet meer dat zijn bericht wordt bewaard, en hoe lang.',
        );
    }

    private function bestand(string $pad): string
    {
        $volledig = base_path($pad);

        $this->assertFileExists($volledig);

        return (string) file_get_contents($volledig);
    }

    /**
     * Hetzelfde bestand, maar zonder commentaar.
     *
     * Noodzakelijk en niet netjesheid: het commentaar in deze bestanden
     * **noemt** de oude zinnen om uit te leggen wat er misging. Zonder deze
     * stap vindt deze test zijn eigen uitleg en meldt hij een fout die er
     * niet is. Zelfde helper als in PasskeyScreenTest.
     */
    private function zonderCommentaar(string $pad): string
    {
        $inhoud = $this->bestand($pad);

        $inhoud = (string) preg_replace('#/\*.*?\*/#s', '', $inhoud);
        $inhoud = (string) preg_replace('#<!--.*?-->#s', '', $inhoud);
        $inhoud = (string) preg_replace('#\{\{--.*?--\}\}#s', '', $inhoud);

        return (string) preg_replace('#^\s*//.*$#m', '', $inhoud);
    }
}
