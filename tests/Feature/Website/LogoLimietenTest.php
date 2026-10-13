<?php

namespace Tests\Feature\Website;

use Tests\TestCase;

/**
 * De grenzen aan een geüpload beeldmerk staan op twee plekken, en die
 * moeten gelijk blijven.
 *
 * De browser keurt het gekozen bestand al af of verkleint het, vóór het
 * versturen (`resources/js/lib/beeldmerk.ts`). De server keurt daarna nog
 * eens, want wie het formulier omzeilt komt daar alsnog langs
 * (`config/media.php`, gebruikt door `ExperienceRequest`).
 *
 * Lopen die twee uiteen, dan gaat het op de vervelendste manier mis: de
 * browser verkleint keurig naar een maat die de server vervolgens weigert,
 * en de klant leest een foutmelding over een bestand dat hij helemaal niet
 * gekozen heeft. Deze test leest de getallen uit het TypeScript-bestand en
 * legt ze naast de configuratie.
 *
 * Verander je een grens, verander hem dan op allebei de plekken -- en kijk
 * ook naar `upload_max_filesize` op de server; zie
 * docs/operations/deployment.md.
 */
class LogoLimietenTest extends TestCase
{
    private function constante(string $naam): int
    {
        $pad = resource_path('js/lib/beeldmerk.ts');

        $this->assertFileExists($pad);

        $inhoud = (string) file_get_contents($pad);

        $gevonden = preg_match(
            '/export const '.preg_quote($naam, '/').'\s*=\s*([^;]+);/',
            $inhoud,
            $treffers,
        );

        $this->assertSame(1, $gevonden, "De constante {$naam} staat niet in beeldmerk.ts.");

        // De waarden zijn kleine rekensommen als `2 * 1024 * 1024`. Alleen
        // cijfers, spaties en maalpunten, dus er valt niets uit te voeren
        // wat er niet hoort te staan.
        $uitdrukking = trim($treffers[1]);

        $this->assertMatchesRegularExpression(
            '/^[\d\s*]+$/',
            $uitdrukking,
            "De constante {$naam} is geen eenvoudige rekensom meer; werk deze test bij.",
        );

        return (int) array_product(
            array_map('intval', preg_split('/\s*\*\s*/', $uitdrukking) ?: []),
        );
    }

    public function test_the_browser_and_the_server_agree_on_the_maximum_size(): void
    {
        $this->assertSame(
            (int) config('media.logo.max_kb') * 1024,
            $this->constante('LOGO_MAX_BYTES'),
        );
    }

    public function test_the_browser_and_the_server_agree_on_the_smallest_side(): void
    {
        $this->assertSame(
            (int) config('media.logo.min_zijde'),
            $this->constante('LOGO_MIN_ZIJDE'),
        );
    }

    public function test_the_browser_and_the_server_agree_on_the_longest_side(): void
    {
        $this->assertSame(
            (int) config('media.logo.max_zijde'),
            $this->constante('LOGO_MAX_ZIJDE'),
        );
    }

    public function test_php_accepts_more_than_we_do(): void
    {
        /*
         * PHP kapt een te groot verzoek af vóórdat Laravel het ziet. Dan
         * komt er een leeg formulier binnen zonder bestand en zonder
         * foutmelding, en dat is een raadsel in plaats van een melding.
         *
         * Strikt groter, niet gelijk: bij precies gelijk is een bestand op
         * de grens al te groot voor PHP terwijl onze eigen regel hem nog
         * zou goedkeuren. Dat is het ene geval waarin de klant een fout
         * krijgt die nergens beschreven staat.
         */
        $toegestaan = $this->naarBytes((string) ini_get('upload_max_filesize'));

        if ($toegestaan === 0) {
            $this->markTestSkipped('Deze PHP kent geen upload_max_filesize.');
        }

        $this->assertGreaterThan(
            (int) config('media.logo.max_kb') * 1024,
            $toegestaan,
            'upload_max_filesize staat te krap voor de grens in config/media.php.',
        );
    }

    public function test_the_whole_form_fits_through_php(): void
    {
        /*
         * Het verzoek draagt meer dan het bestand: twee talen tekst, de
         * uitsnede, de tokens. `post_max_size` geldt voor het geheel, dus
         * die moet boven `upload_max_filesize` liggen -- anders loopt een
         * bestand dat op zichzelf mag, alsnog vast op de optelsom.
         */
        $bestand = $this->naarBytes((string) ini_get('upload_max_filesize'));
        $geheel = $this->naarBytes((string) ini_get('post_max_size'));

        if ($bestand === 0 || $geheel === 0) {
            $this->markTestSkipped('Deze PHP kent deze instellingen niet.');
        }

        $this->assertGreaterThan($bestand, $geheel);
    }

    /** "8M" wordt 8388608. */
    private function naarBytes(string $waarde): int
    {
        $waarde = trim($waarde);

        if ($waarde === '') {
            return 0;
        }

        $getal = (int) $waarde;

        return match (strtolower(substr($waarde, -1))) {
            'g' => $getal * 1024 * 1024 * 1024,
            'm' => $getal * 1024 * 1024,
            'k' => $getal * 1024,
            default => $getal,
        };
    }
}
