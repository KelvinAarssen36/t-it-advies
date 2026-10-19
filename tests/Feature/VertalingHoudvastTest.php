<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use SplFileInfo;
use Tests\TestCase;

/**
 * Het Nederlands als houvast bij een Engels veld.
 *
 * **Waarom dit een test verdient.** Lukt het automatisch vertalen niet --
 * de dienst ligt eruit, of er is geen sleutel ingesteld -- dan stond de
 * klant voor een leeg Engels vakje zonder te zien waar het ook alweer
 * over ging. Nu staat zijn eigen Nederlandse tekst er grijs bij.
 *
 * Dat gebeurt op twee manieren, en welke van de twee hangt af van waar
 * het Engelse veld staat:
 *
 * - **Staat het Engels op een eigen stap**, dan staat het Nederlands er
 *   als citaat boven: `.brand-bron`. Dat blijft staan terwijl je typt, en
 *   dat is precies wat je wilt als je niet kunt terugbladeren.
 * - **Staat het Engels onder het Nederlands op hetzelfde scherm**, dan is
 *   het een grijze hint in het veld zelf: `nederlandsAlsHint()`. Een
 *   citaat zou daar letterlijk herhalen wat twee centimeter hoger staat.
 *
 * **Het mag nooit de waarde van het veld worden.** Een hint wordt niet
 * meeverstuurd; zet je het Nederlands in het veld zelf, dan slaat de
 * klant het op alsof het de vertaling is. Dat is de fout die we hier
 * uitsluiten, en de laatste test bewaakt hem.
 *
 * Zie resources/js/lib/vertaling.ts en docs/architecture/vertalingen.md.
 */
class VertalingHoudvastTest extends TestCase
{
    /**
     * Velden met een eigen Engelse standaardtekst.
     *
     * De knoptekst van LinkedIn en het woord onder een cijfer vallen bij
     * leeg laten niet terug op het Nederlands maar op een Engelse
     * standaard. Die standaard is wat de bezoeker te zien krijgt, dus die
     * hoort als hint in het veld te staan en niet het Nederlands.
     *
     * @var list<string>
     */
    private const EIGEN_ENGELSE_STANDAARD = [
        'components/website/CijfersDialoog.vue',
        'components/website/LinkedinKnopDialoog.vue',
    ];

    /** Hoe ver terug we naar het citaat met het Nederlands zoeken. */
    private const VENSTER = 2500;

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
     * Elk invoerveld dat aan een Engelse kolom hangt.
     *
     * @return list<array{veld: string, tag: string, voor: string}>
     */
    private function engelseVelden(string $inhoud): array
    {
        preg_match_all(
            '/<(?:Input|Textarea)\b([^>]*?)\/>/s',
            $inhoud,
            $gevonden,
            PREG_OFFSET_CAPTURE,
        );

        $uit = [];

        foreach ($gevonden[1] as $treffer) {
            [$tag, $positie] = $treffer;

            if (! preg_match('/v-model="([\w.]*_en)"/', $tag, $naam)) {
                continue;
            }

            $uit[] = [
                'veld' => $naam[1],
                'tag' => $tag,
                'voor' => substr($inhoud, 0, $positie),
            ];
        }

        return $uit;
    }

    public function test_the_search_finds_something(): void
    {
        /*
         * Zonder deze test zou een kapotte reguliere expressie alles
         * hieronder groen maken: nul gevonden velden is ook nul fouten.
         */
        $faq = $this->schermen()['components/website/FaqDialoog.vue'];

        $velden = array_column($this->engelseVelden($faq), 'veld');

        $this->assertContains(
            'formulier.question_en',
            $velden,
            'De Engelse invoervelden worden niet meer gevonden.',
        );
    }

    public function test_every_english_field_shows_the_dutch_it_belongs_to(): void
    {
        $zonder = [];

        foreach ($this->schermen() as $pad => $inhoud) {
            if (in_array($pad, self::EIGEN_ENGELSE_STANDAARD, true)) {
                continue;
            }

            foreach ($this->engelseVelden($inhoud) as $veld) {
                $nederlands = preg_replace('/_en$/', '_nl', $veld['veld']);

                // De grijze hint in het veld zelf.
                $plat = preg_replace('/\s+/', '', $veld['tag']);

                if (str_contains(
                    (string) $plat,
                    'nederlandsAlsHint('.$nederlands,
                )) {
                    continue;
                }

                // Of het citaat erboven, met dezelfde Nederlandse kolom.
                $staart = substr($veld['voor'], -self::VENSTER);

                if (preg_match(
                    '/brand-bron[\s\S]{0,120}\{\{\s*'.preg_quote((string) $nederlands, '/').'\b/',
                    $staart,
                )) {
                    continue;
                }

                $zonder[] = $pad.': '.$veld['veld'];
            }
        }

        $this->assertSame(
            [],
            $zonder,
            'Deze Engelse velden laten het Nederlands niet zien. Zet er '
                .'nederlandsAlsHint() op, of een <p class="brand-bron"> '
                .'erboven: '.implode(', ', $zonder),
        );
    }

    public function test_the_dutch_hint_can_never_become_the_translation(): void
    {
        /*
         * De hele belofte van deze hulp: de klant ziet het Nederlands,
         * maar kan het niet per ongeluk opslaan alsof het de vertaling
         * is. Dat klopt zolang hij in een `placeholder` staat -- die
         * wordt nooit meeverstuurd. Zet iemand hem ooit op `v-model`,
         * `:value` of `:model-value`, dan is die belofte weg.
         */
        $fout = [];

        foreach ($this->schermen() as $pad => $inhoud) {
            preg_match_all(
                '/(v-model|:value|:model-value|:default-value)="[^"]*nederlandsAlsHint/',
                $inhoud,
                $gevonden,
            );

            foreach ($gevonden[1] as $waar) {
                $fout[] = $pad.': '.$waar;
            }
        }

        $this->assertSame(
            [],
            $fout,
            'Het Nederlands staat hier als waarde en niet als grijze hint. '
                .'Dan slaat de klant het op alsof het de vertaling is: '
                .implode(', ', $fout),
        );
    }

    public function test_the_helper_hands_back_nothing_for_an_empty_text(): void
    {
        /*
         * Een lege hint hoort `undefined` te zijn en geen lege tekst: een
         * lege `placeholder` is een attribuut dat er wel staat maar niets
         * doet, en dat leest in de opmaak als een vergeten plek.
         */
        $bron = (string) file_get_contents(
            resource_path('js/lib/vertaling.ts'),
        );

        $this->assertStringContainsString(
            '.trim()',
            $bron,
            'Een veld met alleen spaties hoort geen hint op te leveren.',
        );

        $this->assertStringContainsString(
            "=== '' ? undefined :",
            $bron,
            'Een leeg Nederlands veld hoort geen hint op te leveren, en '
                .'zeker geen lege tekst: dat wordt een placeholder die er '
                .'wel staat maar niets doet.',
        );
    }
}
