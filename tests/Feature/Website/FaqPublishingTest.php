<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\FaqItem;
use App\Models\PageSection;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Wat er van de vragen op de website terechtkomt.
 *
 * Drie dingen die hier vastliggen en die je makkelijk omgooit:
 *
 * 1. Een vraag die offline staat gaat niet mee, en staat er niets online,
 *    dan verdwijnt het hele blok.
 * 2. Het Engels valt **allebei** terug op het Nederlands -- anders dan bij
 *    een expertisepunt onder een dienst, dat juist wegvalt.
 * 3. **Álle vragen gaan mee naar de pagina, ook die van bladzijde twee.**
 *    Het blok bladert per zes, maar deze pagina wordt op de server
 *    gerenderd: wat niet wordt meegestuurd staat niet in de HTML die een
 *    zoekmachine krijgt. Bij een vragenlijst is dat precies de inhoud
 *    waarop gezocht wordt.
 *
 * Zie docs/architecture/modules/faq.md.
 */
class FaqPublishingTest extends TestCase
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

    /** Een Engelse bezoeker, zoals `SetLocale` die ziet. */
    private function engels(): static
    {
        $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9']);

        return $this;
    }

    /* --- Online en offline ------------------------------------------------- */

    public function test_the_switch_puts_a_question_online_and_offline(): void
    {
        $vraag = FaqItem::factory()->offline()->create();

        $this->actingAs($this->beheerder())
            ->patch(route('website.faq.online', $vraag), ['published' => true])
            ->assertSessionHasNoErrors();

        $this->assertTrue($vraag->fresh()->published);

        $this->actingAs($this->beheerder())
            ->patch(route('website.faq.online', $vraag), ['published' => false]);

        $this->assertFalse($vraag->fresh()->published);
    }

    public function test_what_is_offline_is_not_on_the_website(): void
    {
        FaqItem::factory()->create(['question_nl' => 'Staat er wel']);
        FaqItem::factory()->offline()->create(['question_nl' => 'Staat er niet']);

        $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('faq', 1)
            ->where('faq.0.vraag', 'Staat er wel')
            ->etc());
    }

    /**
     * Zonder vragen verdwijnt het hele blok.
     *
     * Een kop met niets eronder is slordiger dan geen kop; dat regelt de
     * teller in AppServiceProvider.
     */
    public function test_the_whole_section_disappears_when_it_is_empty(): void
    {
        $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where(
                'sections',
                fn ($secties) => ! $secties->contains(PageSectionKey::Faq->value),
            )
            ->where('faqHeading', null)
            ->etc());
    }

    public function test_and_comes_back_as_soon_as_there_is_one(): void
    {
        FaqItem::factory()->create();

        $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where(
                'sections',
                fn ($secties) => $secties->contains(PageSectionKey::Faq->value),
            )
            ->has('faqHeading')
            ->etc());
    }

    /** Zet de eigenaar het onderdeel uit, dan gaan de vragen ook niet mee. */
    public function test_a_switched_off_section_sends_no_questions(): void
    {
        FaqItem::factory()->count(3)->create();

        PageSection::query()
            ->where('key', PageSectionKey::Faq)
            ->update(['visible' => false]);

        $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('faq', 0)
            ->where('faqHeading', null)
            ->etc());
    }

    /* --- Alles in de pagina, ook buiten de eerste bladzijde ---------------- */

    /**
     * Veertien vragen gaan alle veertien mee.
     *
     * Het blok toont er zes, maar het bladeren gebeurt in de browser. Zou
     * de server alleen de eerste zes sturen, dan bestaat de rest niet voor
     * een zoekmachine -- en dan is het bladeren een SEO-fout in plaats van
     * een ontwerpkeuze.
     */
    public function test_every_question_reaches_the_page(): void
    {
        FaqItem::factory()->count(14)->create();

        $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('faq', 14)
            ->etc());
    }

    /** En in de volgorde die de eigenaar heeft gesleept. */
    public function test_the_order_is_the_one_the_owner_dragged(): void
    {
        FaqItem::factory()->create(['question_nl' => 'Derde', 'position' => 3]);
        FaqItem::factory()->create(['question_nl' => 'Eerste', 'position' => 1]);
        FaqItem::factory()->create(['question_nl' => 'Tweede', 'position' => 2]);

        $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('faq.0.vraag', 'Eerste')
            ->where('faq.1.vraag', 'Tweede')
            ->where('faq.2.vraag', 'Derde')
            ->etc());
    }

    public function test_the_order_can_be_changed(): void
    {
        $een = FaqItem::factory()->create(['position' => 1]);
        $twee = FaqItem::factory()->create(['position' => 2]);

        $this->actingAs($this->beheerder())
            ->put(route('website.faq.volgorde'), [
                'vragen' => [['id' => $twee->id], ['id' => $een->id]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, (int) $twee->fresh()->position);
        $this->assertSame(2, (int) $een->fresh()->position);
    }

    /**
     * Een halve lijst wordt geweigerd.
     *
     * Zouden er drie van de zes ids binnenkomen, dan krijgen die drie
     * positie 1, 2 en 3 en botsen ze met de andere drie.
     */
    public function test_an_incomplete_order_is_refused(): void
    {
        $een = FaqItem::factory()->create(['position' => 1]);
        FaqItem::factory()->create(['position' => 2]);

        $this->actingAs($this->beheerder())
            ->put(route('website.faq.volgorde'), ['vragen' => [['id' => $een->id]]]);

        $this->assertSame(1, (int) $een->fresh()->position);
    }

    /**
     * De vragen buiten de huidige bladzijde zijn ook `disabled`.
     *
     * **Alleen `hidden` is hier niet genoeg, en dat is nagemeten.** Reka
     * laat de pijltjestoetsen langs de vragen lopen met
     * `useArrowNavigation`, en `findNextFocusableElement` daarin slaat een
     * element met `disabled` over -- maar kijkt niet naar `hidden`. Zonder
     * `disabled` spring je met de pijltjes vanaf de zesde vraag dus naar
     * iets onzichtbaars en ben je je focus kwijt.
     *
     * Dit leest het bestand, want een `assertInertia` ziet alleen de props
     * en niet de DOM. Zelfde aanpak als PasskeyScreenTest.
     */
    public function test_questions_off_the_page_are_also_disabled(): void
    {
        $blok = (string) file_get_contents(
            resource_path('js/components/site/sections/FaqSection.vue'),
        );

        // Zonder commentaar, anders keurt de uitleg hierboven zichzelf goed.
        $blok = (string) preg_replace('~/\*.*?\*/~s', '', $blok);
        $blok = (string) preg_replace('~<!--.*?-->~s', '', $blok);

        $this->assertStringContainsString(':hidden="!staatErNu(index)"', $blok);
        $this->assertStringContainsString(
            ':disabled="!staatErNu(index)"',
            $blok,
            'De vragen buiten de bladzijde zijn niet meer `disabled`; dan loopt de '
                .'toetsenbordnavigatie naar een onzichtbare vraag.',
        );
    }

    /* --- Het briefje voor de zoekmachines ---------------------------------- */

    /**
     * De vragen staan ook als structuurdata in de `<head>`.
     *
     * Via de rootview en niet via Vue: Inertia's `Head`-component laat zo'n
     * tag er niet door, en het is bovendien een beslissing over inhoud.
     */
    public function test_the_questions_are_also_structured_data(): void
    {
        FaqItem::factory()->create([
            'question_nl' => 'Wat kost een migratie?',
            'answer_nl' => 'Dat hangt af van je omvang.',
        ]);

        $html = $this->get(route('home'))->content();

        $this->assertSame(
            1,
            preg_match(
                '~<script type="application/ld\+json">(.*?)</script>~s',
                $html,
                $treffer,
            ),
        );

        /** @var array<string, mixed>|null $briefje */
        $briefje = json_decode($treffer[1], true);

        $this->assertNotNull($briefje, 'Het briefje is geen geldige JSON.');
        $this->assertSame('FAQPage', $briefje['@type']);
        $this->assertSame(
            'Wat kost een migratie?',
            $briefje['mainEntity'][0]['name'],
        );
        $this->assertSame(
            'Dat hangt af van je omvang.',
            $briefje['mainEntity'][0]['acceptedAnswer']['text'],
        );
    }

    /** Zonder vragen staat er niets in de head. */
    public function test_without_questions_there_is_no_note(): void
    {
        $this->assertStringNotContainsString(
            'application/ld+json',
            $this->get(route('home'))->content(),
        );
    }

    /**
     * Een antwoord kan de scripttag niet afbreken.
     *
     * **Dit is de beveiliging van dat briefje en geen opsmuk.** De tekst
     * staat tussen `<script>`-tags en de eigenaar typt hem zelf. Zou er
     * `</script>` in staan, dan sluit dat de tag en is alles erna gewone
     * HTML. `JSON_HEX_TAG` maakt van elke `<` een `<`; haal die vlag
     * weg en deze test valt om.
     */
    public function test_an_answer_cannot_break_out_of_the_script_tag(): void
    {
        FaqItem::factory()->create([
            'question_nl' => 'Gemene vraag?',
            'answer_nl' => 'Kijk uit: </script><img src=x onerror=alert(1)> en verder.',
        ]);

        $html = $this->get(route('home'))->content();

        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $treffer);

        $briefje = $treffer[1] ?? '';

        // Geldige JSON, met de tekst er ongeschonden in.
        $ontleed = json_decode($briefje, true);

        $this->assertNotNull($ontleed);
        $this->assertStringContainsString(
            '</script>',
            $ontleed['mainEntity'][0]['acceptedAnswer']['text'],
        );

        // Maar in de HTML zelf staat geen enkele echte tag.
        $this->assertStringNotContainsString('</script>', $briefje);
        $this->assertStringNotContainsString('<img src=x', $html);
    }

    /* --- De talen ---------------------------------------------------------- */

    public function test_an_english_visitor_gets_the_english_question(): void
    {
        FaqItem::factory()->create([
            'question_nl' => 'Wat kost een migratie?',
            'question_en' => 'What does a migration cost?',
            'answer_nl' => 'Dat hangt af van je omvang.',
            'answer_en' => 'That depends on your size.',
        ]);

        $this->engels()->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('faq.0.vraag', 'What does a migration cost?')
                ->where('faq.0.antwoord', 'That depends on your size.')
                ->etc());
    }

    /**
     * Zonder Engels valt **allebei** terug op het Nederlands.
     *
     * Dat is met opzet anders dan bij een expertisepunt onder een dienst,
     * waar een onvertaald label juist wegvalt: daar is het één van acht en
     * valt er niets op, hier is het de helft van het enige dat er staat.
     * Een Engelse vraag met een Nederlands antwoord eronder is erger dan
     * een vraag die helemaal in het Nederlands staat.
     */
    public function test_a_question_without_english_falls_back_on_both_fields(): void
    {
        FaqItem::factory()->create([
            'question_nl' => 'Wat kost een migratie?',
            'question_en' => null,
            'answer_nl' => 'Dat hangt af van je omvang.',
            'answer_en' => null,
        ]);

        $this->engels()->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('faq.0.vraag', 'Wat kost een migratie?')
                ->where('faq.0.antwoord', 'Dat hangt af van je omvang.')
                ->etc());
    }

    /**
     * Een half vertaalde vraag valt per veld terug.
     *
     * Dus een Engelse vraag met het Nederlandse antwoord eronder kán
     * voorkomen -- als de eigenaar alleen de vraag vertaalde. Het venster
     * waarschuwt daarvoor; de code laat het wel gebeuren, want de helft
     * weggooien is geen verbetering.
     */
    public function test_a_half_translated_question_falls_back_per_field(): void
    {
        FaqItem::factory()->create([
            'question_nl' => 'Wat kost een migratie?',
            'question_en' => 'What does a migration cost?',
            'answer_nl' => 'Dat hangt af van je omvang.',
            'answer_en' => null,
        ]);

        $this->engels()->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('faq.0.vraag', 'What does a migration cost?')
                ->where('faq.0.antwoord', 'Dat hangt af van je omvang.')
                ->etc());
    }
}
