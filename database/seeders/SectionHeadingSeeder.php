<?php

namespace Database\Seeders;

use App\Enums\PageSectionKey;
use App\Models\SectionHeading;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Zet de kop van elk onderdeel klaar dat er een heeft.
 *
 * Dit verving `HeroHeadingSeeder`, `ExperienceHeadingSeeder` en
 * `ServiceHeadingSeeder`. Eén seeder, en de tekst komt uit
 * `SectionHeading::standaard()` -- dus niet meer op twee plekken.
 *
 * **Het is een startpunt en geen definitieve tekst.** Anders dan bij de
 * tijdlijn, waar de seeder de echte loopbaan van de eigenaar bevat,
 * staat hier tekst die er vooral is zodat de voorpagina niet leeg is. De
 * klant schrijft hem om zodra hij weet wat er moet staan; dat is precies
 * waarvoor die schermen bestaan.
 *
 * **De seeder is aanvullend, niet leidend.** Staat er al een rij voor
 * een onderdeel, dan blijft die staan -- ook al heeft de klant hem
 * aangepast. Zou de seeder hem terugzetten, dan gooit elke deploy zijn
 * tekst weg, en dat ziet hij op zijn eigen voorpagina.
 *
 * Zie docs/architecture/kopteksten.md.
 */
class SectionHeadingSeeder extends Seeder
{
    /*
     * Zaaien is geen handeling van de eigenaar, dus het hoort niet in
     * zijn activiteitenlogboek.
     */
    use WithoutModelEvents;

    /**
     * De onderdelen met een beheerbare kop.
     *
     * De werkwijze, het contactformulier, LinkedIn en de voettekst staan
     * er niet bij: die hebben hun tekst nog in hun eigen component, of
     * hebben helemaal geen kop.
     *
     * @var array<int, PageSectionKey>
     */
    private const MET_KOP = [
        PageSectionKey::Hero,
        PageSectionKey::Diensten,
        PageSectionKey::Projecten,
        PageSectionKey::Ervaring,
        PageSectionKey::Certificaten,
        PageSectionKey::Statistieken,
        PageSectionKey::Contact,
    ];

    public function run(): void
    {
        foreach (self::MET_KOP as $sectie) {
            if (SectionHeading::query()->where('section', $sectie)->exists()) {
                continue;
            }

            SectionHeading::query()->create([
                'section' => $sectie,
                ...SectionHeading::standaard($sectie),
            ]);
        }
    }
}
