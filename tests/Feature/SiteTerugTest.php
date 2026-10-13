<?php

namespace Tests\Feature;

use App\Enums\PageSectionKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De weg terug onder aan een subpagina.
 *
 * **Waarom dit een test verdient.** Hier stond één knop, "Terug naar de
 * voorpagina", en die bracht je bovenaan uit. Wie van de voorpagina een
 * project aanklikte mocht daarna de halve pagina opnieuw afscrollen. Nu
 * staan er twee, en wijst de eerste naar het onderdeel waar je vandaan
 * kwam.
 *
 * Dat hangt aan twee dingen die stil kunnen breken: elke subpagina moet het
 * gedeelde onderdeel gebruiken, en de sleutel die hij meegeeft moet een
 * onderdeel zijn dat echt bestaat. Een typefout in zo'n sleutel geeft geen
 * foutmelding -- de knop verdwijnt dan gewoon, want `SiteTerug` kijkt eerst
 * of het onderdeel in het menu staat.
 *
 * Deze tests lezen de componenten als tekst, net als
 * `PrivacyPageTest::test_the_footer_links_to_it`: het gaat om wat er in de
 * browser gebeurt en niet om een antwoord van de server.
 */
class SiteTerugTest extends TestCase
{
    use RefreshDatabase;

    /** De subpagina's van de publieke site, met het onderdeel waar ze bij horen. */
    private const PAGINAS = [
        'Projecten' => 'projecten',
        'OverMij' => 'over-mij',
        'Werkwijze' => 'werkwijze',
        'Contact' => 'contact',

        // De privacyverklaring hoort bij de voettekst en is geen onderdeel
        // van de voorpagina; daar is één knop het eerlijke antwoord.
        'Privacy' => null,

        // Een project bepaalt zijn eerste knop zelf: het onderdeel als je
        // van de voorpagina kwam, anders de lijst.
        'ProjectDetail' => null,
    ];

    private function leesComponent(string $naam): string
    {
        return (string) file_get_contents(
            resource_path('js/pages/public/'.$naam.'.vue'),
        );
    }

    public function test_every_public_subpage_ends_with_the_shared_block(): void
    {
        foreach (array_keys(self::PAGINAS) as $naam) {
            $this->assertStringContainsString(
                '<SiteTerug',
                $this->leesComponent($naam),
                "{$naam}.vue eindigt niet met het gedeelde terugblok.",
            );
        }
    }

    public function test_every_section_key_it_passes_really_exists(): void
    {
        $bestaand = array_map(
            fn (PageSectionKey $sectie) => $sectie->value,
            PageSectionKey::cases(),
        );

        foreach (self::PAGINAS as $naam => $sleutel) {
            if ($sleutel === null) {
                continue;
            }

            $this->assertStringContainsString(
                'sleutel="'.$sleutel.'"',
                $this->leesComponent($naam),
                "{$naam}.vue geeft niet het onderdeel '{$sleutel}' mee.",
            );

            $this->assertContains(
                $sleutel,
                $bestaand,
                "'{$sleutel}' is geen bestaand onderdeel; de knop zou stil verdwijnen.",
            );
        }
    }

    /**
     * Een project dat je via de lijst opent hoort terug te gaan naar die
     * lijst, en niet naar een onderdeel van de voorpagina waar je nooit
     * bent geweest. Dat onderscheid hangt aan `?van=start`; zie
     * `PublicProjectController`.
     */
    public function test_a_project_returns_to_where_it_was_opened_from(): void
    {
        $detail = $this->leesComponent('ProjectDetail');

        $this->assertStringContainsString(
            "props.van === 'start' ? 'projecten' : null",
            $detail,
            'Een project van de voorpagina wijst niet meer terug naar het onderdeel.',
        );

        $this->assertStringContainsString(
            'Terug naar alle projecten',
            $detail,
            'Een project uit de lijst wijst niet meer terug naar die lijst.',
        );
    }

    /**
     * De tweede knop gebruikt hetzelfde woord als het kruimelpad bovenaan.
     * Twee woorden voor dezelfde plek laat een bezoeker twijfelen of het
     * wel dezelfde plek is.
     */
    public function test_the_second_button_uses_the_same_word_as_the_breadcrumb(): void
    {
        $terug = (string) file_get_contents(
            resource_path('js/components/site/SiteTerug.vue'),
        );

        $kruimels = (string) file_get_contents(
            resource_path('js/components/site/SiteKruimels.vue'),
        );

        $this->assertStringContainsString("\$t('Voorpagina')", $terug);
        $this->assertStringContainsString("'Voorpagina'", $kruimels);
    }
}
