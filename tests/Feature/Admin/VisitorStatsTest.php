<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Bezoek\Bezoekteller;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Het tellen van bezoek aan de publieke site.
 *
 * **Twee van deze tests leggen een juridische belofte vast en niet een
 * gedrag.** `test_no_ip_address_is_ever_stored` en
 * `test_only_the_host_of_a_referrer_is_stored` controleren wat er in de
 * privacyverklaring aan de bezoeker wordt verteld. Gaan die om, dan staat
 * er een onwaarheid op de site -- dat is een ander soort fout dan een
 * verkeerd getal, en daarom staan ze vooraan.
 *
 * Zie docs/architecture/bezoekcijfers.md.
 */
class VisitorStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
    }

    /** Een bezoek zoals een gewone browser het doet. */
    private function bezoek(array $headers = [], string $ip = '198.51.100.10'): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0 Safari/537.36',
                ...$headers,
            ])
            ->get('/')
            ->assertOk();
    }

    private function totalen(): ?object
    {
        return DB::table('site_day_totals')->first();
    }

    /* --- De beloften uit de privacyverklaring ----------------------------- */

    /**
     * Er staat nergens een IP-adres in de database.
     *
     * Dit is de kern van de belofte. De test kijkt niet naar één kolom maar
     * naar **alles** wat er na een bezoek in de bezoektabellen staat: zodra
     * iemand er een kolom bij zet waar per ongeluk een adres in belandt,
     * valt deze test om.
     */
    public function test_no_ip_address_is_ever_stored(): void
    {
        $ip = '198.51.100.77';

        $this->bezoek(ip: $ip);

        $tabellen = [
            'site_day_totals',
            'site_day_dimensions',
            'site_visitor_codes',
            'site_visitor_salts',
        ];

        foreach ($tabellen as $tabel) {
            $inhoud = json_encode(
                DB::table($tabel)->get()->map(fn (object $rij) => (array) $rij)->all(),
            );

            $this->assertIsString($inhoud);
            $this->assertStringNotContainsString($ip, $inhoud, $tabel);
        }
    }

    /** En de code is niet het IP-adres met een laagje eromheen. */
    public function test_the_visitor_code_is_not_the_plain_ip(): void
    {
        $ip = '198.51.100.77';

        $this->bezoek(ip: $ip);

        $code = (string) DB::table('site_visitor_codes')->value('code');

        $this->assertNotSame('', $code);
        $this->assertStringNotContainsString($ip, $code);

        // Ook niet de versie zonder zout, want die zou voor iedereen met
        // dezelfde browser hetzelfde zijn en dus wél te herleiden.
        $this->assertNotSame(hash('sha256', $ip), $code);
    }

    /**
     * Van een verwijzer bewaren we alleen de host.
     *
     * **Dit is geen netheid maar een belofte.** Een verwijzende URL kan
     * zoektermen of zelfs persoonsgegevens in zijn queryparameters hebben
     * staan, en die horen hier niet te belanden.
     */
    public function test_only_the_host_of_a_referrer_is_stored(): void
    {
        $this->bezoek([
            'Referer' => 'https://zoeken.example.org/resultaten?q=erik+aarssen+adres&user=12345',
        ]);

        $namen = DB::table('site_day_dimensions')
            ->where('kind', 'verwijzer')
            ->pluck('name')
            ->all();

        $this->assertSame(['zoeken.example.org'], $namen);

        $inhoud = (string) json_encode($namen);
        $this->assertStringNotContainsString('erik', $inhoud);
        $this->assertStringNotContainsString('12345', $inhoud);
        $this->assertStringNotContainsString('?', $inhoud);
    }

    /** Het zout van gisteren verdwijnt, samen met de codes ervan. */
    public function test_yesterdays_codes_and_salt_are_thrown_away(): void
    {
        $gisteren = now(config('site.timezone'))->subDay()->toDateString();

        DB::table('site_visitor_salts')->insert(['day' => $gisteren, 'salt' => 'oud']);
        DB::table('site_visitor_codes')->insert(['day' => $gisteren, 'code' => str_repeat('a', 64)]);

        $this->bezoek();

        $this->assertSame(0, DB::table('site_visitor_codes')->where('day', $gisteren)->count());
        $this->assertSame(0, DB::table('site_visitor_salts')->where('day', $gisteren)->count());

        // En de telling van gisteren blijft staan: die gaat over niemand.
        $this->assertSame(1, DB::table('site_visitor_codes')->count());
    }

    /** Het opruimcommando doet hetzelfde, voor als er dagen niemand komt. */
    public function test_the_prune_command_clears_old_codes(): void
    {
        $gisteren = now(config('site.timezone'))->subDay()->toDateString();

        DB::table('site_visitor_codes')->insert(['day' => $gisteren, 'code' => str_repeat('b', 64)]);
        DB::table('site_visitor_salts')->insert(['day' => $gisteren, 'salt' => 'oud']);
        DB::table('site_day_totals')->insert(['day' => $gisteren, 'views' => 9, 'visitors' => 4]);

        $this->artisan('bezoek:prune-codes')->assertSuccessful();

        $this->assertSame(0, DB::table('site_visitor_codes')->count());
        $this->assertSame(0, DB::table('site_visitor_salts')->count());

        // De cijfers blijven; alleen wat naar een persoon kon leiden is weg.
        $this->assertSame(9, (int) DB::table('site_day_totals')->value('views'));
    }

    /* --- Wat er geteld wordt --------------------------------------------- */

    public function test_a_visit_is_counted(): void
    {
        $this->bezoek();

        $this->assertSame(1, (int) $this->totalen()?->views);
        $this->assertSame(1, (int) $this->totalen()?->visitors);
    }

    /** Dezelfde bezoeker twee keer: twee weergaven, één bezoeker. */
    public function test_the_same_visitor_counts_once_a_day(): void
    {
        $this->bezoek();
        $this->bezoek();
        $this->bezoek();

        $this->assertSame(3, (int) $this->totalen()?->views);
        $this->assertSame(1, (int) $this->totalen()?->visitors);
    }

    public function test_two_different_visitors_count_twice(): void
    {
        $this->bezoek(ip: '198.51.100.10');
        $this->bezoek(ip: '203.0.113.55');

        $this->assertSame(2, (int) $this->totalen()?->views);
        $this->assertSame(2, (int) $this->totalen()?->visitors);
    }

    /**
     * De eigenaar die zijn eigen site bekijkt is geen bezoeker.
     *
     * Zonder dit stijgt zijn bezoekcijfer zodra hij aan het werk gaat, en
     * dan meet hij zichzelf.
     */
    public function test_a_logged_in_user_is_not_counted(): void
    {
        $gebruiker = User::factory()->create();
        $gebruiker->givePermissionTo('manage portal');

        $this->actingAs($gebruiker);
        $this->bezoek();

        $this->assertNull($this->totalen());
    }

    public function test_a_bot_is_not_counted(): void
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])
            ->get('/')
            ->assertOk();

        $this->assertNull($this->totalen());
    }

    /** Een pagina die de browser vooruit ophaalt is geen bezoek. */
    public function test_a_prefetched_page_is_not_counted(): void
    {
        $this->bezoek(['Sec-Purpose' => 'prefetch;anonymous-client-ip']);

        $this->assertNull($this->totalen());
    }

    public function test_a_request_without_a_browser_is_not_counted(): void
    {
        $this->withHeaders(['User-Agent' => ''])->get('/')->assertOk();

        $this->assertNull($this->totalen());
    }

    /* --- De uitsplitsingen ----------------------------------------------- */

    public function test_a_phone_is_recognised(): void
    {
        $this->bezoek([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
        ]);

        $this->assertSame('mobiel', DB::table('site_day_dimensions')
            ->where('kind', 'apparaat')->value('name'));
    }

    /**
     * En een tablet is geen telefoon.
     *
     * Een iPad zegt "ipad" maar ook "mobile", en een Android-tablet zegt
     * "android" zonder "mobi". Precies andersom dan een telefoon, dus dit
     * is de test die omvalt als de volgorde van de controles wisselt.
     */
    public function test_a_tablet_is_not_a_phone(): void
    {
        $this->bezoek([
            'User-Agent' => 'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
        ]);

        $this->assertSame('tablet', DB::table('site_day_dimensions')
            ->where('kind', 'apparaat')->value('name'));
    }

    public function test_without_a_referrer_a_visit_is_direct(): void
    {
        $this->bezoek();

        $this->assertSame('direct', DB::table('site_day_dimensions')
            ->where('kind', 'verwijzer')->value('name'));
    }

    /** De domeinen van LinkedIn komen in één regel terecht. */
    public function test_linkedin_domains_end_up_together(): void
    {
        $this->bezoek(['Referer' => 'https://www.linkedin.com/feed/'], '198.51.100.1');
        $this->bezoek(['Referer' => 'https://lnkd.in/abc'], '198.51.100.2');

        $rijen = DB::table('site_day_dimensions')
            ->where('kind', 'verwijzer')
            ->get();

        $this->assertCount(1, $rijen);
        $this->assertSame('linkedin', $rijen->first()?->name);
        $this->assertSame(2, (int) $rijen->first()?->views);
    }

    /** Vanaf onze eigen site is geen verwijzing maar hetzelfde bezoek. */
    public function test_our_own_site_is_not_a_referrer(): void
    {
        $this->bezoek(['Referer' => config('app.url').'/']);

        $this->assertSame('direct', DB::table('site_day_dimensions')
            ->where('kind', 'verwijzer')->value('name'));
    }

    /**
     * Verwijzerspam vult de tabel niet.
     *
     * Een oude truc: duizenden verzoeken met een verzonnen `Referer` om je
     * site in andermans statistieken te krijgen. Boven de grens komt alles
     * onder 'overig' en groeit de tabel niet verder.
     */
    public function test_referrer_spam_lands_under_one_row(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            $this->bezoek(
                ['Referer' => "https://spam-{$i}.example.net/pad"],
                "198.51.100.{$i}",
            );
        }

        $rijen = DB::table('site_day_dimensions')->where('kind', 'verwijzer')->count();

        // Veertig eigen regels plus 'overig'; niet vijftig.
        $this->assertLessThanOrEqual(41, $rijen);

        $this->assertSame(10, (int) DB::table('site_day_dimensions')
            ->where('kind', 'verwijzer')
            ->where('name', 'overig')
            ->value('views'));
    }

    public function test_the_language_is_counted(): void
    {
        $this->withSession(['locale' => 'en']);
        $this->bezoek();

        $this->assertSame('en', DB::table('site_day_dimensions')
            ->where('kind', 'taal')->value('name'));
    }

    /* --- Het contactformulier -------------------------------------------- */

    /**
     * Een verstuurd bericht komt bij de dagteller.
     *
     * Het enige cijfer dat zegt of de site zijn wérk doet. Er gaat niets
     * van het bericht zelf in de tellers.
     */
    public function test_a_contact_message_is_counted(): void
    {
        $teller = app(Bezoekteller::class);

        $teller->telContact();
        $teller->telContact();

        $this->assertSame(2, (int) $this->totalen()?->contacts);
        $this->assertSame(0, (int) $this->totalen()?->views);
    }

    /* --- Niet het portaal ------------------------------------------------- */

    public function test_the_portal_is_not_counted(): void
    {
        $gebruiker = User::factory()->create();
        $gebruiker->givePermissionTo('manage portal');

        $this->actingAs($gebruiker)->get(route('dashboard'));

        $this->assertNull($this->totalen());
    }
}
