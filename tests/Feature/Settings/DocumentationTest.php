<?php

namespace Tests\Feature\Settings;

use App\Enums\PageSectionKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De handleiding in de instellingen.
 *
 * Er valt hier weinig logica na te lopen -- de pagina haalt niets op en
 * slaat niets op -- maar drie dingen moeten wél blijven kloppen.
 *
 * Het eerste is dat hij achter de inlog zit. De pagina beschrijft in detail
 * hoe het beheergedeelte werkt, en dat is niets voor een bezoeker van de
 * publieke site.
 *
 * Het tweede is dat hij blijft bestaan. Een handleiding die stilletjes uit
 * de instellingen verdwijnt merkt niemand, want niemand kijkt er dagelijks
 * naar -- tot de eigenaar hem nodig heeft.
 *
 * Het derde is nieuw, en het dekt de afspraak die hier het hele punt is:
 * **elk onderdeel dat de klant kan beheren hoort hier een kaart te
 * hebben.** Die afspraak staat in AGENTS.md en in de documentatie, en tot
 * nu toe bewaakte niets hem -- en een vergeten kaart merk je pas als de
 * klant iets opzoekt wat er niet staat.
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

    /**
     * Elke beheerbare module heeft een kaart in de handleiding.
     *
     * Dezelfde opzet als AppSidebarTest, en om dezelfde reden: de
     * afspraak staat op papier, en een afspraak die niets bewaakt wordt
     * vroeg of laat vergeten.
     *
     * Hij zoekt op de **titel** van de kaart, die gelijk is aan de naam
     * van het onderdeel op het indelingsscherm. Dat is geen toeval: de
     * klant leest op twee plekken hetzelfde woord, en dan hoort het
     * hetzelfde woord te zijn.
     */
    public function test_every_manageable_module_has_a_card(): void
    {
        /*
         * De labels komen uit `__()`, dus de taal moet vastliggen. Zonder
         * dit hangt de uitkomst af van wat er in de omgeving staat, en
         * dan slaagt of faalt deze test om de verkeerde reden.
         */
        $this->app->setLocale('nl');

        $handleiding = (string) file_get_contents(
            resource_path('js/pages/settings/Documentatie.vue'),
        );

        $gevonden = 0;

        foreach (PageSectionKey::cases() as $sectie) {
            if ($sectie->beheerRoute() === null) {
                continue;
            }

            $gevonden++;

            $this->assertStringContainsString(
                "\$t('{$sectie->label()}')",
                $handleiding,
                "Module [{$sectie->value}] is te beheren maar heeft geen kaart in de handleiding. "
                    .'Zet er een UitlegKaart voor in Documentatie.vue; zie '
                    .'docs/architecture/uitleg-voor-de-eigenaar.md.',
            );
        }

        // Eerst bewijzen dat er iets te controleren viel: zonder deze
        // regel slaagt de test ook als er geen enkele module bestaat.
        $this->assertGreaterThan(0, $gevonden);
    }
}
