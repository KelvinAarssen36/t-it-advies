<?php

namespace Tests\Feature\Activity;

use App\Enums\ActivityAction;
use App\Models\ActivityEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het activiteitenlogboek: wie heeft wat aan de inhoud veranderd.
 *
 * De belangrijkste test staat onderaan. Een logboek dat per ongeluk een
 * wachtwoord of een 2FA-geheim opslaat is erger dan geen logboek: dan staat
 * het geheim op een plek die juist is bedoeld om lang bewaard te blijven en
 * door mensen te worden gelezen. Zie regel 2 in AGENTS.md.
 */
class ActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_something_is_recorded(): void
    {
        $user = User::factory()->create(['name' => 'Erik Aarssen']);

        $entry = ActivityEntry::query()->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertSame(ActivityAction::Created, $entry->action);
        $this->assertSame(User::class, $entry->subject_type);
        $this->assertSame($user->id, $entry->subject_id);
        $this->assertSame('Erik Aarssen', $entry->subject_label);
    }

    public function test_an_update_records_what_changed_and_what_was_there(): void
    {
        $user = User::factory()->create(['name' => 'Oude naam']);

        $this->actingAs($user);

        $user->update(['name' => 'Nieuwe naam']);

        $entry = ActivityEntry::query()
            ->ofAction(ActivityAction::Updated)
            ->latest('id')
            ->firstOrFail();

        // Dit is het verschil tussen een logboek dat zegt dát er iets
        // veranderde en een dat zegt wát.
        $this->assertSame(
            ['name' => ['van' => 'Oude naam', 'naar' => 'Nieuwe naam']],
            $entry->changes,
        );

        $this->assertSame('Nieuwe naam', $entry->actor_name);
        $this->assertSame($user->id, $entry->user_id);
    }

    public function test_saving_without_changes_records_nothing(): void
    {
        $user = User::factory()->create();

        $aantal = ActivityEntry::query()->count();

        // Opslaan zonder iets te wijzigen mag geen regel opleveren; anders
        // vult het logboek zich met ruis waar de echte wijzigingen in
        // verdwijnen.
        $user->save();
        $user->update(['name' => $user->name]);

        $this->assertSame($aantal, ActivityEntry::query()->count());
    }

    public function test_deleting_keeps_the_label_readable(): void
    {
        $user = User::factory()->create(['name' => 'Weg Ermee']);

        $user->delete();

        $entry = ActivityEntry::query()
            ->ofAction(ActivityAction::Deleted)
            ->latest('id')
            ->firstOrFail();

        // Het onderdeel bestaat niet meer, dus zonder deze momentopname zou
        // er staan dat "iets" is verwijderd -- precies de regel die je
        // later terugzoekt.
        $this->assertSame('Weg Ermee', $entry->subject_label);
        $this->assertSame('Account', $entry->subjectName());
    }

    public function test_a_change_without_a_logged_in_user_says_system(): void
    {
        // Een seeder of een geplande taak heeft geen gebruiker. Er hoort dan
        // niet niets te staan, maar wie het wél deed.
        User::factory()->create();

        $entry = ActivityEntry::query()->latest('id')->firstOrFail();

        $this->assertSame('Systeem', $entry->actor_name);
        $this->assertNull($entry->user_id);
    }

    public function test_nothing_sensitive_is_ever_recorded(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'password' => bcrypt('een-heel-geheim-wachtwoord'),
            'remember_token' => 'geheim-token',
            'two_factor_secret' => 'GEHEIMESLEUTEL',
        ])->save();

        $alles = ActivityEntry::query()->get()->toJson();

        foreach ([
            'een-heel-geheim-wachtwoord',
            'geheim-token',
            'GEHEIMESLEUTEL',
        ] as $geheim) {
            $this->assertStringNotContainsString($geheim, $alles);
        }
    }
}
