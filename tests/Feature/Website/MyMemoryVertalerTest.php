<?php

namespace Tests\Feature\Website;

use App\Support\Translation\MyMemoryVertaler;
use App\Support\Translation\VertaalFout;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * De vertaler zelf, met een nagebootst antwoord van de dienst.
 *
 * `Http::fake()` in elke test: de echte dienst wordt hier nooit
 * aangeroepen. Dat is niet alleen netheid -- een testsuite die het
 * internet op gaat, is traag en valt om zodra er iets hapert bij een
 * ander.
 *
 * Wat hier wordt bewaakt zijn de vier dingen die bij deze dienst echt fout
 * kunnen gaan: een opgemaakte tekst met HTML-entiteiten, een
 * vertaalgeheugen dat onzin teruggeeft, een dagtegoed dat op is, en een
 * antwoord dat er wel is maar niets bruikbaars bevat.
 *
 * Zie docs/architecture/automatisch-vertalen.md.
 */
class MyMemoryVertalerTest extends TestCase
{
    private function vertaler(): MyMemoryVertaler
    {
        return new MyMemoryVertaler(
            true,
            'https://api.mymemory.translated.net/get',
            null,
            5,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $matches
     * @return array<string, mixed>
     */
    private function antwoord(string $tekst, array $matches = []): array
    {
        return [
            'responseData' => ['translatedText' => $tekst, 'match' => 0.85],
            'quotaFinished' => false,
            'responseDetails' => '',
            'responseStatus' => 200,
            'matches' => $matches,
        ];
    }

    public function test_it_translates_and_keeps_the_field_names(): void
    {
        Http::fake([
            '*' => Http::response($this->antwoord('System administrator')),
        ]);

        $uitkomst = $this->vertaler()->naarEngels([
            'role_nl' => 'Systeembeheerder',
        ]);

        // De sleutel blijft staan, want de aanroeper moet weten welk
        // antwoord bij welk veld hoort.
        $this->assertSame(['role_nl' => 'System administrator'], $uitkomst);
    }

    public function test_an_empty_field_costs_no_request(): void
    {
        Http::fake(['*' => Http::response($this->antwoord('x'))]);

        $uitkomst = $this->vertaler()->naarEngels([
            'role_nl' => 'Beheerder',
            'location_nl' => '',
            'description_nl' => null,
        ]);

        $this->assertSame(['role_nl' => 'x'], $uitkomst);

        // Eén verzoek en niet drie: elk verzoek kost tekens van het
        // dagtegoed.
        Http::assertSentCount(1);
    }

    public function test_the_machine_translation_wins_from_the_memory(): void
    {
        /*
         * MyMemory is deels een vertaalgeheugen dat mensen vullen. Voor
         * een korte term komt daar zomaar een zin uit een handleiding uit.
         * De machinevertaling is minder mooi maar wél voorspelbaar, en dat
         * is hier meer waard.
         */
        Http::fake([
            '*' => Http::response($this->antwoord('Iets uit een oud document', [
                ['created-by' => 'iemand', 'translation' => 'Iets uit een oud document'],
                ['created-by' => 'MT!', 'translation' => 'Maintenance'],
            ])),
        ]);

        $this->assertSame(
            ['role_nl' => 'Maintenance'],
            $this->vertaler()->naarEngels(['role_nl' => 'Beheer']),
        );
    }

    public function test_html_entities_are_decoded(): void
    {
        // Zonder dit staat er letterlijk `&#39;` op de website.
        Http::fake([
            '*' => Http::response($this->antwoord('The company&#39;s network')),
        ]);

        $this->assertSame(
            ['role_nl' => "The company's network"],
            $this->vertaler()->naarEngels(['role_nl' => 'Het netwerk van het bedrijf']),
        );
    }

    public function test_an_exhausted_allowance_is_an_error_and_not_a_translation(): void
    {
        /*
         * Het tegoed komt terug als een 403 ín het antwoord en niet als
         * een HTTP-fout. Zonder die controle krijgt de klant de zin "YOU
         * USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY" als vertaling in
         * zijn veld.
         */
        Http::fake([
            '*' => Http::response([
                'responseData' => ['translatedText' => 'YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY'],
                'quotaFinished' => true,
                'responseStatus' => 403,
            ]),
        ]);

        try {
            $this->vertaler()->naarEngels(['role_nl' => 'Beheerder']);
            $this->fail('Er had een VertaalFout moeten komen.');
        } catch (VertaalFout $fout) {
            $this->assertSame(VertaalFout::TEGOED_OP, $fout->reden);
        }
    }

    public function test_a_server_error_becomes_a_readable_failure(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        try {
            $this->vertaler()->naarEngels(['role_nl' => 'Beheerder']);
            $this->fail('Er had een VertaalFout moeten komen.');
        } catch (VertaalFout $fout) {
            $this->assertSame(VertaalFout::GEEN_VERBINDING, $fout->reden);
            // En de melding zegt wat de klant nu kan doen.
            $this->assertStringContainsString('zelf in', $fout->melding());
        }
    }

    public function test_an_empty_answer_keeps_the_original(): void
    {
        // Liever de Nederlandse tekst terug dan een leeg veld: dan ziet de
        // klant meteen dat er niets is vertaald.
        Http::fake(['*' => Http::response($this->antwoord(''))]);

        $this->assertSame(
            ['role_nl' => 'Beheerder'],
            $this->vertaler()->naarEngels(['role_nl' => 'Beheerder']),
        );
    }

    public function test_the_email_is_sent_along_when_it_is_set(): void
    {
        // Het adres verhoogt het dagtegoed van 5.000 naar 50.000 tekens.
        Http::fake(['*' => Http::response($this->antwoord('x'))]);

        (new MyMemoryVertaler(true, 'https://api.mymemory.translated.net/get', 'info@example.com', 5))
            ->naarEngels(['role_nl' => 'Beheerder']);

        Http::assertSent(fn (Request $verzoek) => str_contains($verzoek->url(), 'de=info%40example.com'));
    }

    public function test_a_long_text_is_cut_into_pieces(): void
    {
        /*
         * De dienst neemt hoogstens 500 tekens per verzoek. Daarboven geeft
         * hij geen vertaling maar een foutcode -- en dat is precies wat er
         * gebeurde bij een beschrijving van een paar alinea's: korte
         * teksten werkten, lange vielen stil.
         */
        Http::fake(['*' => Http::response($this->antwoord('Piece.'))]);

        $lang = trim(str_repeat('Dit is een zin van een stuk of veertig tekens. ', 30));

        $uitkomst = $this->vertaler()->naarEngels(['description_nl' => $lang]);

        Http::assertSentCount(4);
        $this->assertStringContainsString('Piece.', $uitkomst['description_nl']);

        // Geen enkel verzoek mag over de grens van de dienst gaan.
        Http::assertSent(function (Request $verzoek) {
            parse_str((string) parse_url($verzoek->url(), PHP_URL_QUERY), $vragen);

            return strlen((string) ($vragen['q'] ?? '')) <= 500;
        });
    }

    public function test_short_text_still_goes_in_one_request(): void
    {
        // De opdeling mag niet in de weg zitten bij het gewone geval.
        Http::fake(['*' => Http::response($this->antwoord('System administrator'))]);

        $this->vertaler()->naarEngels(['role_nl' => 'Systeembeheerder']);

        Http::assertSentCount(1);
    }

    public function test_empty_lines_survive_the_split(): void
    {
        /*
         * Een lege regel scheidt alinea's, en die structuur hoort in het
         * Engels terug te komen. Hij gaat ook niet langs de dienst: dat
         * zou een verzoek kosten voor niets.
         */
        Http::fake(['*' => Http::response($this->antwoord('Piece.'))]);

        $zin = str_repeat('Een zin met genoeg tekens erin om te tellen. ', 12);

        $uitkomst = $this->vertaler()->naarEngels([
            'description_nl' => trim($zin)."\n\n".trim($zin),
        ]);

        $this->assertStringContainsString("\n\n", $uitkomst['description_nl']);
    }

    public function test_a_switched_off_translator_refuses(): void
    {
        Http::fake();

        try {
            (new MyMemoryVertaler(false, 'https://example.test', null, 5))
                ->naarEngels(['role_nl' => 'Beheerder']);
            $this->fail('Er had een VertaalFout moeten komen.');
        } catch (VertaalFout $fout) {
            $this->assertSame(VertaalFout::NIET_INGESTELD, $fout->reden);
        }

        Http::assertNothingSent();
    }
}
