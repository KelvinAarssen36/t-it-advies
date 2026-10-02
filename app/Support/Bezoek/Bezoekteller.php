<?php

namespace App\Support\Bezoek;

use App\Enums\BezoekDimensie;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Het tellen van een bezoek aan de publieke site.
 *
 * **Hier staat de belofte die in de privacyverklaring aan de bezoeker wordt
 * gedaan. Lees dit voordat je er iets aan verandert.**
 *
 * Er wordt niets op het apparaat van de bezoeker opgeslagen en niets
 * uitgelezen: geen cookie, geen localStorage, geen script dat de browser
 * bevraagt. Daarmee valt dit buiten de cookiewet en is er geen
 * toestemmingsbanner nodig. Zet hier dus nooit iets bij dat wél op het
 * apparaat schrijft -- dan verandert dit onderdeel van karakter en is die
 * banner er alsnog nodig.
 *
 * En er wordt geen IP-adres bewaard. Wat er wordt opgeslagen is een
 * onomkeerbare code uit drie dingen:
 *
 *     sha256( dagzout + ip-adres + browserkenmerk )
 *
 * Het dagzout is een willekeurig geheim dat één dag meegaat. Bij het eerste
 * bezoek na middernacht komt er een nieuw zout en gaan de codes van gisteren
 * weg. Daarmee is de code van gisteren naar niemand meer te herleiden, ook
 * niet door ons: het ingrediënt waarmee je hem zou kunnen narekenen bestaat
 * niet meer.
 *
 * **Waarom dat opruimen aan het zout hangt en niet aan de nachtelijke
 * cron.** Een belofte die afhangt van een cronregel die iemand vergeet, is
 * geen belofte. Nu dwingt het systeem zichzelf: zodra er na middernacht
 * iemand komt, is gisteren weg. De geplande taak `bezoek:prune-codes` is
 * een tweede slot voor het geval er dagen niemand langskomt.
 *
 * **Waarom tellers en geen rij per bezoek.** Een logboek met een rij per
 * bezoek is een tijdlijn van wie wanneer op de site was; tellers zijn dat
 * niet. En het past op gedeelde hosting: deze tabellen groeien met 365
 * rijen per jaar in plaats van met elk bezoek.
 *
 * Zie docs/architecture/bezoekcijfers.md.
 */
class Bezoekteller
{
    /**
     * Browserkenmerken van bots, kleine letters.
     *
     * Niet volledig en dat hoeft ook niet: het doel is dat de grafiek over
     * bezoekers gaat en niet over zoekmachines. Een bot die zich voordoet
     * als een gewone browser tellen we mee, en daar is niets tegen te doen
     * zonder dingen te gaan doen die we juist niet willen.
     *
     * @var array<int, string>
     */
    private const BOTS = [
        'bot', 'crawl', 'spider', 'slurp', 'search', 'fetch', 'monitor',
        'preview', 'headless', 'lighthouse', 'pagespeed', 'curl', 'wget',
        'python-requests', 'go-http-client', 'java/', 'okhttp', 'axios',
        'postman', 'facebookexternalhit', 'whatsapp', 'telegram',
        'embedly', 'quora link preview', 'validator', 'uptime',
    ];

    /**
     * Verwijzers die we onder een eigen naam samenvoegen.
     *
     * De zoekmachines en de sociale netwerken gebruiken meerdere domeinen
     * (`google.nl`, `google.com`, `lnkd.in`), en die horen in één regel te
     * staan. De sleutel is een stuk van de host; de waarde is wat er in de
     * tabel komt.
     *
     * @var array<string, string>
     */
    private const VERWIJZERS = [
        'linkedin' => 'linkedin',
        'lnkd.in' => 'linkedin',
        'google' => 'google',
        'bing' => 'bing',
        'duckduckgo' => 'duckduckgo',
        'yahoo' => 'yahoo',
        'facebook' => 'facebook',
        'instagram' => 'instagram',
        'youtube' => 'youtube',
        'github' => 'github',
    ];

    /**
     * Hoeveel verschillende onbekende verwijzers we per dag bewaren.
     *
     * **Dit is een slot tegen verwijzerspam**, een oude truc waarbij iemand
     * duizenden verzoeken stuurt met een verzonnen `Referer` om zijn site
     * in jouw statistieken te krijgen. Zonder deze grens zou dat de tabel
     * volschrijven en het scherm onleesbaar maken. Wat erboven komt wordt
     * 'overig'.
     */
    private const VERWIJZERS_PER_DAG = 40;

    /** Alles wat niet herkend wordt, en alles boven de grens hierboven. */
    private const OVERIG = 'overig';

    /** Rechtstreeks, zonder verwijzer -- of vanaf onze eigen site. */
    private const DIRECT = 'direct';

    /**
     * Eén bezoek tellen.
     *
     * Hij doet niets en klaagt niet als er iets niet klopt. Dat is met
     * opzet: dit draait in het verzoek van een bezoeker, en een teller die
     * de landingspagina kan laten omvallen is erger dan een teller die een
     * bezoek mist.
     */
    public function tel(Request $request): void
    {
        if (! $this->telMee($request)) {
            return;
        }

        $dag = $this->vandaag();

        $this->verhoogOfVoegToe('site_day_totals', ['day' => $dag], 'views');

        if ($this->nieuweBezoeker($request, $dag)) {
            $this->verhoogOfVoegToe('site_day_totals', ['day' => $dag], 'visitors');
        }

        $this->telDimensie($dag, BezoekDimensie::Apparaat, $this->apparaat($request));
        $this->telDimensie($dag, BezoekDimensie::Taal, $this->taal());
        $this->telVerwijzer($dag, $this->verwijzer($request));
    }

    /**
     * Een verstuurd bericht uit het contactformulier tellen.
     *
     * Dit is het enige cijfer dat zegt of de site zijn wérk doet in plaats
     * van alleen bekeken te worden. Er gaat niets van het bericht zelf in
     * de tellers -- alleen dat er er één was.
     */
    public function telContact(): void
    {
        $this->verhoogOfVoegToe(
            'site_day_totals',
            ['day' => $this->vandaag()],
            'contacts',
        );
    }

    /** De dag in de tijdzone van het bedrijf; zie config/site.php. */
    public function vandaag(): string
    {
        return CarbonImmutable::now(config('site.timezone'))->toDateString();
    }

    /* --- Wat we wel en niet tellen ---------------------------------------- */

    /**
     * Of dit verzoek een bezoek van een bezoeker is.
     *
     * Vijf gevallen vallen eruit, en elk om een eigen reden:
     *
     * 1. **Geen GET.** Een POST is een handeling en geen bezoek.
     * 2. **Ingelogd.** De eigenaar die zijn eigen site bekijkt is geen
     *    bezoeker; anders stijgt zijn cijfer zodra hij aan het werk gaat.
     * 3. **Een bot.** Zie BOTS.
     * 4. **Vooruit geladen.** Browsers halen soms een pagina op die je nog
     *    niet hebt geopend. Dat is geen bezoek, en het staat in een kop.
     * 5. **Zonder browserkenmerk.** Dat is geen browser.
     */
    private function telMee(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($request->user() !== null) {
            return false;
        }

        $browser = (string) $request->userAgent();

        if (trim($browser) === '') {
            return false;
        }

        if ($this->isBot($browser)) {
            return false;
        }

        return ! $this->isVooruitGeladen($request);
    }

    private function isBot(string $browser): bool
    {
        $browser = mb_strtolower($browser);

        foreach (self::BOTS as $kenmerk) {
            if (str_contains($browser, $kenmerk)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Of de browser deze pagina vooruit ophaalt zonder dat iemand kijkt.
     *
     * Drie koppen, want ze zijn in de loop der jaren drie keer anders
     * genoemd en alle drie komen ze nog voor.
     */
    private function isVooruitGeladen(Request $request): bool
    {
        if (str_contains((string) $request->header('Sec-Purpose'), 'prefetch')) {
            return true;
        }

        return in_array(
            mb_strtolower((string) $request->header('Purpose', $request->header('X-Purpose', ''))),
            ['prefetch', 'preview'],
            true,
        );
    }

    /* --- De bezoeker, zonder hem te onthouden ----------------------------- */

    /**
     * Of deze bezoeker vandaag nog niet geteld was.
     *
     * `insertOrIgnore` doet het werk in één statement: botst de code met
     * een bestaande, dan komt er niets bij en krijgen we 0 terug. Dat is
     * belangrijker dan het lijkt -- twee bezoekers tegelijk mogen elkaar
     * niet in de weg zitten, en dit kan niet fout gaan omdat de database
     * de unieke sleutel bewaakt en niet wij.
     */
    private function nieuweBezoeker(Request $request, string $dag): bool
    {
        $code = hash('sha256', implode('|', [
            $this->zout($dag),
            (string) $request->ip(),

            /*
             * Het browserkenmerk hoort erbij omdat twee mensen achter
             * hetzelfde kantoornetwerk anders één bezoeker zijn. Het wordt
             * niet opgeslagen; het gaat alleen de hash in.
             */
            (string) $request->userAgent(),
        ]));

        return DB::table('site_visitor_codes')->insertOrIgnore([
            'day' => $dag,
            'code' => $code,
        ]) === 1;
    }

    /**
     * Het zout van vandaag, en het opruimen van gisteren.
     *
     * Die twee staan hier samen en dat is het hele punt: er kan geen zout
     * voor een nieuwe dag ontstaan zonder dat de codes van de vorige dagen
     * in dezelfde beweging verdwijnen.
     *
     * De laatste regel leest het zout opnieuw uit de database in plaats van
     * de eigen waarde te gebruiken. Dat is voor de race waarin twee
     * bezoekers tegelijk de eerste van de dag zijn: dan wint er één met
     * `insertOrIgnore`, en moeten ze allebei met hetzelfde zout rekenen --
     * anders is die ene bezoeker er twee.
     */
    private function zout(string $dag): string
    {
        $bestaand = DB::table('site_visitor_salts')->where('day', $dag)->value('salt');

        if (is_string($bestaand) && $bestaand !== '') {
            return $bestaand;
        }

        DB::table('site_visitor_salts')->insertOrIgnore([
            'day' => $dag,
            'salt' => bin2hex(random_bytes(32)),
        ]);

        DB::table('site_visitor_codes')->where('day', '<', $dag)->delete();
        DB::table('site_visitor_salts')->where('day', '<', $dag)->delete();

        return (string) DB::table('site_visitor_salts')->where('day', $dag)->value('salt');
    }

    /* --- De uitsplitsingen ------------------------------------------------ */

    private function apparaat(Request $request): string
    {
        $browser = mb_strtolower((string) $request->userAgent());

        /*
         * Tablet eerst. Een iPad zegt "ipad" maar ook "mobile", en een
         * Android-tablet zegt "android" zonder "mobi" -- precies andersom
         * dan een telefoon. Zou mobiel eerst komen, dan was elke tablet
         * een telefoon.
         */
        if (preg_match('/ipad|tablet|playbook|silk|kindle|android(?!.*mobi)/', $browser) === 1) {
            return 'tablet';
        }

        if (preg_match('/mobi|iphone|ipod|android|blackberry|iemobile|opera mini/', $browser) === 1) {
            return 'mobiel';
        }

        return 'desktop';
    }

    /** De taal waarin de pagina is opgediend; zie SetLocale. */
    private function taal(): string
    {
        return app()->getLocale() === 'en' ? 'en' : 'nl';
    }

    /**
     * De verwijzer, teruggebracht tot één woord.
     *
     * **Alleen de host, en de rest gaat weg vóórdat er iets is
     * opgeslagen.** Dat is geen netheid: een verwijzende URL kan
     * zoektermen of zelfs persoonsgegevens in zijn queryparameters hebben
     * staan, en die horen hier niet te belanden. `parse_url` met
     * `PHP_URL_HOST` gooit al het andere weg.
     */
    private function verwijzer(Request $request): string
    {
        $referer = (string) $request->header('Referer');

        if ($referer === '') {
            return self::DIRECT;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return self::DIRECT;
        }

        $host = mb_strtolower($host);
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        // Vanaf onze eigen site is geen verwijzing maar hetzelfde bezoek --
        // een taalwisseling bijvoorbeeld.
        if ($host === mb_strtolower((string) $request->getHost())) {
            return self::DIRECT;
        }

        foreach (self::VERWIJZERS as $kenmerk => $naam) {
            if (str_contains($host, $kenmerk)) {
                return $naam;
            }
        }

        return mb_substr($host, 0, 100);
    }

    /**
     * De verwijzer tellen, met het slot tegen verwijzerspam ervoor.
     *
     * De grens wordt alleen geteld als deze verwijzer vandaag nog niet
     * voorkwam -- dat is de enige keer dat hij een rij zou toevoegen. Bij
     * een bekende verwijzer kost dit dus geen extra vraag aan de database.
     */
    private function telVerwijzer(string $dag, string $naam): void
    {
        $sleutels = [
            'day' => $dag,
            'kind' => BezoekDimensie::Verwijzer->value,
            'name' => $naam,
        ];

        if ($this->verhoog('site_day_dimensions', $sleutels, 'views')) {
            return;
        }

        $bekend = in_array($naam, self::VERWIJZERS, true)
            || $naam === self::DIRECT
            || $naam === self::OVERIG;

        if (! $bekend && $this->aantalVerwijzers($dag) >= self::VERWIJZERS_PER_DAG) {
            $this->telDimensie($dag, BezoekDimensie::Verwijzer, self::OVERIG);

            return;
        }

        $this->voegToe('site_day_dimensions', $sleutels, 'views');
    }

    private function aantalVerwijzers(string $dag): int
    {
        return DB::table('site_day_dimensions')
            ->where('day', $dag)
            ->where('kind', BezoekDimensie::Verwijzer->value)
            ->count();
    }

    private function telDimensie(string $dag, BezoekDimensie $soort, string $naam): void
    {
        $this->verhoogOfVoegToe('site_day_dimensions', [
            'day' => $dag,
            'kind' => $soort->value,
            'name' => $naam,
        ], 'views');
    }

    /* --- Tellen zonder botsingen ------------------------------------------ */

    /**
     * Eén bij een teller optellen, en de rij aanmaken als hij nog niet
     * bestaat.
     *
     * **Niet met `upsert()` en niet met platte SQL.** Dat eerste kan geen
     * `teller + 1` uitdrukken; dat tweede verschilt tussen MySQL en de
     * SQLite waarop de tests draaien, en dan test je iets anders dan er in
     * productie gebeurt.
     *
     * Dit doet het in twee stappen die allebei op zichzelf veilig zijn: een
     * `UPDATE ... SET teller = teller + 1`, en alleen als die niets raakte
     * een `insertOrIgnore`. Raakt die insert ook niets -- want iemand anders
     * was net sneller -- dan volgt er nog een update. In het gewone geval is
     * het één statement.
     *
     * @param  array<string, string>  $sleutels
     */
    private function verhoogOfVoegToe(string $tabel, array $sleutels, string $kolom): void
    {
        if ($this->verhoog($tabel, $sleutels, $kolom)) {
            return;
        }

        $this->voegToe($tabel, $sleutels, $kolom);
    }

    /**
     * Alleen optellen bij een rij die al bestaat, en zeggen of dat lukte.
     *
     * Hij voegt met opzet niets toe: `telVerwijzer` heeft juist nodig te
     * weten dát de rij nog niet bestond, om vóór het aanmaken de grens
     * tegen verwijzerspam te kunnen nakijken.
     *
     * @param  array<string, string>  $sleutels
     * @return bool Of er een bestaande rij is bijgewerkt.
     */
    private function verhoog(string $tabel, array $sleutels, string $kolom): bool
    {
        return DB::table($tabel)->where($sleutels)->increment($kolom) > 0;
    }

    /**
     * @param  array<string, string>  $sleutels
     */
    private function voegToe(string $tabel, array $sleutels, string $kolom): void
    {
        $nu = now();

        $toegevoegd = DB::table($tabel)->insertOrIgnore([
            ...$sleutels,
            $kolom => 1,
            'created_at' => $nu,
            'updated_at' => $nu,
        ]);

        // Iemand anders was sneller met dezelfde rij; dan gewoon optellen.
        if ($toegevoegd === 0) {
            DB::table($tabel)->where($sleutels)->increment($kolom);
        }
    }
}
