<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het laadscherm tussen inloggen en het portaal.
 *
 * Het interessante deel is de bestemming. Die komt uit de sessie en wordt
 * aan een pagina meegegeven die hem in de browser opvolgt -- precies de
 * vorm waarin een open omleiding ontstaat als je hem niet toetst.
 */
class PortalEntryTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Een vaste naam, zodat een test op de voornaam niet afhangt van
        // wat de factory verzint -- die levert ook titels en achtervoegsels.
        $user = User::factory()->create(['name' => 'Sam Jansen']);
        $user->assignRole('admin');

        return $user;
    }

    public function test_it_passes_on_the_remembered_destination(): void
    {
        $this->actingAs($this->user())
            ->withSession(['url.intended' => route('admin.mail.index')])
            ->get(route('portal.enter'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('auth/PortalEntry')
                ->where('destination', route('admin.mail.index', absolute: false)));
    }

    public function test_it_falls_back_to_the_dashboard_without_a_destination(): void
    {
        $this->actingAs($this->user())
            ->get(route('portal.enter'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('destination', route('dashboard', absolute: false)));
    }

    public function test_it_refuses_a_destination_on_another_domain(): void
    {
        // Anders bouw je hier een open omleiding: een adres uit de sessie
        // dat de browser klakkeloos opvolgt.
        $this->actingAs($this->user())
            ->withSession(['url.intended' => 'https://kwaadaardig.example/phishing'])
            ->get(route('portal.enter'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('destination', route('dashboard', absolute: false)));
    }

    public function test_it_refuses_a_protocol_relative_destination(): void
    {
        $this->actingAs($this->user())
            ->withSession(['url.intended' => '//kwaadaardig.example/phishing'])
            ->get(route('portal.enter'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('destination', route('dashboard', absolute: false)));
    }

    public function test_it_refuses_itself_as_destination(): void
    {
        // Dit gebeurt echt: wie zonder 2FA binnenkomt wordt hiervandaan naar
        // de instelpagina gestuurd, en dan staat dit adres als onthouden
        // bestemming klaar. Zonder deze grens blijf je rondjes draaien.
        $this->actingAs($this->user())
            ->withSession(['url.intended' => route('portal.enter')])
            ->get(route('portal.enter'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('destination', route('dashboard', absolute: false)));
    }

    public function test_logging_in_sets_the_greeting(): void
    {
        /*
         * Dit is de bron van de begroeting. Hij wordt bij het inloggen
         * gezet en niet op het laadscherm, want dat scherm staat ook tussen
         * de website en het portaal -- daar zou je begroet worden telkens
         * als je even op je eigen site hebt gekeken.
         */
        $user = $this->user();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('portal.enter'));

        $this->assertTrue(session('portal.welcome'));
    }

    public function test_coming_back_from_the_website_does_not_greet_again(): void
    {
        $user = $this->user();

        // Het laadscherm passeren zonder te zijn ingelogd zet niets.
        $this->actingAs($user)->get(route('portal.enter'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('welcome', null));
    }

    public function test_the_greeting_uses_only_the_first_name(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['name' => 'Erik Aarssen']);
        $user->assignRole('admin');

        $this->actingAs($user)
            ->withSession(['portal.welcome' => true])
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('welcome.firstName', 'Erik'));
    }

    public function test_the_greeting_appears_wherever_you_land(): void
    {
        // Dit is de reden dat de begroeting uit de controller van het
        // dashboard is gehaald: met een onthouden bestemming kom je daar
        // helemaal niet langs, en dan werd je niet begroet.
        $user = $this->user();

        $this->actingAs($user)
            ->withSession(['portal.welcome' => true])
            ->get(route('admin.mail.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('welcome.firstName', 'Sam'));
    }

    public function test_the_greeting_shows_once_and_then_not_again(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->withSession(['portal.welcome' => true])
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('welcome.firstName', 'Sam'));

        // Bij een verversing is de vlag opgebruikt; anders krijg je de
        // begroeting elke keer opnieuw voor je kiezen.
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('welcome', null));
    }

    public function test_the_loading_screen_does_not_use_up_the_greeting(): void
    {
        // Het laadscherm staat tussen het inloggen en het portaal in. Zou
        // het de vlag opnemen, dan was hij op voordat er iets te zien was.
        $user = $this->user();

        $this->actingAs($user)
            ->withSession(['portal.welcome' => true])
            ->get(route('portal.enter'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('welcome', null));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('welcome.firstName', 'Sam'));
    }

    public function test_a_page_outside_the_portal_does_not_use_up_the_greeting(): void
    {
        // Wie na het inloggen eerst 2FA moet instellen, komt langs een
        // scherm dat de begroeting niet toont. Zou die hem opgebruiken, dan
        // was de begroeting weg zonder dat iemand hem heeft gezien.
        $user = $this->user();

        $this->actingAs($user)
            ->withSession(['portal.welcome' => true])
            ->get(route('security.two-factor.setup'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('welcome.firstName', 'Sam'));
    }

    public function test_there_is_no_greeting_without_logging_in_first(): void
    {
        $this->actingAs($this->user())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('welcome', null));
    }
}
