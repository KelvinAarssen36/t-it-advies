<?php

namespace Tests\Feature\Website;

use App\Enums\ContactWeergave;
use App\Enums\PageSectionKey;
use App\Models\ContactSetting;
use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Models\PageSection;
use App\Models\User;
use Database\Seeders\ContactSeeder;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Spatie\Honeypot\EncryptedTime;
use Tests\TestCase;

/**
 * Waar het contactformulier op de website staat.
 *
 * Twee keuzes: het formulier onderaan de landingspagina, of alleen een knop
 * daar met het formulier op `/contact`. De eigenaar kiest, en die keuze
 * moet aan allebei de kanten kloppen -- de landing én het losse adres.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class ContactPlacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
        $this->seed(ContactSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    private function zet(ContactWeergave $weergave): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.contact.instellingen'), [
                'display' => $weergave->value,
            ])
            ->assertSessionHasNoErrors();
    }

    /* --- Het formulier op de landingspagina ------------------------------- */

    public function test_the_form_stands_on_the_landing_page_by_default(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contact.eigenPagina', false)
                ->has('contact.velden', 4)
                ->has('contact.kop'));
    }

    /** En het losse adres bestaat dan niet. */
    public function test_the_separate_page_does_not_exist_then(): void
    {
        $this->get(route('contact'))->assertNotFound();
    }

    /* --- De eigen contactpagina ------------------------------------------- */

    public function test_the_landing_page_shows_a_button_instead(): void
    {
        $this->zet(ContactWeergave::EigenPagina);

        $this->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contact.eigenPagina', true)
                // De velden gaan dan niet mee: één formulier op één plek,
                // en dus ook één Turnstile-widget.
                ->has('contact.velden', 0));
    }

    public function test_the_separate_page_works(): void
    {
        $this->zet(ContactWeergave::EigenPagina);

        $this->get(route('contact'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/Contact')
                ->has('velden', 4)
                ->has('kop')
                ->where('email', config('site.email')));
    }

    /**
     * Een bezoek aan de contactpagina telt mee.
     *
     * Zonder `TelBezoek` op die route zakken de bezoekcijfers zodra de
     * eigenaar voor deze weergave kiest -- en dan lijkt het of zijn website
     * minder bekeken wordt terwijl hij alleen iets heeft omgezet.
     */
    public function test_a_visit_to_the_separate_page_is_counted(): void
    {
        $this->zet(ContactWeergave::EigenPagina);

        /*
         * Uitloggen én een browserkenmerk meegeven. De teller slaat een
         * ingelogde bezoeker over -- dat is de eigenaar die zijn eigen site
         * bekijkt -- en een verzoek zonder browserkenmerk ook. Zie
         * Bezoekteller::telMee().
         */
        auth()->logout();

        $voor = (int) DB::table('site_day_totals')->sum('views');

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/131.0 Safari/537.36',
        ])->get(route('contact'))->assertOk();

        $this->assertGreaterThan(
            $voor,
            (int) DB::table('site_day_totals')->sum('views'),
        );
    }

    /**
     * Staat het hele onderdeel uit, dan bestaat ook de pagina niet.
     *
     * Dan wil de eigenaar geen contactformulier, en een adres dat er toch
     * is leidt bezoekers naar iets dat hij heeft weggehaald.
     */
    public function test_the_page_is_gone_when_the_section_is_switched_off(): void
    {
        $this->zet(ContactWeergave::EigenPagina);

        PageSection::query()
            ->where('key', PageSectionKey::Contact)
            ->update(['visible' => false]);

        $this->get(route('contact'))->assertNotFound();
    }

    /**
     * En dan neemt het formulier ook geen berichten meer aan.
     *
     * **Dit ontbrak, en daarmee was het uitzetten een halve instelling.**
     * Het formulier verdween netjes van de site, maar `POST /contact` bleef
     * aannemen, opslaan én mailen -- een oud tabblad of een bot met die URL
     * kwam er dus nog door, terwijl de eigenaar dacht dat hij het had
     * uitgezet.
     *
     * Zie Contactformulier::staatAan().
     */
    public function test_no_message_is_accepted_when_the_section_is_switched_off(): void
    {
        Mail::fake();

        PageSection::query()
            ->where('key', PageSectionKey::Contact)
            ->update(['visible' => false]);

        $this->post(route('contact.store'), [
            'name' => 'Kees Jansen',
            'email' => 'kees@example.com',
            'subject_text' => 'Vraag over een migratie',
            'message' => 'Graag advies over onze overstap naar een nieuwe omgeving.',
            config('honeypot.name_field_name') => '',
            config('honeypot.valid_from_field_name') => EncryptedTime::create(now()->subMinute()),
        ])->assertNotFound();

        $this->assertSame(0, ContactSubmission::query()->count());
        Mail::assertNothingQueued();
    }

    /**
     * Op een verse database zonder indeling blijft het formulier werken.
     *
     * Dezelfde terugval als op de landingspagina: is er nooit geseed, dan
     * geldt de volgorde uit de code. Anders levert een vergeten `db:seed`
     * een website zonder contactformulier op, en dat is het soort fout dat
     * je op de publieke site niet wil laten afhangen van of iemand eraan
     * gedacht heeft.
     */
    public function test_the_form_works_on_a_database_without_any_sections(): void
    {
        Mail::fake();

        PageSection::query()->delete();

        $this->post(route('contact.store'), [
            'name' => 'Kees Jansen',
            'email' => 'kees@example.com',
            'subject_text' => 'Vraag over een migratie',
            'message' => 'Graag advies over onze overstap naar een nieuwe omgeving.',
            config('honeypot.name_field_name') => '',
            config('honeypot.valid_from_field_name') => EncryptedTime::create(now()->subMinute()),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ContactSubmission::query()->count());
    }

    /* --- De onderwerpen op de site ---------------------------------------- */

    public function test_only_published_subjects_reach_the_visitor(): void
    {
        ContactSubject::factory()->create(['label_nl' => 'Zichtbaar']);
        ContactSubject::factory()->offline()->create(['label_nl' => 'Verborgen']);

        $this->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('contact.onderwerpen', 1)
                ->where('contact.onderwerpen.0.naam', 'Zichtbaar'));
    }

    public function test_an_english_visitor_gets_the_english_subject(): void
    {
        ContactSubject::factory()->create([
            'label_nl' => 'Vrijblijvend gesprek',
            'label_en' => 'Informal conversation',
        ]);

        $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9'])
            ->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contact.onderwerpen.0.naam', 'Informal conversation'));
    }

    /** Zonder Engelse naam valt hij terug op het Nederlands. */
    public function test_a_missing_english_name_falls_back_to_dutch(): void
    {
        ContactSubject::factory()->create([
            'label_nl' => 'Vrijblijvend gesprek',
            'label_en' => null,
        ]);

        $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9'])
            ->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contact.onderwerpen.0.naam', 'Vrijblijvend gesprek'));
    }

    /* --- Het blok blijft staan zonder onderwerpen ------------------------- */

    /**
     * Het contactformulier verdwijnt niet van de site als er geen
     * onderwerpen zijn.
     *
     * **Anders dan bij elke andere module**, en dat is met opzet: zonder
     * onderwerpen is het onderwerpveld gewoon een tekstvak, en dat werkt
     * prima. Daarom staat er in `SectionContent` géén teller voor Contact.
     */
    public function test_the_section_stays_without_subjects(): void
    {
        $this->assertSame(0, ContactSubject::query()->count());

        $this->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where(
                    'sections',
                    fn ($secties) => collect($secties)->contains('contact'),
                ));
    }

    /* --- De instellingen -------------------------------------------------- */

    public function test_an_unknown_display_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.contact.instellingen'), ['display' => 'zijkant'])
            ->assertSessionHasErrors('display');

        $this->assertSame(
            ContactWeergave::OpDePagina,
            ContactSetting::huidige()->display,
        );
    }
}
