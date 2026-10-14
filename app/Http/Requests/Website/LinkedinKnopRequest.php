<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Website\Concerns\SchoneVelden;
use App\Models\LinkedinSetting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * De tekst op de knop van het LinkedIn-blok, in beide talen.
 *
 * **Alleen de knop.** De kop erboven heeft zijn eigen eindpunt
 * (`website.linkedin.kop`, via BewaartKoptekst); zou dit verzoek die
 * velden ook kennen, dan wist het ene venster de tekst van het andere. Zie
 * FooterController voor waar die les vandaan komt.
 *
 * **Geen adres.** De link staat in config/site.php en is hier met opzet
 * geen veld. Een `url` die toch meekomt wordt genegeerd: hij staat niet in
 * de regels en dus ook niet in `gegevens()`.
 *
 * **Autorisatie zit op de route en niet hier.** `can:manage portal` staat
 * op de hele groep in routes/website.php.
 */
class LinkedinKnopRequest extends FormRequest
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
             * `nullable`: leeg betekent de standaardtekst, "Bekijk [naam]".
             * Dat is geen half ingevuld formulier maar de beginstand.
             */
            'button_nl' => ['nullable', 'string', 'max:'.LinkedinSetting::KNOP_MAX],
            'button_en' => ['nullable', 'string', 'max:'.LinkedinSetting::KNOP_MAX],

            'machine_translated' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'button_nl' => __('knoptekst'),
            'button_en' => __('Engelse knoptekst'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'button_nl.max' => __('Een knop is kort: hooguit :max tekens, anders breekt hij op een telefoon over twee regels.'),
            'button_en.max' => __('Een knop is kort: hooguit :max tekens, anders breekt hij op een telefoon over twee regels.'),
        ];
    }

    /**
     * De gevalideerde teksten als modelvelden; leeg wordt `null`.
     *
     * @return array<string, string|null>
     */
    public function gegevens(): array
    {
        return [
            'button_nl' => $this->tekst('button_nl'),
            'button_en' => $this->tekst('button_en'),
        ];
    }

    /** Heeft de eigenaar dit Engels door de vertaaldienst laten maken? */
    public function automatischVertaald(): bool
    {
        return $this->boolean('machine_translated');
    }
}
