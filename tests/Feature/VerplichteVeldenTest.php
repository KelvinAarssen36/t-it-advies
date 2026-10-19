<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use SplFileInfo;
use Tests\TestCase;

/**
 * De bewaking op verplichte velden in de vensters van het portaal.
 *
 * **Waarom dit een test verdient.** De klant beheert alles zelf. Kon hij
 * op "Volgende" of "Opslaan" drukken terwijl er een verplicht veld leeg
 * was, dan kreeg hij drie stappen later een rode serverfout over een veld
 * dat hij al niet meer voor zich had. Dat is nu omgedraaid: de knop is
 * grijs zolang er iets mist, en drukt hij toch, dan hoort hij wát er mist.
 *
 * Dat hangt aan een paar dingen die stil kunnen breken, en elk ervan heeft
 * hieronder zijn eigen test:
 *
 * 1. Een nieuw venster met een sterretje dat `useVerplicht()` vergeet.
 * 2. Een naam in de melding die niet overeenkomt met het label op het
 *    scherm -- dan leest de klant over een veld dat hij nergens ziet.
 * 3. Een sterretje bij een veld dat niet bewaakt wordt.
 * 4. Een knop die weer hard wordt uitgeschakeld op een leeg veld; dan is
 *    er geen klik meer en dus ook geen uitleg.
 *
 * Deze tests lezen de componenten als tekst, net als `SiteTerugTest`: het
 * gaat om wat er in de browser gebeurt en niet om een antwoord van de
 * server.
 *
 * Zie resources/js/lib/verplicht.ts en
 * docs/architecture/formulieren-en-schuifbalken.md.
 */
class VerplichteVeldenTest extends TestCase
{
    /**
     * Formulieren die hun waarden niet in een eigen variabele bewaren.
     *
     * Dit zijn de schermen op Inertia's `<Form>`: de invoervelden staan er
     * zonder `v-model`, dus er is niets om "staat hier al iets?" aan af te
     * lezen. Meekijken met wat er getypt wordt zou kunnen, maar juist hier
     * vult een wachtwoordbeheerder de velden vaak in zonder een
     * toetsaanslag -- en dan blijft de knop grijs terwijl alles is
     * ingevuld. Dat is erger dan wat we wilden oplossen.
     *
     * De server geeft hier meteen een duidelijke melding terug, en
     * Profile.vue heeft het `required`-attribuut van de browser zelf.
     *
     * @var list<string>
     */
    private const ZONDER_EIGEN_WAARDEN = [
        'components/PasskeyRegister.vue',
        'components/site/ContactFormulier.vue',
        'pages/auth/ConfirmPassword.vue',
        'pages/auth/ForgotPassword.vue',
        'pages/auth/Login.vue',
        'pages/auth/ResetPassword.vue',
        'pages/settings/Profile.vue',
        'pages/settings/Security.vue',
    ];

    /**
     * Keuzelijsten die altijd al een waarde hebben.
     *
     * Ze krijgen wel een sterretje -- de server eist ze -- maar er valt
     * niets leeg te laten, dus er is ook niets te bewaken.
     *
     * @var list<string>
     */
    private const ALTIJD_GEVULD = ['Soort', 'Weergave'];

    /**
     * Elk Vue-bestand in resources/js met zijn inhoud.
     *
     * @return array<string, string>
     */
    private function schermen(): array
    {
        $uit = [];

        foreach (File::allFiles(resource_path('js')) as $bestand) {
            /** @var SplFileInfo $bestand */
            if ($bestand->getExtension() !== 'vue') {
                continue;
            }

            $pad = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                $bestand->getRelativePathname(),
            );

            $uit[$pad] = (string) file_get_contents($bestand->getPathname());
        }

        ksort($uit);

        return $uit;
    }

    /**
     * De labels met een sterretje, zoals ze op het scherm staan.
     *
     * @return list<string>
     */
    private function sterretjes(string $inhoud): array
    {
        preg_match_all(
            '/verplicht>\s*(?:<LocaleFlag[^>]*>\s*)?\{\{\s*\$t\(\s*\'([^\']*)\'\s*\)\s*\}\}/',
            $inhoud,
            $enkel,
        );

        // Een label dat onderweg van naam wisselt: "Percentage" of "Getal".
        preg_match_all(
            '/verplicht>\s*\{\{\s*\w+\s*\?\s*\$t\(\'([^\']*)\'\)\s*:\s*\$t\(\'([^\']*)\'\)/',
            $inhoud,
            $beide,
        );

        return [...$enkel[1], ...$beide[1], ...$beide[2]];
    }

    /**
     * De namen die `useVerplicht()` in de melding zet.
     *
     * @return list<string>
     */
    private function bewaakteNamen(string $inhoud): array
    {
        preg_match_all('/naam: t\(\'([^\']*)\'\)/', $inhoud, $gevonden);

        // De naam mag meewisselen met het label; pak dan allebei de kanten.
        preg_match_all(
            '/naam: \w+\.value \? t\(\'([^\']*)\'\) : t\(\'([^\']*)\'\)/',
            $inhoud,
            $beide,
        );

        return [...$gevonden[1], ...$beide[1], ...$beide[2]];
    }

    public function test_the_search_finds_something(): void
    {
        /*
         * Zonder deze test zou een kapotte reguliere expressie alles
         * hieronder groen maken: nul gevonden velden is ook nul fouten.
         */
        $schermen = $this->schermen();

        $this->assertArrayHasKey(
            'components/website/ProjectDialoog.vue',
            $schermen,
        );

        $project = $schermen['components/website/ProjectDialoog.vue'];

        $this->assertContains(
            'Organisatie',
            $this->sterretjes($project),
            'De labels met een sterretje worden niet meer gevonden.',
        );

        $this->assertContains(
            'Organisatie',
            $this->bewaakteNamen($project),
            'De bewaakte namen worden niet meer gevonden.',
        );
    }

    public function test_every_screen_with_a_required_field_guards_it(): void
    {
        $zonder = [];

        foreach ($this->schermen() as $pad => $inhoud) {
            if (! preg_match('/verplicht[>=]/', $inhoud)) {
                continue;
            }

            if (in_array($pad, self::ZONDER_EIGEN_WAARDEN, true)) {
                continue;
            }

            if (str_contains($inhoud, 'useVerplicht(')) {
                continue;
            }

            $zonder[] = $pad;
        }

        $this->assertSame(
            [],
            $zonder,
            'Deze schermen hebben een verplicht veld maar bewaken het niet. '
                .'Gebruik useVerplicht() uit lib/verplicht.ts, of zet het '
                .'scherm in ZONDER_EIGEN_WAARDEN met een reden erbij: '
                .implode(', ', $zonder),
        );
    }

    public function test_every_guarded_field_is_a_label_the_owner_can_see(): void
    {
        $fout = [];

        foreach ($this->schermen() as $pad => $inhoud) {
            if (! str_contains($inhoud, 'useVerplicht(')) {
                continue;
            }

            $sterretjes = $this->sterretjes($inhoud);

            foreach ($this->bewaakteNamen($inhoud) as $naam) {
                if (in_array($naam, $sterretjes, true)) {
                    continue;
                }

                $fout[] = $pad.': '.$naam;
            }
        }

        $this->assertSame(
            [],
            $fout,
            'De melding noemt een veldnaam die nergens als label met een '
                .'sterretje op het scherm staat: '.implode(', ', $fout),
        );
    }

    public function test_every_required_label_is_really_guarded(): void
    {
        $fout = [];

        foreach ($this->schermen() as $pad => $inhoud) {
            if (! str_contains($inhoud, 'useVerplicht(')) {
                continue;
            }

            $namen = $this->bewaakteNamen($inhoud);

            foreach ($this->sterretjes($inhoud) as $label) {
                if (in_array($label, self::ALTIJD_GEVULD, true)) {
                    continue;
                }

                if (in_array($label, $namen, true)) {
                    continue;
                }

                $fout[] = $pad.': '.$label;
            }
        }

        $this->assertSame(
            [],
            $fout,
            'Dit label heeft een sterretje maar zit niet in useVerplicht(), '
                .'dus de knop blijft indrukbaar terwijl het veld leeg is: '
                .implode(', ', $fout),
        );
    }

    public function test_no_button_is_switched_off_on_an_empty_field(): void
    {
        /*
         * `disabled` is voor wat echt niet kan -- er loopt al een verzoek.
         * Hangt hij aan een lege waarde, dan hoort het `geblokkeerd` te
         * zijn: dan komt de klik binnen en hoort de klant wat er mist.
         *
         * De vertaalknop is de uitzondering en staat er met opzet niet
         * tussen: een lege tekst valt niet te vertalen, en die knop is een
         * hulpje en geen doorgang.
         */
        $fout = [];

        foreach ($this->schermen() as $pad => $inhoud) {
            preg_match_all(
                '/<Button\b[\s\S]*?>/',
                $inhoud,
                $knoppen,
            );

            foreach ($knoppen[0] as $knop) {
                if (! preg_match('/:disabled="[^"]*trim\(\) === /', $knop)) {
                    continue;
                }

                if (str_contains($knop, 'vertaal')) {
                    continue;
                }

                $fout[] = $pad;
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($fout)),
            'Deze knop gaat hard uit op een leeg veld. Gebruik `geblokkeerd`, '
                .'zodat de klik binnenkomt en er uitleg volgt: '
                .implode(', ', array_unique($fout)),
        );
    }

    public function test_the_button_tells_a_screen_reader_it_is_blocked(): void
    {
        $knop = (string) file_get_contents(
            resource_path('js/components/ui/button/Button.vue'),
        );

        $this->assertStringContainsString(
            ':aria-disabled="geblokkeerd || undefined"',
            $knop,
            'Een grijze knop moet zich bij een schermlezer melden.',
        );

        $this->assertStringNotContainsString(
            ':disabled="geblokkeerd',
            $knop,
            'Een geblokkeerde knop mag niet echt uitgeschakeld worden; dan '
                .'komt de klik niet binnen en volgt er geen uitleg.',
        );
    }
}
