<?php

namespace App\Support\Translation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Vertalen met MyMemory.
 *
 * Dit is de enige klasse in de applicatie die iets van MyMemory weet. De
 * rest praat met het contract Vertaler; zie de toelichting daar.
 *
 * **Waarom deze dienst.** Er is geen account voor nodig en geen sleutel:
 * de API staat gewoon open. Dat was de doorslaggevende eis -- een knop die
 * pas werkt nadat iemand zich ergens heeft aangemeld, is voor de klant
 * geen knop. De kwaliteit haalt het niet bij die van een betaalde dienst,
 * maar dat hoeft ook niet: wat hier uitkomt is een startpunt dat de klant
 * naleest en meestal in eigen woorden herschrijft.
 *
 * **Het tegoed.** Anoniem 5.000 tekens per dag per IP-adres. Zet je een
 * e-mailadres in `TRANSLATE_EMAIL`, dan wordt dat 50.000 -- dat adres gaat
 * als parameter mee en er hoeft niets voor te worden aangemeld. Voor een
 * site van deze omvang is zelfs het anonieme tegoed ruim.
 *
 * **De dienst neemt hoogstens 500 tekens per verzoek.** Daarboven geeft
 * hij geen vertaling maar een foutcode terug -- en dat is precies wat er
 * gebeurde bij een beschrijving van een paar alinea's: korte teksten
 * werkten, lange vielen stil. Lange tekst wordt daarom in stukken geknipt
 * op zinsgrenzen en daarna weer aan elkaar gezet. Zie `stukken()`.
 *
 * **Er gaat één verzoek per stuk uit.** De dienst kent geen bundel. Bij
 * een ervaring blijft dat overzichtelijk; wel is er een bovengrens, zodat
 * een verkeerd verzoek niet ineens tientallen aanroepen wordt.
 *
 * Zie docs/architecture/automatisch-vertalen.md.
 */
class MyMemoryVertaler implements Vertaler
{
    /**
     * Meer dan dit aantal velden in één keer hoort niet voor te komen.
     *
     * Het is geen limiet die de klant tegenkomt maar een noodrem: elke
     * tekst is een apart verzoek, en een fout in de aanroepende code zou
     * er anders tientallen achter elkaar afvuren.
     *
     * **Twaalf, en dat getal komt ergens vandaan.** Het drukste scherm
     * is een dienst: een titel, een korte tekst, een uitgebreide tekst
     * en hoogstens acht expertisepunten, samen elf. Stond dit lager --
     * en het stond op zes -- dan sneed `array_slice` hieronder de rest
     * er stilletjes af, en kreeg de klant de helft van zijn punten
     * onvertaald terug zonder dat er iets misging. Komt er ooit een
     * scherm met meer velden, dan hoort dit getal mee te groeien.
     */
    private const HOOGUIT = 12;

    /**
     * Hoeveel tekens er in één verzoek passen.
     *
     * De dienst staat er 500 toe. We blijven daaronder, want een zin die
     * er net overheen gaat kost een heel verzoek extra -- en de grens
     * telt in bytes, niet in letters, dus een tekst met accenten of een
     * euroteken is langer dan hij eruitziet.
     */
    private const PER_VERZOEK = 440;

    /**
     * De noodrem op het aantal verzoeken voor één veld.
     *
     * Zestien stukken is ruim zevenduizend tekens: meer dan een
     * beschrijving ooit hoort te zijn, en weinig genoeg dat de klant niet
     * een minuut naar een laadscherm zit te kijken.
     */
    private const STUKKEN_HOOGUIT = 16;

    public function __construct(
        private readonly bool $ingeschakeld,
        private readonly string $endpoint,
        private readonly ?string $email,
        private readonly int $timeout,
    ) {}

    public function beschikbaar(): bool
    {
        return $this->ingeschakeld;
    }

    public function naarEngels(array $teksten): array
    {
        if (! $this->beschikbaar()) {
            throw new VertaalFout(VertaalFout::NIET_INGESTELD);
        }

        // Lege velden gaan er niet heen: dat kost tekens van het dagtegoed
        // en levert een lege string terug die we al hadden.
        $tevertalen = array_slice(
            array_filter($teksten, fn (?string $tekst) => filled($tekst)),
            0,
            self::HOOGUIT,
            preserve_keys: true,
        );

        $uitkomst = [];

        foreach ($tevertalen as $veld => $tekst) {
            $uitkomst[$veld] = $this->vertaalLang((string) $tekst);
        }

        return $uitkomst;
    }

    /**
     * Een tekst van willekeurige lengte vertalen.
     *
     * Past hij in één verzoek, dan gaat hij er in één keer heen. Zo niet,
     * dan wordt hij in stukken geknipt en daarna weer aan elkaar gezet.
     */
    private function vertaalLang(string $tekst): string
    {
        $stukken = $this->stukken($tekst);

        if (count($stukken) === 1) {
            return $this->vertaal($stukken[0]);
        }

        $vertaald = [];

        foreach ($stukken as $stuk) {
            // Een lege regel is geen tekst maar een alinea-eind; die hoeft
            // niet langs de vertaaldienst en kost anders een verzoek.
            $vertaald[] = trim($stuk) === '' ? $stuk : $this->vertaal($stuk);
        }

        return implode("\n", $vertaald);
    }

    /**
     * Een tekst opknippen in stukken die door de dienst worden geaccepteerd.
     *
     * De volgorde is niet willekeurig: eerst per **regel**, want een lege
     * regel scheidt alinea's en die structuur wil je terugzien in het
     * Engels. Past een regel niet, dan per **zin**. Past een zin nog
     * steeds niet -- iemand die een halve alinea in één zin schrijft --
     * dan per **woord**, want een half verzoek is beter dan geen.
     *
     * De stukken worden later met een regeleinde aan elkaar gezet. Zinnen
     * die binnen één regel zijn opgeknipt komen daardoor op een eigen
     * regel te staan; dat is een schoonheidsfoutje dat de klant zo
     * wegpoetst, en het alternatief is geen vertaling.
     *
     * @return array<int, string>
     */
    private function stukken(string $tekst): array
    {
        if ($this->past($tekst)) {
            return [$tekst];
        }

        $stukken = [];

        foreach (preg_split('/\R/u', $tekst) ?: [] as $regel) {
            foreach ($this->knip($regel) as $stuk) {
                $stukken[] = $stuk;

                if (count($stukken) >= self::STUKKEN_HOOGUIT) {
                    return $stukken;
                }
            }
        }

        return $stukken === [] ? [$tekst] : $stukken;
    }

    /**
     * Eén regel opknippen tot elk deel past.
     *
     * @return array<int, string>
     */
    private function knip(string $regel): array
    {
        if ($this->past($regel)) {
            return [$regel];
        }

        // Splitsen ná een punt, uitroepteken of vraagteken, met de spatie
        // die erop volgt. De leestekens blijven daardoor staan.
        $delen = preg_split('/(?<=[.!?])\s+/u', $regel) ?: [$regel];

        $stukken = [];
        $huidig = '';

        foreach ($delen as $deel) {
            foreach ($this->losseWoorden($deel) as $brok) {
                $samen = $huidig === '' ? $brok : $huidig.' '.$brok;

                if ($this->past($samen)) {
                    $huidig = $samen;

                    continue;
                }

                if ($huidig !== '') {
                    $stukken[] = $huidig;
                }

                $huidig = $brok;
            }
        }

        if ($huidig !== '') {
            $stukken[] = $huidig;
        }

        return $stukken;
    }

    /**
     * Een zin die zelfs alleen te lang is, opdelen op woordgrenzen.
     *
     * @return array<int, string>
     */
    private function losseWoorden(string $zin): array
    {
        if ($this->past($zin)) {
            return [$zin];
        }

        $stukken = [];
        $huidig = '';

        foreach (preg_split('/\s+/u', $zin) ?: [] as $woord) {
            $samen = $huidig === '' ? $woord : $huidig.' '.$woord;

            if ($this->past($samen)) {
                $huidig = $samen;

                continue;
            }

            if ($huidig !== '') {
                $stukken[] = $huidig;
            }

            // Eén woord dat op zichzelf te lang is, gaat gewoon mee. De
            // dienst weigert het dan, en dat is duidelijker dan het
            // stilletjes halveren.
            $huidig = $woord;
        }

        if ($huidig !== '') {
            $stukken[] = $huidig;
        }

        return $stukken;
    }

    /** Past deze tekst binnen één verzoek? In bytes, want zo telt de dienst. */
    private function past(string $tekst): bool
    {
        return strlen($tekst) <= self::PER_VERZOEK;
    }

    private function vertaal(string $tekst): string
    {
        try {
            $antwoord = Http::timeout($this->timeout)
                // Eén nieuwe poging bij een hapering. Twee zou bij een
                // echte storing alleen maar langer laten wachten op
                // hetzelfde antwoord.
                ->retry(2, 250, throw: false)
                ->acceptJson()
                ->get($this->endpoint, array_filter([
                    'q' => $tekst,
                    'langpair' => 'nl|en',
                    // Het adres verhoogt het dagtegoed van 5.000 naar
                    // 50.000 tekens. Optioneel, en er hoort geen aanmelding
                    // bij.
                    'de' => $this->email,
                ]));
        } catch (ConnectionException) {
            throw new VertaalFout(VertaalFout::GEEN_VERBINDING);
        } catch (Throwable $fout) {
            throw new VertaalFout(VertaalFout::ONBEKEND, $fout->getMessage());
        }

        if ($antwoord->failed()) {
            throw new VertaalFout(VertaalFout::GEEN_VERBINDING);
        }

        /** @var array<string, mixed> $gegevens */
        $gegevens = $antwoord->json() ?? [];

        /*
         * Het dagtegoed is op. Dat komt terug als een 403 in het
         * antwoordveld en niet als een HTTP-fout, dus zonder deze regel
         * zou de klant de melding "YOU USED ALL AVAILABLE FREE
         * TRANSLATIONS FOR TODAY" als vertaling in zijn veld krijgen.
         */
        if (($gegevens['quotaFinished'] ?? false) === true
            || (int) ($gegevens['responseStatus'] ?? 0) === 403) {
            throw new VertaalFout(VertaalFout::TEGOED_OP);
        }

        if ((int) ($gegevens['responseStatus'] ?? 0) !== 200) {
            throw new VertaalFout(
                VertaalFout::ONBEKEND,
                (string) ($gegevens['responseDetails'] ?? ''),
            );
        }

        return $this->besteVertaling($gegevens, $tekst);
    }

    /**
     * De bruikbaarste vertaling uit het antwoord.
     *
     * MyMemory is deels een vertaalgeheugen: naast een machinevertaling
     * krijg je zinnen die eerder door mensen zijn aangeleverd. Dat is soms
     * beter en soms onzin -- een korte term als "Beheer" levert zomaar een
     * zin uit een handleiding op waar iemand hem ooit in had staan.
     *
     * Daarom pakken we de machinevertaling als die erbij zit, en pas
     * daarna wat de dienst zelf als beste aanmerkt. Dat is voorspelbaarder,
     * en voorspelbaar is hier meer waard dan af en toe net iets mooier.
     *
     * @param  array<string, mixed>  $gegevens
     */
    private function besteVertaling(array $gegevens, string $origineel): string
    {
        /** @var array<int, array<string, mixed>> $matches */
        $matches = is_array($gegevens['matches'] ?? null) ? $gegevens['matches'] : [];

        foreach ($matches as $match) {
            if (($match['created-by'] ?? null) === 'MT!' && filled($match['translation'] ?? null)) {
                return $this->schoon((string) $match['translation'], $origineel);
            }
        }

        $tekst = $gegevens['responseData']['translatedText'] ?? null;

        return filled($tekst)
            ? $this->schoon((string) $tekst, $origineel)
            : $origineel;
    }

    /**
     * De dienst geeft HTML-entiteiten terug -- een apostrof komt binnen als
     * `&#39;`. Zonder deze regel staat dat letterlijk op de website.
     */
    private function schoon(string $tekst, string $origineel): string
    {
        $schoon = trim(html_entity_decode($tekst, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $schoon === '' ? $origineel : $schoon;
    }
}
