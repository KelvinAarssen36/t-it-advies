<?php

namespace App\Support\Juridisch;

use App\Models\SecurityEvent;
use Illuminate\Database\Eloquent\Collection;

/**
 * De tekst die de eigenaar terugstuurt aan een bezoeker die om zijn
 * gegevens vraagt.
 *
 * **Hij wordt in allebei de talen tegelijk opgebouwd, en dat is het hele
 * punt van deze klasse.** Eerst stond deze tekst in het Vue-component en
 * liep hij mee met de taal van het portaal. Die taal staat op Nederlands,
 * want zo werkt de eigenaar -- dus kreeg een Engelstalige bezoeker een
 * Nederlands antwoord, tenzij de eigenaar zijn hele portaal omzette om één
 * mail te kunnen sturen.
 *
 * De taal van het antwoord hoort bij de **bezoeker** en niet bij het
 * scherm. Daarom komen ze hier allebei vandaan en kiest het scherm er een.
 *
 * **Er wordt nergens `App::setLocale()` aangeroepen.** Dat mag in dit
 * project alleen in `SetLocale`, zodat geen enkel scherm stiekem zijn
 * eigen taal kiest. Het kan hier ook zonder: `__()` neemt een taal als
 * derde argument, en omdat de Nederlandse zin in dit project zélf de
 * vertaalsleutel is, is "de Nederlandse tekst" en "de sleutel om de
 * Engelse mee op te zoeken" hetzelfde ding. Zie
 * `SecurityEventType::sleutel()`.
 *
 * Zie docs/security/verzoeken-van-bezoekers.md.
 */
class Antwoordtekst
{
    /** De talen waarin het antwoord wordt klaargezet. */
    public const TALEN = ['nl', 'en'];

    /**
     * Hoeveel logboekregels er hoogstens in de tekst worden uitgeschreven.
     *
     * Honderd regels in een mail leest niemand, en het antwoord noemt het
     * totaal er altijd bij. Wil de bezoeker ze allemaal, dan is dat een
     * vervolgvraag.
     */
    private const REGELS_IN_DE_TEKST = 20;

    /**
     * Het antwoord in elke taal, als kaart van taalcode naar tekst.
     *
     * @param  Collection<int, SecurityEvent>  $gebeurtenissen
     * @return array<string, string>
     */
    public function voorElkeTaal(
        ?string $zoekterm,
        Collection $gebeurtenissen,
        int $bewaartermijnBeveiliging,
    ): array {
        $teksten = [];

        foreach (self::TALEN as $taal) {
            $teksten[$taal] = $this->tekst(
                $taal,
                $zoekterm,
                $gebeurtenissen,
                $bewaartermijnBeveiliging,
            );
        }

        return $teksten;
    }

    /**
     * @param  Collection<int, SecurityEvent>  $gebeurtenissen
     */
    private function tekst(
        string $taal,
        ?string $zoekterm,
        Collection $gebeurtenissen,
        int $bewaartermijnBeveiliging,
    ): string {
        $regels = [
            __('Beste,', [], $taal),
            '',
            __('Je vroeg welke gegevens wij van je hebben. Hieronder staat wat we hebben nagekeken.', [], $taal),
            '',
        ];

        /*
         * Nog niet gezocht. Dan is er niets na te kijken, en zou een tekst
         * die "we hebben niets gevonden" zegt een bewering doen die niemand
         * heeft gecontroleerd.
         */
        if ($zoekterm === null) {
            $regels[] = __('Vul hierboven eerst het e-mailadres of IP-adres in waar het verzoek over gaat, dan vult deze tekst zich met wat er gevonden is.', [], $taal);

            return implode("\n", $regels);
        }

        if ($gebeurtenissen->isEmpty()) {
            $regels[] = __('We hebben je niet in onze gegevens kunnen vinden. Er staat niets van je bij ons.', [], $taal);
        } else {
            $regels[] = __('In ons beveiligingslogboek staan :aantal regels die bij jou horen. Dat logboek houdt inlogpogingen en geblokkeerde formulieren bij, met IP-adres, om misbruik tegen te kunnen gaan.', ['aantal' => $gebeurtenissen->count()], $taal);
            $regels[] = '';

            foreach ($gebeurtenissen->take(self::REGELS_IN_DE_TEKST) as $gebeurtenis) {
                $regels[] = $this->logboekregel($gebeurtenis, $taal);
            }

            $regels[] = '';
            $regels[] = __('Die regels verwijderen we niet: het logboek bestaat om misbruik te kunnen zien en tegenhouden, en dat werkt niet als er regels uit te halen zijn. Ze verdwijnen vanzelf na :dagen dagen.', ['dagen' => $bewaartermijnBeveiliging], $taal);
        }

        /*
         * Deze twee alinea's staan er altijd onder, hoe de zoekopdracht ook
         * afliep. De eerste noemt het logbestand van de webserver met
         * zoveel woorden: dat schrijven wij niet zelf, maar het IP-adres
         * van elk verzoek staat erin, en een antwoord dat "wij bewaren geen
         * IP-adressen" zegt zonder die uitzondering is niet waar. Het staat
         * om dezelfde reden in de privacyverklaring.
         */
        $regels[] = '';
        $regels[] = __('Verder bewaren wij zelf geen gegevens over je bezoek aan onze website: daarvoor gebruiken we geen cookies en slaan we je IP-adres niet op. Wel houdt onze webserver, zoals elke webserver, een technisch logbestand bij waarin het IP-adres van elk verzoek staat. Dat hoort bij het beheer en de beveiliging van de server en wordt bewaard door de partij waar die server staat.', [], $taal);
        $regels[] = '';
        $regels[] = __('Heb je ons eerder een bericht via het contactformulier gestuurd, laat dat dan weten -- dat staat in onze mailbox en kunnen we opzoeken en verwijderen.', [], $taal);
        $regels[] = '';
        $regels[] = __('Met vriendelijke groet,', [], $taal);
        $regels[] = '@T IT Advies';

        return implode("\n", $regels);
    }

    /**
     * Eén regel uit het logboek, in de taal van het antwoord.
     *
     * Ook de datum gaat mee: `locale()` op de instantie zelf, zodat de
     * maandafkorting klopt zonder dat de taal van de hele applicatie
     * verschuift. Carbon heeft een eigen taal, los van die van Laravel.
     */
    private function logboekregel(SecurityEvent $gebeurtenis, string $taal): string
    {
        /*
         * `locale()` wordt als losse regel aangeroepen en niet in een
         * ketting. Hij geeft zonder argument de taal terug en mét argument
         * zichzelf, dus in een ketting ziet de statische analyse
         * `Carbon|string` staan en struikelt hij over `isoFormat()`.
         *
         * `copy()` omdat we anders de taal van de instantie uit de
         * database aanpassen, en die gaat daarna nog naar het scherm.
         */
        $moment = $gebeurtenis->created_at->copy();
        $moment->locale($taal);

        $wanneer = $moment->isoFormat('D MMM YYYY, HH:mm');
        $wat = __($gebeurtenis->sleutel(), [], $taal);
        $uitkomst = __($gebeurtenis->outcome->sleutel(), [], $taal);

        $regel = "- {$wanneer} — {$wat} ({$uitkomst})";

        return $gebeurtenis->ip_address === null
            ? $regel
            : "{$regel} — {$gebeurtenis->ip_address}";
    }
}
