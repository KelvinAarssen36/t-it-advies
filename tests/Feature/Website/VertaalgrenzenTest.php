<?php

namespace Tests\Feature\Website;

use App\Http\Requests\Website\AboutPointRequest;
use App\Http\Requests\Website\ProjectRequest;
use App\Models\User;
use App\Support\Translation\Vertaler;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;
use Tests\TestCase;

/**
 * De lengtes in de witte lijst van de vertaalroute.
 *
 * **Deze test bestaat door drie fouten van dezelfde soort.** De
 * vertaalroute deelt velden tussen modules: `title_nl` doet de kop van een
 * blok én het onderwerp van de bevestigingsmail, `punt_nl` doet een
 * expertisepunt én een punt bij "Over mij", `result_nl` doet een stap in de
 * werkwijze én een project. De grens in die lijst moet dus **de ruimste van
 * zijn gebruikers** zijn; de controller zegt dat zelf ook met zoveel
 * woorden.
 *
 * Bij drie velden was dat niet zo, met steeds dezelfde uitkomst: vult de
 * eigenaar iets in dat voor zíjn module geldig is maar langer dan de
 * krapste gebruiker toestaat, dan valt **het hele verzoek** om. Niet dat
 * ene veld -- alles. Hij krijgt dan alleen "het vertalen is niet gelukt",
 * en de titel die er wél in paste komt ook niet terug.
 *
 * Wat hier wordt bewaakt is dus niet of het vertalen werkt, maar of een
 * tekst die een módule accepteert ook door de vertaalroute komt.
 *
 * Zie docs/architecture/automatisch-vertalen.md.
 */
class VertaalgrenzenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->app->instance(Vertaler::class, new class implements Vertaler
        {
            public function beschikbaar(): bool
            {
                return true;
            }

            /**
             * @param  array<string, string|null>  $teksten
             * @return array<string, string>
             */
            public function naarEngels(array $teksten): array
            {
                $gevuld = array_filter($teksten, fn (?string $tekst) => filled($tekst));

                return array_map(fn (string $tekst) => 'EN: '.$tekst, $gevuld);
            }
        });
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $velden
     */
    private function vertaal(array $velden): TestResponse
    {
        return $this->actingAs($this->beheerder())
            ->post(route('website.vertalen'), $velden);
    }

    /**
     * Het getal achter `max:` uit een regelrij.
     *
     * @param  array<int, mixed>  $regels
     */
    private function maxUit(array $regels): int
    {
        foreach ($regels as $regel) {
            if (is_string($regel) && str_starts_with($regel, 'max:')) {
                return (int) substr($regel, 4);
            }
        }

        $this->fail('Geen max-regel gevonden; de validatie is van vorm veranderd.');
    }

    /**
     * Het onderwerp van de bevestigingsmail mag 160 tekens.
     *
     * ContactMailDialoog stuurt dat als `title_nl`, en die stond op 120.
     */
    public function test_a_mail_subject_of_its_full_length_gets_through(): void
    {
        $this->vertaal([
            'title_nl' => str_repeat('a', 160),
            'body_nl' => 'De tekst van de mail.',
        ])->assertSessionHasNoErrors();

        /** @var array<string, mixed> $vertaling */
        $vertaling = session(SessionKey::FLASH_DATA)['vertaling'] ?? [];

        $this->assertArrayHasKey(
            'title_en',
            $vertaling,
            'Een onderwerp van 160 tekens liet het hele verzoek omvallen.',
        );

        // En de tekst eronder komt dan ook terug; dat is het echte gevolg.
        $this->assertArrayHasKey('body_en', $vertaling);
    }

    /**
     * Een punt bij "Over mij" mag 80 tekens.
     *
     * OverMijPuntDialoog stuurt dat als `punt_nl`, en die stond op 60 --
     * de maat van een expertisepunt onder een dienst.
     */
    public function test_an_about_point_of_its_full_length_gets_through(): void
    {
        /*
         * De grens uit de module zelf lezen en niet overtypen. Verzet
         * iemand hem daar, dan moet de vertaalroute meebewegen -- en dat
         * is precies wat deze test hoort te bewaken.
         */
        $lengte = $this->maxUit((new AboutPointRequest)->rules()['text_nl']);

        $this->vertaal(['punt_nl' => str_repeat('a', $lengte)])
            ->assertSessionHasNoErrors();

        /** @var array<string, mixed> $vertaling */
        $vertaling = session(SessionKey::FLASH_DATA)['vertaling'] ?? [];

        $this->assertArrayHasKey('punt_en', $vertaling);
    }

    /**
     * Het resultaat van een project mag 4000 tekens.
     *
     * ProjectDialoog stuurt dat als `result_nl`, en die stond op 160 -- de
     * maat van een stap in de werkwijze.
     */
    public function test_a_project_result_of_its_full_length_gets_through(): void
    {
        $this->vertaal([
            'title_nl' => 'Een project',
            'result_nl' => str_repeat('a', ProjectRequest::TEKST_MAX),
        ])->assertSessionHasNoErrors();

        /** @var array<string, mixed> $vertaling */
        $vertaling = session(SessionKey::FLASH_DATA)['vertaling'] ?? [];

        $this->assertArrayHasKey(
            'result_en',
            $vertaling,
            'Een resultaat van 4000 tekens liet het hele verzoek omvallen.',
        );

        $this->assertArrayHasKey('title_en', $vertaling);
    }
}
