<?php

namespace App\Console\Commands;

use App\Models\AboutPoint;
use App\Models\AboutSetting;
use App\Models\Certificate;
use App\Models\CoreFact;
use App\Models\Education;
use App\Models\Experience;
use App\Models\FaqItem;
use App\Models\FooterSetting;
use App\Models\Project;
use App\Models\SectionHeading;
use App\Models\Service;
use App\Models\ServicePoint;
use App\Models\Statistic;
use App\Models\WorkStep;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Rekt de voorbeelddata op tot de randen, om de opmaak te beproeven.
 *
 * **Waarom dit bestaat.** De gewone voorbeelddata is nette, korte tekst, en
 * daarmee ziet elke pagina er goed uit. Dat zegt niets over wat er gebeurt
 * zodra de klant een naam van honderdtwintig tekens invult, of een kort
 * vakje naast een lang zet. Juist daar gaat opmaak stuk: een kaart die zijn
 * buren oprekt, een woord dat uit zijn vak steekt, een rij die de pagina
 * breder maakt dan het scherm.
 *
 * Dit commando zet per onderdeel **drie gevallen naast elkaar**:
 *
 * | Geval        | Waarvoor                                                |
 * | ------------ | ------------------------------------------------------- |
 * | Tot de rand  | Elk veld op zijn maximum -- hoe hoog wordt het vak?     |
 * | Onbreekbaar  | Eén woord van veertig tekens -- steekt het eruit?       |
 * | Minimaal     | Twee tekens -- hoeveel lege ruimte blijft er over?      |
 *
 * Dat laatste is net zo belangrijk als het eerste: een vak met twee tekens
 * naast een vak met drie regels is waar de lege vlakken ontstaan.
 *
 * **Het is geen test maar een kijkglas.** Er wordt niets gecontroleerd; je
 * zet het aan, je kijkt in je browser, en je haalt het weer weg met
 * `php artisan voorbeeld:opruimen`.
 *
 * Draait alleen lokaal, net als de seeder zelf.
 */
class RekVoorbeeldDataOp extends Command
{
    protected $signature = 'voorbeeld:oprekken {--force : Zonder vragen}';

    protected $description = 'Vult de voorbeelddata tot de randen, om te zien of de opmaak dat aankan (alleen lokaal)';

    /**
     * Een woord dat nergens kan afbreken.
     *
     * Veertig tekens zonder spatie, streepje of koppelteken. Dit is het
     * geval waar `overflow-wrap` voor bestaat; zonder die regel steekt dit
     * uit elk vak dat smaller is.
     */
    private const ONBREEKBAAR = 'Bedrijfsinformatievoorzieningsbeheerder';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->components->error('Deze opdracht draait alleen in local en testing.');

            return self::FAILURE;
        }

        $this->components->warn(
            'Dit overschrijft bestaande teksten met opvultekst. Bedoeld voor een '
                .'voorbeelddatabase, niet voor werk dat je wil houden.'
        );

        if (! $this->option('force') && ! $this->confirm('Doorgaan?', false)) {
            $this->components->info('Niets gedaan.');

            return self::SUCCESS;
        }

        $geraakt = 0;

        $geraakt += $this->rekOp(Service::query()->orderBy('id')->get()->all(), [
            'title_nl' => 60,
            'summary_nl' => 300,
        ]);

        $geraakt += $this->rekOp(ServicePoint::query()->orderBy('id')->get()->all(), [
            'text_nl' => 60,
        ]);

        $geraakt += $this->rekOp(CoreFact::query()->orderBy('id')->get()->all(), [
            'label_nl' => 60,
            'value_nl' => 80,
            'note_nl' => 120,
        ]);

        $geraakt += $this->rekOp(Certificate::query()->orderBy('id')->get()->all(), [
            'title_nl' => 120,
            'issuer' => 120,
        ]);

        $geraakt += $this->rekOp(Education::query()->orderBy('id')->get()->all(), [
            'title_nl' => 120,
            'institution' => 120,
        ]);

        $geraakt += $this->rekOp(Statistic::query()->orderBy('id')->get()->all(), [
            'label_nl' => 60,
            'note_nl' => 120,
        ]);

        $geraakt += $this->rekOp(Project::query()->orderBy('id')->get()->all(), [
            'title_nl' => 120,
            'summary_nl' => 300,
        ]);

        $geraakt += $this->rekOp(WorkStep::query()->orderBy('id')->get()->all(), [
            'title_nl' => 60,
            'summary_nl' => 300,
            'duration_nl' => 40,
            'result_nl' => 160,
        ]);

        $geraakt += $this->rekOp(FaqItem::query()->orderBy('id')->get()->all(), [
            'question_nl' => 160,
        ]);

        $geraakt += $this->rekOp(AboutPoint::query()->orderBy('id')->get()->all(), [
            'text_nl' => 80,
        ]);

        $geraakt += $this->rekOp(Experience::query()->orderBy('id')->get()->all(), [
            'role_nl' => 120,
            'organisation' => 120,
            'location_nl' => 120,
        ]);

        $geraakt += $this->rekOp(SectionHeading::query()->orderBy('id')->get()->all(), [
            'eyebrow_nl' => 60,
            'title_nl' => 120,
            'intro_nl' => 300,
        ]);

        /*
         * De twee enkele rijen, los: die hebben geen buren om naast te
         * zetten, dus daar is alleen "tot de rand" zinvol.
         */
        $this->vul(AboutSetting::huidige(), ['summary_nl' => 400], 0);
        $this->vul(FooterSetting::huidige(), ['tagline_nl' => 120, 'region_nl' => 60], 0);
        $geraakt += 2;

        $this->newLine();
        $this->components->info($geraakt.' rijen opgerekt.');
        $this->components->twoColumnDetail('Terugdraaien', 'php artisan voorbeeld:opruimen && php artisan voorbeeld:zaaien');

        $this->newLine();
        $this->components->info(
            'Kijk nu op je website naar: de certificaten (vier naast elkaar), de '
                .'kerngegevens, de opleidingen, de diensten en de projectkaarten.'
        );

        return self::SUCCESS;
    }

    /**
     * Zet drie gevallen naast elkaar in één lijst.
     *
     * De eerste rij tot de rand, de tweede met een onbreekbaar woord, de
     * derde minimaal, en de rest blijft zoals hij was. Zo staan de drie
     * uitersten náást elkaar in dezelfde rij op de pagina -- en dat is
     * precies waar een ongelijke hoogte zichtbaar wordt.
     *
     * **Een array en geen collectie**, want een `Collection<Experience>` is
     * voor de statische analyse geen `Collection<Model>`: generieke types
     * zijn niet covariant. Bij een array is dat wel zo.
     *
     * @param  array<int, Model>  $rijen
     * @param  array<string, int>  $velden
     */
    private function rekOp(array $rijen, array $velden): int
    {
        $geraakt = 0;

        foreach (array_values($rijen) as $i => $rij) {
            if ($i > 2) {
                break;
            }

            $this->vul($rij, $velden, $i);
            $geraakt++;
        }

        return $geraakt;
    }

    /**
     * @param  array<string, int>  $velden
     */
    private function vul(Model $rij, array $velden, int $geval): void
    {
        foreach ($velden as $veld => $maximum) {
            $rij->{$veld} = match ($geval) {
                1 => self::ONBREEKBAAR,
                2 => 'Ja',
                default => $this->opvulling($maximum),
            };
        }

        $rij->save();
    }

    /**
     * Leesbare opvultekst van precies de gevraagde lengte.
     *
     * Echte woorden en geen "aaaa": een rij a's breekt anders af dan een
     * zin, en dan beproef je de verkeerde situatie.
     */
    private function opvulling(int $lengte): string
    {
        $bron = 'Advies over inrichting beheer en migratie van werkplekken netwerken '
            .'en toepassingen voor organisaties die verder willen zonder afhankelijk '
            .'te worden van een enkele leverancier ';

        $tekst = '';

        while (mb_strlen($tekst) < $lengte) {
            $tekst .= $bron;
        }

        return rtrim(mb_substr($tekst, 0, $lengte));
    }
}
