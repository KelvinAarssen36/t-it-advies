<?php

namespace Tests\Feature\Admin;

use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Filteren op onderwerp in Beheer → Aanvragen.
 *
 * Staat naast het filter op stand en niet erin: "alleen ongelezen" en
 * "alleen offertes" zijn twee vragen die je ook samen kunt stellen.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class AanvraagFilterTest extends TestCase
{
    use RefreshDatabase;

    private function beheerder(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<int, string>
     */
    private function namen(array $filters): array
    {
        $namen = [];

        $this->actingAs($this->beheerder())
            ->get(route('admin.submissions.index', $filters))
            ->assertInertia(function (AssertableInertia $page) use (&$namen) {
                $namen = collect($page->toArray()['props']['aanvragen']['data'])
                    ->pluck('naam')
                    ->all();

                return true;
            });

        return $namen;
    }

    public function test_filtering_on_a_subject_shows_only_that_subject(): void
    {
        $offerte = ContactSubject::factory()->create(['label_nl' => 'Offerte']);
        $spoed = ContactSubject::factory()->create(['label_nl' => 'Spoed']);

        ContactSubmission::factory()->create([
            'name' => 'Bij de offerte',
            'subject_id' => $offerte->id,
            'subject_custom' => false,
        ]);

        ContactSubmission::factory()->create([
            'name' => 'Bij spoed',
            'subject_id' => $spoed->id,
            'subject_custom' => false,
        ]);

        $this->assertSame(['Bij de offerte'], $this->namen(['onderwerp' => (string) $offerte->id]));
    }

    /**
     * En "zelf ingevuld" is een eigen keuze in dat filter.
     *
     * **Dat is geen extraatje.** Een bezoeker die zijn eigen onderwerp typte
     * hangt aan geen enkel onderwerp, dus zonder deze keuze is die groep
     * niet te bekijken -- en juist daar zit wat niet in de lijst van de
     * eigenaar past.
     */
    public function test_self_written_subjects_can_be_filtered_as_a_group(): void
    {
        $offerte = ContactSubject::factory()->create(['label_nl' => 'Offerte']);

        ContactSubmission::factory()->create([
            'name' => 'Uit de lijst',
            'subject_id' => $offerte->id,
            'subject_custom' => false,
        ]);

        ContactSubmission::factory()->create([
            'name' => 'Zelf getypt',
            'subject_id' => null,
            'subject_custom' => true,
        ]);

        $this->assertSame(['Zelf getypt'], $this->namen(['onderwerp' => 'zelf']));
    }

    /** Zonder filter staat alles er. */
    public function test_without_a_filter_everything_is_listed(): void
    {
        $onderwerp = ContactSubject::factory()->create();

        ContactSubmission::factory()->create([
            'name' => 'Een',
            'subject_id' => $onderwerp->id,
            'subject_custom' => false,
        ]);
        ContactSubmission::factory()->create(['name' => 'Twee']);

        $this->assertCount(2, $this->namen([]));
    }

    /** Het filter werkt samen met het filter op stand. */
    public function test_it_combines_with_the_status_filter(): void
    {
        $onderwerp = ContactSubject::factory()->create(['label_nl' => 'Offerte']);

        ContactSubmission::factory()->create([
            'name' => 'Ongelezen offerte',
            'subject_id' => $onderwerp->id,
            'subject_custom' => false,
        ]);

        ContactSubmission::factory()->gelezen()->create([
            'name' => 'Gelezen offerte',
            'subject_id' => $onderwerp->id,
            'subject_custom' => false,
        ]);

        $this->assertSame(
            ['Ongelezen offerte'],
            $this->namen(['onderwerp' => (string) $onderwerp->id, 'stand' => 'ongelezen']),
        );
    }

    /** En met het zoekveld. */
    public function test_it_combines_with_the_search_field(): void
    {
        $onderwerp = ContactSubject::factory()->create(['label_nl' => 'Offerte']);

        ContactSubmission::factory()->create([
            'name' => 'Kees Jansen',
            'subject_id' => $onderwerp->id,
            'subject_custom' => false,
        ]);

        ContactSubmission::factory()->create([
            'name' => 'Marieke Visser',
            'subject_id' => $onderwerp->id,
            'subject_custom' => false,
        ]);

        $this->assertSame(
            ['Kees Jansen'],
            $this->namen(['onderwerp' => (string) $onderwerp->id, 'zoek' => 'Kees']),
        );
    }

    /**
     * Het scherm stuurt álle onderwerpen mee om op te filteren, ook de
     * offline.
     *
     * Zette de eigenaar er een offline, dan blijven de aanvragen die eraan
     * hangen bestaan -- en dan moet hij ze ook nog kunnen opzoeken.
     */
    public function test_the_filter_list_includes_offline_subjects(): void
    {
        ContactSubject::factory()->create(['label_nl' => 'Online']);
        ContactSubject::factory()->offline()->create(['label_nl' => 'Offline']);

        $this->actingAs($this->beheerder())
            ->get(route('admin.submissions.index'))
            ->assertInertia(function (AssertableInertia $page) {
                $namen = collect($page->toArray()['props']['onderwerpen'])->pluck('naam');

                $this->assertTrue($namen->contains('Online'));
                $this->assertTrue($namen->contains('Offline'));

                return true;
            });
    }

    /** Een onzinnig onderwerp levert niets op en geen fout. */
    public function test_an_unknown_subject_simply_finds_nothing(): void
    {
        ContactSubmission::factory()->create(['name' => 'Kees Jansen']);

        $this->assertSame([], $this->namen(['onderwerp' => '999999']));
    }
}
