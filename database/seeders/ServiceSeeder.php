<?php

namespace Database\Seeders;

use App\Enums\ServiceIcon;
use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Zet de drie diensten klaar die tot nu toe in het Vue-component stonden.
 *
 * **Een startpunt en geen definitieve tekst.** Anders dan bij de tijdlijn,
 * waar de seeder de echte loopbaan van de eigenaar bevat, staat hier tekst
 * die er vooral is zodat het blok niet leeg is -- en een leeg blok
 * verdwijnt van de website. De klant schrijft het om zodra hij weet wat er
 * moet staan; daarvoor is dit scherm er.
 *
 * De expertisepunten zijn wel echt bedoeld als voorbeeld van wat er kan:
 * korte labels, geen zinnen. Zo ziet de klant meteen waar dat veld voor is.
 *
 * **De seeder is aanvullend, niet leidend.** Staat er al een dienst, dan
 * blijft alles staan -- ook wat de klant heeft aangepast of weggehaald.
 * Zou de seeder zijn drie diensten terugzetten, dan komt bij elke deploy
 * een verwijderde dienst weer op zijn voorpagina te staan.
 *
 * Zie ook HeroHeadingSeeder; hetzelfde patroon en om dezelfde reden.
 */
class ServiceSeeder extends Seeder
{
    /*
     * Zaaien is geen handeling van de eigenaar, dus het hoort niet in zijn
     * activiteitenlogboek.
     */
    use WithoutModelEvents;

    public function run(): void
    {
        if (Service::query()->exists()) {
            return;
        }

        foreach ($this->diensten() as $plek => $dienst) {
            $punten = $dienst['punten'];
            unset($dienst['punten']);

            $service = Service::query()->create([
                ...$dienst,
                'position' => $plek + 1,
                'published' => true,
            ]);

            foreach ($punten as $index => $punt) {
                $service->points()->create([
                    'position' => $index + 1,
                    'text_nl' => $punt[0],
                    'text_en' => $punt[1],
                ]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function diensten(): array
    {
        return [
            [
                'icon' => ServiceIcon::Advice,
                'title_nl' => 'Advies',
                'title_en' => 'Advice',
                'summary_nl' => 'Meedenken over wat er nodig is en wat niet. Een keuze die je over drie jaar nog kunt uitleggen.',
                'summary_en' => 'Thinking along about what is needed and what is not. A choice you can still explain three years from now.',
                'body_nl' => 'Voordat er iets gebouwd of gekocht wordt, is de vraag wat het probleem eigenlijk is. Vaak blijkt de helft van een wensenlijst op te lossen met wat er al staat, en de andere helft pas zinvol zodra die eerste helft werkt.'."\n\n".'Wat je krijgt is een helder verhaal: wat er nu is, waar het knelt, wat de opties zijn en wat ze kosten. Inclusief de optie om niets te doen, want die is vaker goed dan je denkt.',
                'body_en' => 'Before anything is built or bought, the question is what the problem actually is. Half of a wish list often turns out to be solvable with what is already there, and the other half only becomes worthwhile once that first half works.'."\n\n".'What you get is a clear account: what exists now, where it pinches, what the options are and what they cost. Including the option of doing nothing, which is a good one more often than you would think.',
                'punten' => [
                    ['inventarisatie', 'inventory'],
                    ['leverancierskeuze', 'choosing suppliers'],
                    ['kostenraming', 'cost estimates'],
                    ['tweede mening', 'second opinion'],
                ],
            ],
            [
                'icon' => ServiceIcon::Delivery,
                'title_nl' => 'Realisatie',
                'title_en' => 'Delivery',
                'summary_nl' => 'Bouwen wat er is afgesproken, met documentatie die meegroeit met de code.',
                'summary_en' => 'Building what was agreed, with documentation that grows along with the code.',
                'body_nl' => 'In korte stappen, zodat je onderweg kunt bijsturen in plaats van aan het eind te ontdekken dat het net iets anders moest. Elke stap levert iets op dat werkt.'."\n\n".'De documentatie hoort bij de oplevering en niet erna: wie het overneemt moet er zonder mij mee verder kunnen.',
                'body_en' => 'In short steps, so you can adjust along the way instead of discovering at the end that it needed to be slightly different. Every step delivers something that works.'."\n\n".'The documentation is part of the handover and not an afterthought: whoever takes it over has to be able to continue without me.',
                'punten' => [
                    ['inrichting', 'setup'],
                    ['migraties', 'migrations'],
                    ['koppelingen', 'integrations'],
                    ['documentatie', 'documentation'],
                ],
            ],
            [
                'icon' => ServiceIcon::Maintenance,
                'title_nl' => 'Beheer',
                'title_en' => 'Management',
                'summary_nl' => 'Draaiend houden, in de gaten houden en bijsturen voordat het een storing wordt.',
                'summary_en' => 'Keeping it running, keeping an eye on it and adjusting before it becomes an outage.',
                'body_nl' => 'Het meeste beheer is voorkomen dat er iets misgaat: updates op tijd, back-ups die ook echt zijn teruggezet, en meldingen die bij iemand terechtkomen die er wat mee doet.'."\n\n".'Gaat er toch iets stuk, dan weet je wie je belt en hoeft niemand eerst uit te zoeken hoe het in elkaar zit.',
                'body_en' => 'Most management is preventing things from going wrong: updates on time, backups that have actually been restored once, and alerts that reach someone who acts on them.'."\n\n".'If something does break, you know who to call, and nobody has to work out how it all fits together first.',
                'punten' => [
                    ['monitoring', 'monitoring'],
                    ['back-ups', 'backups'],
                    ['updates', 'updates'],
                    ['storingen', 'incidents'],
                ],
            ],
        ];
    }
}
