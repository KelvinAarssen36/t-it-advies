<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De handleiding in de instellingen.
 *
 * Er valt hier weinig logica na te lopen -- de pagina haalt niets op en
 * slaat niets op -- maar twee dingen moeten wél blijven kloppen.
 *
 * Het eerste is dat hij achter de inlog zit. De pagina beschrijft in detail
 * hoe het beheergedeelte werkt, en dat is niets voor een bezoeker van de
 * publieke site.
 *
 * Het tweede is dat hij blijft bestaan. Een handleiding die stilletjes uit
 * de instellingen verdwijnt merkt niemand, want niemand kijkt er dagelijks
 * naar -- tot de eigenaar hem nodig heeft.
 *
 * Zie docs/architecture/uitleg-voor-de-eigenaar.md.
 */
class DocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('documentation.show'))->assertRedirect(route('login'));
    }

    public function test_the_owner_gets_the_manual(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('documentation.show'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page->component('settings/Documentatie'),
            );
    }
}
