<?php

namespace Database\Seeders;

use App\Enums\PageSectionKey;
use App\Models\PageSection;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Zet de onderdelen van de landingspagina klaar.
 *
 * Dit is productiedata en geen testdata: zonder deze rijen is de
 * landingspagina leeg. Hij hoort dus in elke omgeving te draaien.
 *
 * **De seeder is aanvullend, niet leidend.** Een onderdeel dat er al staat
 * blijft precies zoals de klant het heeft gezet -- op zijn plek, aan of
 * uit. Zou de seeder de volgorde terugzetten, dan gooit elke deploy zijn
 * werk weg, en dat merkt hij op zijn eigen website.
 *
 * Wat hij wél doet: nieuwe onderdelen toevoegen zodra wij er een bouwen, en
 * onderdelen opruimen die niet meer bestaan. Dat laatste is dezelfde keuze
 * als in RolesAndPermissionsSeeder: een rij die naar een verwijderde module
 * verwijst, levert een regel op het indelingsscherm op die nergens heen
 * gaat.
 */
class PageSectionSeeder extends Seeder
{
    /*
     * Zaaien is geen handeling van de eigenaar, dus het hoort niet in zijn
     * activiteitenlogboek. De trait staat hier en niet alleen op
     * DatabaseSeeder, zodat het ook klopt als iemand deze seeder los
     * aanroept met `--class=PageSectionSeeder`.
     */
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (PageSectionKey::cases() as $sectie) {
            PageSection::query()->firstOrCreate(
                ['key' => $sectie],
                [
                    'position' => $sectie->standaardPositie(),
                    'visible' => true,
                ],
            );
        }

        $this->ruimOp();
    }

    /**
     * Weg met rijen waarvan het onderdeel niet meer bestaat.
     */
    private function ruimOp(): void
    {
        $bestaand = array_map(
            fn (PageSectionKey $sectie) => $sectie->value,
            PageSectionKey::cases(),
        );

        PageSection::query()->whereNotIn('key', $bestaand)->delete();
    }
}
