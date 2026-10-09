<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Website\Concerns\SchoneVelden;
use App\Models\FooterSetting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * De zin onder het merk in de voet, en verder niets.
 *
 * **Eén verzoek per venster.** De voet heeft drie vensters -- deze tekst,
 * de contactgegevens en de bedrijfsgegevens -- en elk heeft zijn eigen
 * eindpunt met zijn eigen validatie. Dat is de les die bij "Over mij" is
 * opgeschreven en daar een stille fout bleek: bewaart het ene venster, dan
 * gaan de velden van het andere als leeg mee en worden ze gewist.
 *
 * **Autorisatie zit op de route en niet hier.** `can:manage portal` staat
 * op de hele groep in routes/website.php.
 */
class FooterTekstRequest extends FormRequest
{
    use SchoneVelden;

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
            /*
             * `nullable`: geen zin onder je merk is een geldige toestand.
             * Dan staat er in de voet alleen de merknaam, en dat is geen
             * half product maar een keuze.
             */
            'tagline_nl' => ['nullable', 'string', 'max:'.FooterSetting::TAGLINE_MAX],
            'tagline_en' => ['nullable', 'string', 'max:'.FooterSetting::TAGLINE_MAX],

            'machine_translated' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tagline_nl' => __('zin onder je logo'),
            'tagline_en' => __('Engelse zin onder je logo'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            /*
             * Een eigen melding: "mag niet meer dan 120 tekens bevatten"
             * zegt niet waaróm, en hier is dat juist de hele reden.
             */
            'tagline_nl.max' => __(
                'Deze zin staat onderaan elke pagina en past op één regel. Wil je meer vertellen, dan is "Over mij" daarvoor de plek.',
            ),
            'tagline_en.max' => __(
                'Deze zin staat onderaan elke pagina en past op één regel. Wil je meer vertellen, dan is "Over mij" daarvoor de plek.',
            ),
        ];
    }

    /**
     * De gevalideerde teksten als modelvelden.
     *
     * @return array<string, mixed>
     */
    public function gegevens(): array
    {
        return [
            'tagline_nl' => $this->tekst('tagline_nl'),
            'tagline_en' => $this->tekst('tagline_en'),
        ];
    }

    /** Heeft de eigenaar dit Engels door de vertaaldienst laten maken? */
    public function automatischVertaald(): bool
    {
        return $this->boolean('machine_translated');
    }
}
