<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Website\Concerns\SchoneVelden;
use App\Models\AboutSetting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Het korte blok van "Over mij": de samenvatting, en verder niets.
 *
 * **Eén verzoek per blok en niet één voor allebei.** Dit stond eerst samen
 * met de teksten van de aparte pagina in één `AboutRequest`, en dat was een
 * fout met een stille uitkomst: bewaart het ene venster, dan gaan de velden
 * van het andere als leeg mee en worden ze gewist. Nu valideert elk venster
 * precies zijn eigen velden, en kan een halve opslag de andere helft niet
 * raken.
 *
 * **De grens op de samenvatting is de reden dat de aparte pagina bestaat.**
 * Zonder die grens wordt dit blok het hele levensverhaal midden op de
 * voorpagina. Het getal staat op het model, zodat het venster met dezelfde
 * grens kan rekenen.
 *
 * **De foto zit hier niet bij, en dat is een tweede correctie.** Hij werd
 * hier gekozen, dus hij werd hier ook gevalideerd. Maar hij staat op de
 * voorpagina én op de aparte pagina, en dan is "hij hoort bij het blok" een
 * afspraak die je moet onthouden in plaats van iets wat je ziet. Hij heeft
 * nu zijn eigen venster en zijn eigen verzoek; zie AboutFotoRequest.
 *
 * **Autorisatie zit op de route en niet hier.** `can:manage portal` staat
 * op de hele groep in routes/website.php.
 */
class AboutBlokRequest extends FormRequest
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
             * `nullable`, want een leeg "Over mij" is een geldige toestand:
             * dan staat het onderdeel niet op de site. Dat is geen fout, en
             * het scherm zegt het ook met zoveel woorden.
             */
            'summary_nl' => ['nullable', 'string', 'max:'.AboutSetting::SAMENVATTING_MAX],
            'summary_en' => ['nullable', 'string', 'max:'.AboutSetting::SAMENVATTING_MAX],

            'machine_translated' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'summary_nl' => __('samenvatting'),
            'summary_en' => __('Engelse samenvatting'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            /*
             * Een eigen melding, want de standaardtekst zegt alleen "mag
             * niet meer dan 400 tekens bevatten" -- en dat waarom is hier
             * het hele punt van de aparte pagina.
             */
            'summary_nl.max' => __(
                'Dit stuk staat op je voorpagina en is daarom kort gehouden. Wil je meer vertellen, zet dat dan op je aparte pagina -- daar is ruimte voor je hele verhaal.',
            ),
            'summary_en.max' => __(
                'Dit stuk staat op je voorpagina en is daarom kort gehouden. Wil je meer vertellen, zet dat dan op je aparte pagina -- daar is ruimte voor je hele verhaal.',
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
            'summary_nl' => $this->tekst('summary_nl'),
            'summary_en' => $this->tekst('summary_en'),
        ];
    }

    /** Heeft de eigenaar dit Engels door de vertaaldienst laten maken? */
    public function automatischVertaald(): bool
    {
        return $this->boolean('machine_translated');
    }
}
