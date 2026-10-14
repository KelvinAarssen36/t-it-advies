<?php

namespace Tests\Feature\Admin;

use App\Enums\PageSectionKey;
use App\Models\Backup;
use App\Models\LinkedinSetting;
use App\Models\PageSection;
use App\Models\SectionHeading;
use App\Support\Backup\BackupLezer;
use App\Support\Backup\BackupMaker;
use App\Support\Backup\BackupTerugzetter;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Het LinkedIn-blok in een back-up, en terug.
 *
 * Drie dingen moeten kloppen:
 *
 * 1. **Een nieuwe back-up neemt alles mee**: de kop en de knoptekst in
 *    beide talen, plus de plek en de zichtbaarheid van het blok.
 * 2. **Een oude back-up laat de teksten met rust.** Van vóór het beheer
 *    stond de tekst in de code; zo'n back-up heeft voor LinkedIn niets.
 *    Dan blijft wat de eigenaar nu heeft staan, net als bij elke module
 *    die er toen nog niet was -- en is er niets, dan staat de standaard.
 * 3. **De proef verandert niets.**
 *
 * Alles op een database in het geheugen met een nep-schijf; er wordt
 * nooit iets teruggezet over echte gegevens.
 *
 * Zie docs/operations/back-ups.md.
 */
class BackupLinkedinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function data(Backup $backup): array
    {
        $lezer = app(BackupLezer::class);
        $pad = (string) $backup->pad();
        $manifest = $lezer->manifest($pad);

        return $lezer->data($pad, (string) $manifest['checksum']);
    }

    /**
     * Een back-up zoals hij gemaakt werd voordat LinkedIn te beheren was:
     * zonder `linkedin_settings` en zonder kop voor dit blok.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $data
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function alsOudeBackup(array $data): array
    {
        unset($data['linkedin_settings']);

        $data['section_headings'] = array_values(array_filter(
            $data['section_headings'],
            fn (array $rij): bool => $rij['section'] !== PageSectionKey::Linkedin->value,
        ));

        return $data;
    }

    private function zetLinkedinNeer(string $titel, string $knop): void
    {
        SectionHeading::query()->updateOrCreate(
            ['section' => PageSectionKey::Linkedin],
            [
                'eyebrow_nl' => 'Volgen',
                'eyebrow_en' => 'Follow',
                'title_nl' => $titel,
                'title_en' => $titel.' (EN)',
                'intro_nl' => 'Zin',
                'intro_en' => 'Line',
            ],
        );

        LinkedinSetting::query()->updateOrCreate(['id' => 1], [
            'button_nl' => $knop,
            'button_en' => $knop.' (EN)',
        ]);
    }

    /* --- 1. Alles gaat mee --------------------------------------------- */

    public function test_a_new_backup_brings_both_languages_back(): void
    {
        $this->zetLinkedinNeer('Zoals het was', 'Volg mij');

        $backup = app(BackupMaker::class)->maak();

        $this->zetLinkedinNeer('Verprutst', 'Weg');

        app(BackupTerugzetter::class)->zetTerug($this->data($backup));

        $kop = SectionHeading::voor(PageSectionKey::Linkedin);
        $knop = LinkedinSetting::huidige();

        $this->assertSame('Zoals het was', $kop->title_nl);
        $this->assertSame('Zoals het was (EN)', $kop->title_en);
        $this->assertSame('Follow', $kop->eyebrow_en);
        $this->assertSame('Volg mij', $knop->button_nl);
        $this->assertSame('Volg mij (EN)', $knop->button_en);
    }

    public function test_the_place_and_visibility_of_the_block_come_back(): void
    {
        $rij = PageSection::query()->where('key', PageSectionKey::Linkedin)->firstOrFail();
        $rij->forceFill(['position' => 2, 'visible' => false])->save();

        $backup = app(BackupMaker::class)->maak();

        $rij->forceFill(['position' => 9, 'visible' => true])->save();

        app(BackupTerugzetter::class)->zetTerug($this->data($backup));

        $rij->refresh();

        $this->assertSame(2, $rij->position);
        $this->assertFalse($rij->visible);
    }

    /**
     * Het adres zit niet in de back-up.
     *
     * Het staat in config/site.php; een back-up die het meenam zou bij
     * terugzetten een tweede waarheid opleveren.
     */
    public function test_the_address_is_not_in_the_backup(): void
    {
        $data = $this->data(app(BackupMaker::class)->maak());

        $this->assertArrayHasKey('linkedin_settings', $data);
        $this->assertStringNotContainsString(
            (string) config('site.linkedin'),
            (string) json_encode($data),
        );
    }

    /* --- 2. Een oude back-up ------------------------------------------- */

    public function test_an_old_backup_leaves_the_linkedin_texts_alone(): void
    {
        $this->zetLinkedinNeer('Van na de back-up', 'Volg mij');

        // Een andere kop, om te bewijzen dat de rest wél teruggaat.
        SectionHeading::query()->create([
            'section' => PageSectionKey::Faq,
            'eyebrow_nl' => 'Vragen',
            'title_nl' => 'Zoals het was',
        ]);

        $data = $this->alsOudeBackup($this->data(app(BackupMaker::class)->maak()));

        SectionHeading::query()
            ->where('section', PageSectionKey::Faq)
            ->update(['title_nl' => 'Verprutst']);

        $meldingen = app(BackupTerugzetter::class)->zetTerug($data);

        $this->assertSame('Van na de back-up', SectionHeading::voor(PageSectionKey::Linkedin)->title_nl);
        $this->assertSame('Van na de back-up (EN)', SectionHeading::voor(PageSectionKey::Linkedin)->title_en);
        $this->assertSame('Volg mij', LinkedinSetting::huidige()->button_nl);
        $this->assertSame('Zoals het was', SectionHeading::voor(PageSectionKey::Faq)->title_nl);

        $this->assertTrue(
            collect($meldingen)->contains(fn (string $m): bool => str_contains($m, 'linkedin_settings')),
            'Niet gemeld dat de knoptekst ongemoeid is gelaten.',
        );
        $this->assertTrue(
            collect($meldingen)->contains(fn (string $m): bool => str_contains($m, 'LinkedIn')),
            'Niet gemeld dat de kop van LinkedIn is gebleven.',
        );
    }

    /**
     * Ook als de `id` van de LinkedIn-kop in de oude back-up van een andere
     * kop is.
     *
     * Kan gebeuren: de kop van LinkedIn is later aangemaakt, en het
     * nummer dat hij kreeg kan in een oudere stand bij iets anders horen.
     * Dan wint de back-up dat nummer, en komt LinkedIn terug onder een
     * nieuw nummer. De sleutel die telt is `section`.
     */
    public function test_an_old_backup_with_a_clashing_id_keeps_both(): void
    {
        $this->zetLinkedinNeer('Van na de back-up', 'Volg mij');

        $linkedinId = (int) SectionHeading::voor(PageSectionKey::Linkedin)->id;

        $data = $this->alsOudeBackup($this->data(app(BackupMaker::class)->maak()));

        // In de oude stand had dat nummer de kop van de vragen.
        $faq = SectionHeading::query()->make([
            'section' => PageSectionKey::Faq,
            'eyebrow_nl' => 'Vragen',
            'title_nl' => 'Uit de oude back-up',
        ])->getAttributes();
        $faq['id'] = $linkedinId;
        $faq['section'] = PageSectionKey::Faq->value;
        $faq['created_at'] = now()->toDateTimeString();
        $faq['updated_at'] = now()->toDateTimeString();
        $data['section_headings'][] = $faq;

        app(BackupTerugzetter::class)->zetTerug($data);

        $this->assertSame('Uit de oude back-up', SectionHeading::voor(PageSectionKey::Faq)->title_nl);
        $this->assertSame('Van na de back-up', SectionHeading::voor(PageSectionKey::Linkedin)->title_nl);
        $this->assertSame(
            1,
            SectionHeading::query()->where('section', PageSectionKey::Linkedin)->count(),
        );
    }

    /** Was er nog niets, dan staat na het terugzetten de standaardtekst. */
    public function test_an_old_backup_on_a_site_without_linkedin_texts_shows_the_default(): void
    {
        $data = $this->alsOudeBackup($this->data(app(BackupMaker::class)->maak()));

        app(BackupTerugzetter::class)->zetTerug($data);

        $props = $this->get(route('home'))->viewData('page')['props'];

        $this->assertSame('Blijf op de hoogte', $props['linkedinBlock']['kop']['titel']);
        $this->assertSame(
            'Bekijk '.config('security.portal_account.name'),
            $props['linkedinBlock']['knop'],
        );
    }

    /**
     * Een back-up van ná het beheer, waarin LinkedIn nog op de
     * standaardtekst stond, zet de standaard ook echt terug.
     *
     * Dat is het verschil met een oude back-up: hier stónd de knoptekst-
     * tabel erin, dus de back-up wist van het blok en zijn stand telt.
     */
    public function test_a_new_backup_with_default_texts_restores_the_default(): void
    {
        $backup = app(BackupMaker::class)->maak();

        $this->zetLinkedinNeer('Later geschreven', 'Later');

        app(BackupTerugzetter::class)->zetTerug($this->data($backup));

        $this->assertFalse(SectionHeading::voor(PageSectionKey::Linkedin)->exists);
        $this->assertSame('Blijf op de hoogte', SectionHeading::voor(PageSectionKey::Linkedin)->title_nl);
        $this->assertSame(0, LinkedinSetting::query()->count());
    }

    /* --- 3. De proef ---------------------------------------------------- */

    public function test_the_trial_restore_of_an_old_backup_changes_nothing(): void
    {
        $this->zetLinkedinNeer('Zoals het nu is', 'Volg mij');

        $data = $this->alsOudeBackup($this->data(app(BackupMaker::class)->maak()));

        $voor = DB::table('section_headings')->orderBy('id')->get()->toArray();

        app(BackupTerugzetter::class)->zetTerug($data, proef: true);

        $this->assertEquals($voor, DB::table('section_headings')->orderBy('id')->get()->toArray());
        $this->assertSame('Volg mij', LinkedinSetting::huidige()->button_nl);
    }
}
