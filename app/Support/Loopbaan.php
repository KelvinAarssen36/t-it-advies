<?php

namespace App\Support;

use App\Enums\ExperienceStatKey;
use App\Enums\ExperienceStatModus;
use App\Models\Experience;
use App\Models\ExperienceStat;
use Illuminate\Database\Eloquent\Collection;

/**
 * De cijfers boven de tijdlijn: uitrekenen, en wat de klant zelf instelde.
 *
 * Dit staat in een eigen klasse en niet in een controller, omdat er twee
 * plekken zijn die het nodig hebben en ze niet uit elkaar mogen lopen: de
 * **website** toont de cijfers, en het **beheerscherm** toont wat er zou
 * staan als je op automatisch zet. Zou elk scherm het zelf uitrekenen, dan
 * belooft het portaal iets anders dan de site laat zien.
 *
 * De klant bepaalt per cijfer wat er gebeurt -- uitrekenen, een eigen
 * getal, of niet tonen -- en hoe het heet. Zie App\Enums\ExperienceStatModus.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
class Loopbaan
{
    /**
     * De cijfers zoals ze op de website komen te staan.
     *
     * Leeg als er geen ervaringen zijn: nullen boven een lege lijst is
     * erger dan geen cijfers. Verborgen cijfers zitten er niet bij, en een
     * eigen cijfer zonder getal ook niet -- dat is een half ingevuld ding
     * en geen nul.
     *
     * @param  Collection<int, Experience>  $loopbaan  de ervaringen die online staan
     * @return array<int, array{waarde: int, label: string}>
     */
    public function cijfers(Collection $loopbaan): array
    {
        if ($loopbaan->isEmpty()) {
            return [];
        }

        $berekend = $this->berekend($loopbaan);

        $uitkomst = [];

        foreach ($this->rijen()->where('modus', '!=', ExperienceStatModus::Verborgen) as $rij) {
            $waarde = $this->waardeVan($rij, $berekend);

            if ($waarde === null) {
                continue;
            }

            $uitkomst[] = ['waarde' => $waarde, 'label' => $rij->woord()];
        }

        return $uitkomst;
    }

    /**
     * Welk getal er bij dit cijfer hoort, of null als er geen te tonen is.
     *
     * Een eigen cijfer zonder getal levert `null`: de klant heeft het soort
     * gekozen maar nog niets ingevuld, en dan is er niets om te laten zien.
     * Dat kan niet gebeuren via het beheerscherm -- daar is het getal
     * verplicht -- maar wel via een oude rij of een handmatige wijziging.
     *
     * @param  array<string, int>  $berekend
     */
    public function waardeVan(ExperienceStat $rij, array $berekend): ?int
    {
        if ($rij->modus === ExperienceStatModus::Eigen) {
            return $rij->value;
        }

        return $rij->key->berekenbaar()
            ? ($berekend[$rij->key->value] ?? 0)
            : $rij->value;
    }

    /**
     * Alle cijfers die de klant heeft staan, op volgorde.
     *
     * @return Collection<int, ExperienceStat>
     */
    public function rijen(): Collection
    {
        return ExperienceStat::query()->opVolgorde()->get();
    }

    /**
     * Wat de cijfers zijn volgens de tijdlijn zelf.
     *
     * Alleen de soorten die wij kunnen tellen staan erin; een eigen cijfer
     * heeft hier niets te zoeken.
     *
     * @param  Collection<int, Experience>  $loopbaan
     * @return array<string, int>
     */
    public function berekend(Collection $loopbaan): array
    {
        if ($loopbaan->isEmpty()) {
            return [
                ExperienceStatKey::Years->value => 0,
                ExperienceStatKey::Roles->value => 0,
                ExperienceStatKey::Organisations->value => 0,
            ];
        }

        return [
            ExperienceStatKey::Years->value => $this->jaren($loopbaan),
            ExperienceStatKey::Roles->value => $loopbaan->count(),
            ExperienceStatKey::Organisations->value => $loopbaan
                ->pluck('organisation')
                ->unique()
                ->count(),
        ];
    }

    /**
     * De ervaringen die meetellen: alles wat online staat.
     *
     * @return Collection<int, Experience>
     */
    public function online(): Collection
    {
        return Experience::query()->online()->get();
    }

    /**
     * Hoeveel jaar ervaring de tijdlijn beslaat.
     *
     * Van de vroegste startdatum tot het laatste einde, of tot vandaag als
     * er nog iets loopt. **Niet de som van alle periodes**: functies
     * overlappen, en dan tel je jezelf rijk.
     *
     * Minstens één: wie deze maand begonnen is heeft geen "0 jaar".
     *
     * @param  Collection<int, Experience>  $loopbaan
     */
    private function jaren(Collection $loopbaan): int
    {
        $begin = $loopbaan->min('started_on');

        $einde = $loopbaan->contains(fn (Experience $ervaring) => $ervaring->loopt())
            ? now()
            : $loopbaan->max('ended_on');

        return max(1, (int) $begin->diffInYears($einde));
    }
}
