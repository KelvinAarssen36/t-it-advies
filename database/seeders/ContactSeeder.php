<?php

namespace Database\Seeders;

use App\Enums\ContactVeld;
use App\Models\ContactField;
use App\Models\ContactSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Zet de velden en de instellingen van het contactformulier klaar.
 *
 * **Geen onderwerpen.** Die verzint de klant zelf, en een formulier met
 * drie verzonnen onderwerpen erin is erger dan een formulier zonder:
 * zonder onderwerpen is het een gewoon tekstveld, precies zoals het nu al
 * werkt.
 *
 * **De seeder is aanvullend, niet leidend.** Een veld dat er al staat
 * blijft staan zoals de klant het heeft gezet -- zijn standen en zijn
 * volgorde blijven van hem. Wat hij wél doet is nieuwe velden erbij zetten
 * en verdwenen velden opruimen. Zelfde gedachte als PageSectionSeeder.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class ContactSeeder extends Seeder
{
    /*
     * Zaaien is geen handeling van de eigenaar, dus het hoort niet in
     * zijn activiteitenlogboek.
     */
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (ContactVeld::cases() as $veld) {
            ContactField::query()->firstOrCreate(
                ['key' => $veld],
                [
                    'status' => $veld->standaardStatus(),
                    'position' => $this->plekVoor($veld),
                    'allow_custom' => true,
                ],
            );
        }

        $this->ruimOp();

        // De instellingen: één rij, met de standaardtekst van de
        // bevestigingsmail erin. Die staat op het model en niet hier,
        // zodat de twee niet uit elkaar kunnen lopen.
        if (ContactSetting::query()->doesntExist()) {
            ContactSetting::query()->create(ContactSetting::standaard());
        }
    }

    /**
     * Waar een nieuw veld komt te staan.
     *
     * Op een verse database de plek uit de enum, zodat de hele rij in één
     * keer klopt. In een database die al gevuld is achteraan: zodra de
     * klant zijn volgorde één keer heeft opgeslagen zijn de velden
     * hernummerd, en dan botst een standaardplek met een plek die al
     * bezet is. Dezelfde les als in PageSectionSeeder.
     */
    private function plekVoor(ContactVeld $veld): int
    {
        if (ContactField::query()->doesntExist()) {
            return $veld->standaardPositie();
        }

        return ((int) ContactField::query()->max('position')) + 1;
    }

    /**
     * Velden die niet meer bestaan.
     *
     * Een rij met een sleutel die niet meer in de enum staat levert een
     * fout op zodra iemand hem uitleest -- de cast kan er niets van maken.
     */
    private function ruimOp(): void
    {
        $bekend = array_map(
            fn (ContactVeld $veld) => $veld->value,
            ContactVeld::cases(),
        );

        ContactField::query()->whereNotIn('key', $bekend)->delete();
    }
}
