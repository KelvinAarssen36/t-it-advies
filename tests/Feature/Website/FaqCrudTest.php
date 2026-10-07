<?php

namespace Tests\Feature\Website;

use App\Http\Requests\Website\FaqItemRequest;
use App\Models\ActivityEntry;
use App\Models\FaqItem;
use App\Models\User;
use App\Support\Translation\Vertaler;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De veelgestelde vragen: aanmaken, wijzigen, verwijderen.
 *
 * Volgt de tabel uit docs/development/testen.md. Het bijzondere geval zit
 * in de twee lengtes: een vraag moet op één regel passen en een antwoord
 * mag alinea's hebben, en die twee grenzen liggen ver uit elkaar.
 *
 * Zie docs/architecture/modules/faq.md.
 */
class FaqCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $anders
     * @return array<string, mixed>
     */
    private function invoer(array $anders = []): array
    {
        return [
            'question_nl' => 'Wat kost een migratie?',
            'question_en' => 'What does a migration cost?',
            'answer_nl' => 'Dat hangt af van je omvang. Een eerste indicatie geef ik in het gesprek.',
            'answer_en' => 'That depends on your size. I give a first indication during the call.',
            'published' => true,
            ...$anders,
        ];
    }

    /**
     * @param  array<string, mixed>  $anders
     */
    private function maak(array $anders = []): TestResponse
    {
        return $this->actingAs($this->beheerder())
            ->post(route('website.faq.store'), $this->invoer($anders));
    }

    /* --- Rechten ----------------------------------------------------------- */

    public function test_every_route_needs_the_permission(): void
    {
        $vraag = FaqItem::factory()->create();
        $gebruiker = User::factory()->create();

        $this->actingAs($gebruiker)->get(route('website.faq.index'))->assertForbidden();
        $this->actingAs($gebruiker)->post(route('website.faq.store'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.faq.update', $vraag))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.faq.kop'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.faq.volgorde'))->assertForbidden();
        $this->actingAs($gebruiker)->patch(route('website.faq.online', $vraag))->assertForbidden();
        $this->actingAs($gebruiker)->delete(route('website.faq.destroy', $vraag))->assertForbidden();

        $this->assertSame(1, FaqItem::query()->count());
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('website.faq.index'))->assertRedirect(route('login'));
    }

    /* --- Het scherm -------------------------------------------------------- */

    public function test_the_screen_lists_the_questions(): void
    {
        FaqItem::factory()->count(3)->create();

        $this->actingAs($this->beheerder())
            ->get(route('website.faq.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/Faq')
                ->has('items', 3)
                ->where('leeg', false)
                ->where('opties.antwoordMax', FaqItemRequest::ANTWOORD_MAX)
                ->where('kanVertalen', false)
                ->etc());
    }

    /**
     * Zonder vragen zegt het scherm dat het leeg is.
     *
     * Die vlag hangt aan het uitroepteken op de indelingspagina: het
     * onderdeel staat aan maar is leeg, dus het staat niet op de website.
     */
    public function test_an_empty_screen_says_so(): void
    {
        $this->actingAs($this->beheerder())
            ->get(route('website.faq.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('leeg', true)
                ->has('items', 0)
                ->etc());
    }

    /**
     * De grens van het antwoord komt van de server.
     *
     * Hetzelfde getal staat in het bewerkvenster, in de teller onder het
     * veld. Zou het daar apart staan, dan krijgt de eigenaar een
     * foutmelding op iets wat dat venster net nog goedkeurde.
     */
    public function test_the_answer_limit_comes_from_one_place(): void
    {
        $venster = (string) file_get_contents(
            resource_path('js/components/website/FaqDialoog.vue'),
        );

        $this->assertStringContainsString('props.opties.antwoordMax', $venster);
        $this->assertStringNotContainsString(
            (string) FaqItemRequest::ANTWOORD_MAX,
            $venster,
            'De grens van het antwoord staat als getal in het venster; hij hoort uit opties.antwoordMax te komen.',
        );
    }

    /* --- Aanmaken ---------------------------------------------------------- */

    public function test_a_question_is_created_in_both_languages(): void
    {
        $this->maak()->assertSessionHasNoErrors();

        $vraag = FaqItem::query()->sole();

        $this->assertSame('Wat kost een migratie?', $vraag->question_nl);
        $this->assertSame('What does a migration cost?', $vraag->question_en);
        $this->assertStringStartsWith('Dat hangt af', $vraag->answer_nl);
        $this->assertStringStartsWith('That depends', (string) $vraag->answer_en);
        $this->assertTrue($vraag->published);
    }

    /** Een nieuwe vraag komt achteraan; waar hij hoort bepaalt de eigenaar. */
    public function test_a_new_question_goes_last(): void
    {
        FaqItem::factory()->create(['position' => 7]);

        $this->maak();

        $this->assertSame(
            8,
            (int) FaqItem::query()->orderByDesc('id')->value('position'),
        );
    }

    /** Een leeg Engels veld wordt `null` en geen lege string. */
    public function test_an_empty_english_field_becomes_null(): void
    {
        $this->maak(['question_en' => '', 'answer_en' => '  ']);

        $vraag = FaqItem::query()->sole();

        $this->assertNull($vraag->question_en);
        $this->assertNull($vraag->answer_en);
    }

    /**
     * De witregels in een antwoord blijven staan.
     *
     * Dat zijn de alinea's: `brand-faq-tekst` zet ze op de website om met
     * `white-space: pre-line`. Zou het antwoord binnenin getrimd worden,
     * dan is het op de site één lange lap tekst.
     */
    public function test_blank_lines_inside_an_answer_survive(): void
    {
        $this->maak(['answer_nl' => "  Eerste alinea.\n\nTweede alinea.  "]);

        $this->assertSame(
            "Eerste alinea.\n\nTweede alinea.",
            FaqItem::query()->sole()->answer_nl,
        );
    }

    public function test_the_question_and_the_answer_are_required(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.faq.store'), ['question_nl' => '', 'answer_nl' => ''])
            ->assertSessionHasErrors(['question_nl', 'answer_nl']);

        $this->assertSame(0, FaqItem::query()->count());
    }

    /**
     * Een vraag die niet op één regel past wordt geweigerd.
     *
     * En met een eigen melding: de standaardtekst zegt alleen "mag niet
     * meer dan 160 tekens bevatten", niet waaróm.
     */
    public function test_a_question_that_is_too_long_is_refused(): void
    {
        $this->maak(['question_nl' => str_repeat('a', 161)])
            ->assertSessionHasErrors('question_nl');

        $this->assertSame(0, FaqItem::query()->count());
    }

    public function test_an_answer_that_is_too_long_is_refused(): void
    {
        $this->maak(['answer_nl' => str_repeat('a', FaqItemRequest::ANTWOORD_MAX + 1)])
            ->assertSessionHasErrors('answer_nl');

        $this->assertSame(0, FaqItem::query()->count());
    }

    /** En precies op de grens mag wel. */
    public function test_an_answer_exactly_on_the_limit_is_allowed(): void
    {
        $this->maak(['answer_nl' => str_repeat('a', FaqItemRequest::ANTWOORD_MAX)])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, FaqItem::query()->count());
    }

    /* --- De vertaalknop ---------------------------------------------------- */

    /**
     * De twee velden komen terug onder de namen die het venster verwacht.
     *
     * **Dit is de reden dat deze test bestaat.** De vertaalknop stuurt
     * `question_nl` en `answer_nl` naar één gedeeld eindpunt, en dat
     * eindpunt maakt er `_en` van. Luistert het venster naar andere namen,
     * dan lijkt die knop gewoon niets te doen -- geen foutmelding, geen
     * melding, niets. Dat merk je niet in een typecheck en niet in een
     * ander testbestand.
     */
    public function test_the_translation_comes_back_under_the_expected_names(): void
    {
        $this->app->instance(Vertaler::class, new class implements Vertaler
        {
            public function beschikbaar(): bool
            {
                return true;
            }

            public function naarEngels(array $teksten): array
            {
                $gevuld = array_filter($teksten, fn (?string $tekst) => filled($tekst));

                return array_map(fn (string $tekst) => 'EN: '.$tekst, $gevuld);
            }
        });

        $this->actingAs($this->beheerder())
            ->from(route('website.faq.index'))
            ->post(route('website.vertalen'), [
                'question_nl' => 'Wat kost een migratie?',
                'answer_nl' => 'Dat hangt af van je omvang.',
            ])
            ->assertRedirect(route('website.faq.index'));

        $this->assertSame([
            'question_en' => 'EN: Wat kost een migratie?',
            'answer_en' => 'EN: Dat hangt af van je omvang.',
        ], session(SessionKey::FLASH_DATA)['vertaling'] ?? null);
    }

    /** En het venster luistert ook echt naar die twee namen. */
    public function test_the_dialog_listens_for_those_names(): void
    {
        $venster = (string) file_get_contents(
            resource_path('js/components/website/FaqDialoog.vue'),
        );

        $this->assertStringContainsString('velden.question_en', $venster);
        $this->assertStringContainsString('velden.answer_en', $venster);
    }

    /* --- Wijzigen ---------------------------------------------------------- */

    public function test_a_question_can_be_changed(): void
    {
        $vraag = FaqItem::factory()->create();

        $this->actingAs($this->beheerder())
            ->put(route('website.faq.update', $vraag), $this->invoer())
            ->assertSessionHasNoErrors();

        $this->assertSame('Wat kost een migratie?', $vraag->fresh()->question_nl);
    }

    /**
     * Het merkje "automatisch vertaald" hangt aan de tekst.
     *
     * Het zegt dat het Engels dat er nú staat van de vertaaldienst komt en
     * nog door niemand is nagelezen -- niet dat er een keer op die knop is
     * gedrukt.
     */
    public function test_the_machine_translated_mark_follows_the_text(): void
    {
        $this->maak(['machine_translated' => true]);

        $vraag = FaqItem::query()->sole();

        $this->assertNotNull($vraag->machine_translated_at);

        $this->actingAs($this->beheerder())->put(
            route('website.faq.update', $vraag),
            $this->invoer(['machine_translated' => false]),
        );

        $this->assertNull($vraag->fresh()->machine_translated_at);
    }

    /* --- Verwijderen ------------------------------------------------------- */

    public function test_a_question_can_be_deleted(): void
    {
        $vraag = FaqItem::factory()->create();

        $this->actingAs($this->beheerder())
            ->delete(route('website.faq.destroy', $vraag))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, FaqItem::query()->count());
    }

    /* --- Het activiteitenlogboek ------------------------------------------- */

    /**
     * Aanmaken, wijzigen en verwijderen komen in het logboek.
     *
     * Dit is inhoud van de eigenaar, geen gegeven van een bezoeker -- dus
     * hier hoort `LogsActivity` wél op het model, anders dan bij een
     * contactaanvraag.
     */
    public function test_the_activity_log_records_the_three_actions(): void
    {
        $this->maak();

        $vraag = FaqItem::query()->sole();

        $this->actingAs($this->beheerder())->put(
            route('website.faq.update', $vraag),
            $this->invoer(['question_nl' => 'Wat kost een verhuizing?']),
        );

        $this->actingAs($this->beheerder())
            ->delete(route('website.faq.destroy', $vraag));

        $this->assertSame(
            3,
            ActivityEntry::query()->where('subject_type', FaqItem::class)->count(),
        );
    }
}
