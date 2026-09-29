<?php

namespace App\Support;

use App\Enums\ExperienceStatKey;
use App\Models\Experience;
use App\Models\ExperienceStat;
use Illuminate\Database\Eloquent\Collection;

/**
 * De cijfers boven de tijdlijn: uitrekenen, en wat de klant zelf invulde.
 *
 * Dit staat in een eigen klasse en niet in een controller, omdat er twee
 * plekken zijn die het nodig hebben en ze niet uit elkaar mogen lopen: de
 * **website** toont de cijfers, en het **beheerscherm** toont wat er zou
 * staan als je het veld leeglaat. Zou elk scherm het zelf uitrekenen, dan
 * belooft het portaal iets anders dan de site laat zien.
 *
 * De regel is simpel: **ingevuld wint, leeg wordt berekend.** Zo klopt een
 * cijfer vanzelf zodra er een functie bij komt, en kan de klant er toch
 * overheen als hij het anders wil.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
class Loopbaan
{
    /**
     * De cijfers zoals ze op de website komen te staan.
     *
     * Leeg als er geen ervaringen zijn: drie nullen boven een lege lijst
     * is erger dan geen cijfers.
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
        $ingevuld = $this->ingevuld();

        return array_map(
            fn (ExperienceStatKey $cijfer) => [
                'waarde' => $ingevuld[$cijfer->value] ?? $berekend[$cijfer->value],
                'label' => $cijfer->label(),
            ],
            ExperienceStatKey::opVolgorde(),
        );
    }

    /**
     * Wat de cijfers zijn volgens de tijdlijn zelf.
     *
     * @param  Collection<int, Experience>  $loopbaan
     * @return array<string, int>
     */
    public function berekend(Collection $loopbaan): array
    {
        if ($loopbaan->isEmpty()) {
            return array_fill_keys(
                array_map(fn (ExperienceStatKey $cijfer) => $cijfer->value, ExperienceStatKey::cases()),
                0,
            );
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
     * Wat de klant zelf heeft ingevuld, op sleutel.
     *
     * Een rij die er nog niet is -- de seeder heeft nog niet gedraaid --
     * telt als niet ingevuld. Dat is de veilige kant op: dan rekenen we
     * het gewoon uit.
     *
     * @return array<string, int>
     */
    public function ingevuld(): array
    {
        return ExperienceStat::query()
            ->whereNotNull('value')
            ->pluck('value', 'key')
            ->all();
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
