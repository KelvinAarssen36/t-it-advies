<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Website\Concerns\SchoneVelden;
use App\Models\FooterSetting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * De contactgegevens in de voet: telefoon en werkgebied.
 *
 * **Het e-mailadres zit hier niet bij, en dat is geen omissie.** Het staat
 * in `config/site.php`, en `config/mail.php` valt erop terug voor de
 * ontvanger van het contactformulier. Beheerbaar maken zou betekenen dat de
 * eigenaar zijn eigen formulier kan omleiden zonder dat hij dat doorheeft.
 *
 * **Het telefoonnummer blijft vrije tekst.** "06 12 34 56 78",
 * "+31 6 12345678" en "06-12345678" zijn alle drie correct, en welke van de
 * drie er op zijn site hoort te staan weet hij beter dan wij. De regel
 * eronder houdt alleen tegen wat in geen enkele notatie een nummer is.
 */
class FooterContactRequest extends FormRequest
{
    use SchoneVelden;

    /**
     * Wat een telefoonnummer mag bevatten.
     *
     * Cijfers, spaties, een plusteken voor het landnummer, en streepjes of
     * haakjes voor wie zo schrijft. Letters niet: dan staat er een woord
     * achter een `tel:`-link en belt er niets.
     *
     * **Er moet minstens één cijfer in staan, en dat ontbrak hier.** De
     * eerste versie was `[0-9+\-() ]+`, en die eist nergens een cijfer.
     * Daardoor kwamen `"+"`, `"--"` en `"( ) -"` er gewoon doorheen, met
     * twee uitkomsten die allebei fout zijn:
     *
     * - `"+"` werd letterlijk `<a href="tel:+">+</a>` op de site;
     * - `"--"` liet `telefoonLink()` leeg achter, zodat de regel stil van
     *   de site viel terwijl het beheerscherm hem als ingevuld toonde.
     *
     * De groepen eromheen laten alles toe wat daarvoor en daarna mag
     * staan; de `[0-9]` in het midden is wat het een nummer maakt.
     */
    public const TELEFOON_PATROON = '/^[0-9+\-() ]*[0-9][0-9+\-() ]*$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'phone' => [
                'nullable',
                'string',
                'max:'.FooterSetting::TELEFOON_MAX,
                'regex:'.self::TELEFOON_PATROON,
            ],

            'region_nl' => ['nullable', 'string', 'max:'.FooterSetting::PLAATS_MAX],
            'region_en' => ['nullable', 'string', 'max:'.FooterSetting::PLAATS_MAX],

            'machine_translated' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone' => __('telefoonnummer'),
            'region_nl' => __('plaats of werkgebied'),
            'region_en' => __('Engelse plaats of werkgebied'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __(
                'Hier hoort een telefoonnummer te staan: minstens één cijfer, verder spaties, een plusteken of een streepje.',
            ),
        ];
    }

    /**
     * De gevalideerde velden als modelvelden.
     *
     * @return array<string, mixed>
     */
    public function gegevens(): array
    {
        return [
            'phone' => $this->tekst('phone'),
            'region_nl' => $this->tekst('region_nl'),
            'region_en' => $this->tekst('region_en'),
        ];
    }

    /** Heeft de eigenaar dit Engels door de vertaaldienst laten maken? */
    public function automatischVertaald(): bool
    {
        return $this->boolean('machine_translated');
    }
}
