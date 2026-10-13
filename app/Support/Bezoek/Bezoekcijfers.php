<?php

namespace App\Support\Bezoek;

use App\Enums\BezoekDimensie;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * De bezoekcijfers zoals het beheerscherm ze nodig heeft.
 *
 * De leeskant van [Bezoekteller]. Hij rekent niets uit wat de database zelf
 * kan: de tellers staan er al in, dus dit is optellen en op een rij zetten.
 *
 * **Drie dingen gebeuren hier en niet in de frontend**, en elk om dezelfde
 * reden -- het is een inhoudelijke keuze en geen opmaak:
 *
 * 1. **De gaten vullen.** Een dag zonder bezoek heeft geen rij. Zou de
 *    frontend alleen de bestaande rijen krijgen, dan tekent de grafiek een
 *    stille week even breed als een drukke dag.
 * 2. **De vorige periode.** "Twaalf procent meer dan de vorige dertig
 *    dagen" is een vergelijking, en welke periode daarvoor telt is een
 *    beslissing.
 * 3. **Delen door nul.** Van nul naar vijf is geen stijging van oneindig
 *    procent maar "geen vergelijking mogelijk". Dat is `null`, en de
 *    frontend laat dan niets zien in plaats van een onzinnig getal.
 *
 * Zie docs/architecture/bezoekcijfers.md.
 */
class Bezoekcijfers
{
    /** De perioden waaruit het scherm kan kiezen, in dagen. */
    public const PERIODEN = [7, 30, 90];

    public const STANDAARD = 30;

    /**
     * Alles wat het scherm voor één periode nodig heeft.
     *
     * @return array{
     *     dagen: int,
     *     reeks: array<int, array{dag: string, bezoekers: int, weergaven: int}>,
     *     totalen: array{bezoekers: int, weergaven: int, berichten: int},
     *     verschil: array{bezoekers: int|null, weergaven: int|null, berichten: int|null},
     *     dimensies: array<string, array<int, array{naam: string, aantal: int}>>,
     *     eerste: string|null,
     * }
     */
    public function overzicht(int $dagen): array
    {
        $dagen = in_array($dagen, self::PERIODEN, true) ? $dagen : self::STANDAARD;

        $vandaag = CarbonImmutable::now(config('site.timezone'))->startOfDay();

        // Vandaag telt mee, dus een periode van zeven dagen begint zes
        // dagen terug. Zonder die min één is het er één te veel.
        $vanaf = $vandaag->subDays($dagen - 1);
        $vorigeVanaf = $vanaf->subDays($dagen);

        $nu = $this->totalen($vanaf, $vandaag);
        $vorige = $this->totalen($vorigeVanaf, $vanaf->subDay());

        return [
            'dagen' => $dagen,
            'reeks' => $this->reeks($vanaf, $vandaag),
            'totalen' => $nu,
            'verschil' => [
                'bezoekers' => $this->verschil($nu['bezoekers'], $vorige['bezoekers']),
                'weergaven' => $this->verschil($nu['weergaven'], $vorige['weergaven']),
                'berichten' => $this->verschil($nu['berichten'], $vorige['berichten']),
            ],
            'dimensies' => $this->dimensies($vanaf, $vandaag),

            /*
             * De eerste dag waarvan we iets weten. Het scherm zegt daarmee
             * "we meten sinds 1 oktober" in plaats van te doen alsof een
             * lege grafiek betekent dat er niemand komt.
             */
            'eerste' => $this->eersteDag(),
        ];
    }

    /**
     * De korte samenvatting voor het blok op het dashboard.
     *
     * **Het grote getal is het totaal van altijd en niet van deze maand.**
     * Dat is met opzet: een dashboardblok dat elke maand op nul begint
     * voelt als iets dat je kwijtraakt, en de dagtotalen worden nergens
     * opgeruimd -- ze gaan over niemand in het bijzonder. Zo is het ook
     * beloofd in de privacyverklaring.
     *
     * Twee vragen tegelijk: wat heeft de site in totaal gedaan, en wat
     * gebeurt er nú. Zonder dat tweede is het een monument in plaats van
     * een dashboard.
     *
     * @return array{
     *     weergavenTotaal: int,
     *     bezoekersVandaag: int,
     *     weergavenVandaag: int,
     *     reeks: array<int, int>,
     *     meet: bool,
     * }
     */
    public function samenvatting(int $dagen = 14): array
    {
        $vandaag = CarbonImmutable::now(config('site.timezone'))->startOfDay();

        /** @var object{weergaven: int|null}|null $totaal */
        $totaal = DB::table('site_day_totals')
            ->selectRaw('SUM(views) as weergaven')
            ->first();

        $reeks = $this->reeks($vandaag->subDays($dagen - 1), $vandaag);
        $laatste = end($reeks);

        return [
            'weergavenTotaal' => (int) ($totaal->weergaven ?? 0),
            'bezoekersVandaag' => (int) ($laatste['bezoekers'] ?? 0),
            'weergavenVandaag' => (int) ($laatste['weergaven'] ?? 0),

            // Alleen de aantallen; het blok is te klein voor datums.
            'reeks' => array_map(
                fn (array $dag) => $dag['weergaven'],
                $reeks,
            ),

            /*
             * Of er al iets gemeten is. Nul weergaven betekent iets anders
             * dan "er komt niemand" wanneer we gisteren pas zijn begonnen,
             * en het blok zegt dat dan ook.
             */
            'meet' => $this->eersteDag() !== null,
        ];
    }

    /**
     * @return array{bezoekers: int, weergaven: int, berichten: int}
     */
    private function totalen(CarbonImmutable $vanaf, CarbonImmutable $tot): array
    {
        /** @var object{bezoekers: int|null, weergaven: int|null, berichten: int|null}|null $rij */
        $rij = DB::table('site_day_totals')
            ->whereBetween('day', [$vanaf->toDateString(), $tot->toDateString()])
            ->selectRaw('SUM(visitors) as bezoekers, SUM(views) as weergaven, SUM(contacts) as berichten')
            ->first();

        return [
            'bezoekers' => (int) ($rij->bezoekers ?? 0),
            'weergaven' => (int) ($rij->weergaven ?? 0),
            'berichten' => (int) ($rij->berichten ?? 0),
        ];
    }

    /**
     * Eén regel per dag in de periode, ook de dagen zonder bezoek.
     *
     * @return array<int, array{dag: string, bezoekers: int, weergaven: int}>
     */
    private function reeks(CarbonImmutable $vanaf, CarbonImmutable $tot): array
    {
        $rijen = DB::table('site_day_totals')
            ->whereBetween('day', [$vanaf->toDateString(), $tot->toDateString()])
            ->get(['day', 'visitors', 'views'])
            ->keyBy(fn (object $rij) => (string) CarbonImmutable::parse($rij->day)->toDateString());

        $reeks = [];
        $dag = $vanaf;

        while ($dag->lessThanOrEqualTo($tot)) {
            $sleutel = $dag->toDateString();
            $rij = $rijen->get($sleutel);

            $reeks[] = [
                'dag' => $sleutel,
                'bezoekers' => (int) ($rij->visitors ?? 0),
                'weergaven' => (int) ($rij->views ?? 0),
            ];

            $dag = $dag->addDay();
        }

        return $reeks;
    }

    /**
     * De uitsplitsingen, grootste eerst.
     *
     * Elke soort krijgt zijn eigen lijst, ook als die leeg is: het scherm
     * hoort niet te hoeven weten welke soorten er bestaan.
     *
     * @return array<string, array<int, array{naam: string, aantal: int}>>
     */
    private function dimensies(CarbonImmutable $vanaf, CarbonImmutable $tot): array
    {
        $uitkomst = [];

        foreach (BezoekDimensie::cases() as $soort) {
            $uitkomst[$soort->value] = DB::table('site_day_dimensions')
                ->whereBetween('day', [$vanaf->toDateString(), $tot->toDateString()])
                ->where('kind', $soort->value)
                ->groupBy('name')
                ->orderByDesc('aantal')
                ->orderBy('name')
                ->selectRaw('name as naam, SUM(views) as aantal')
                ->get()
                ->map(fn (object $rij) => [
                    'naam' => (string) $rij->naam,
                    'aantal' => (int) $rij->aantal,
                ])
                ->all();
        }

        return $uitkomst;
    }

    private function eersteDag(): ?string
    {
        $dag = DB::table('site_day_totals')->min('day');

        return $dag === null
            ? null
            : CarbonImmutable::parse((string) $dag)->toDateString();
    }

    /**
     * Het verschil in procenten, of `null` als er niets te vergelijken is.
     *
     * Was de vorige periode nul, dan is elk percentage onzin -- ook al is
     * het rekenkundig oneindig. Het scherm laat dan niets zien.
     */
    private function verschil(int $nu, int $vorige): ?int
    {
        if ($vorige === 0) {
            return null;
        }

        return (int) round((($nu - $vorige) / $vorige) * 100);
    }
}
