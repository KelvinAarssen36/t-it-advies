<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Website\Concerns\SchoneVelden;
use App\Models\AboutSetting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * De teksten van de aparte pagina "Over mij".
 *
 * De tegenhanger van `AboutBlokRequest`. Samen in één verzoek stoppen zou
 * betekenen dat het ene venster de velden van het andere leeg bewaart; zie
 * de uitleg daar.
 *
 * **De pagina aan- of uitzetten zit hier niet bij.** Dat is één schuifje en
 * hoort niet het hele formulier langs de validatie te sturen -- zelfde
 * afspraak als het online-schuifje bij de andere modules. Zie
 * `AboutController::paginaAan()`.
 *
 * **Autorisatie zit op de route en niet hier.** `can:manage portal` staat
 * op de hele groep in routes/website.php.
 */
class AboutPaginaRequest extends FormRequest
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
            'page_title_nl' => ['nullable', 'string', 'max:120'],
            'page_title_en' => ['nullable', 'string', 'max:120'],
            'page_intro_nl' => ['nullable', 'string', 'max:300'],
            'page_intro_en' => ['nullable', 'string', 'max:300'],

            'story_nl' => ['nullable', 'string', 'max:'.AboutSetting::VERHAAL_MAX],
            'story_en' => ['nullable', 'string', 'max:'.AboutSetting::VERHAAL_MAX],

            'machine_translated' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'page_title_nl' => __('titel van de pagina'),
            'page_title_en' => __('Engelse titel van de pagina'),
            'page_intro_nl' => __('inleiding van de pagina'),
            'page_intro_en' => __('Engelse inleiding van de pagina'),
            'story_nl' => __('verhaal'),
            'story_en' => __('Engelse verhaal'),
        ];
    }

    /**
     * De gevalideerde invoer als modelvelden.
     *
     * @return array<string, mixed>
     */
    public function gegevens(): array
    {
        return [
            'page_title_nl' => $this->tekst('page_title_nl'),
            'page_title_en' => $this->tekst('page_title_en'),
            'page_intro_nl' => $this->tekst('page_intro_nl'),
            'page_intro_en' => $this->tekst('page_intro_en'),

            /*
             * Het verhaal wordt aan de buitenkant getrimd, maar de
             * witregels binnenin blijven staan: dat zijn de alinea's.
             */
            'story_nl' => $this->tekst('story_nl'),
            'story_en' => $this->tekst('story_en'),
        ];
    }

    /** Heeft de eigenaar dit Engels door de vertaaldienst laten maken? */
    public function automatischVertaald(): bool
    {
        return $this->boolean('machine_translated');
    }
}
