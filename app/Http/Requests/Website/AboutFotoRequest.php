<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Website\Concerns\LogoVelden;
use Illuminate\Foundation\Http\FormRequest;

/**
 * De foto van "Over mij", en verder niets.
 *
 * **Een eigen verzoek, omdat de foto bij geen van de twee versies hoort.**
 * Hij stond eerst bij het korte blok, want daar werd hij ook gekozen. Maar
 * hij staat op de voorpagina én op de aparte pagina, en dan is "hij hoort
 * bij het blok" een afspraak die je moet onthouden in plaats van iets wat
 * je ziet. Nu heeft hij zijn eigen venster en zijn eigen eindpunt, en geldt
 * dezelfde regel als voor de twee tekstvensters: elk verzoek valideert
 * precies zijn eigen velden en kan de rest niet raken.
 *
 * Dat is hier geen netheid maar een voorwaarde. Zat de foto nog bij het
 * blok, dan stuurt een opslag van de samenvatting ook de fotovelden mee --
 * en dan moet de controller elke keer opnieuw uitzoeken of er wel iets over
 * de foto is gezegd. Nu is die vraag weg: komt dit verzoek binnen, dan gaat
 * het over de foto.
 *
 * **De velden heten `foto_*` en niet `logo_*`**; `LogoVelden` laat de
 * veldnaam en de configuratiesleutel overschrijven, want een portret heeft
 * andere grenzen dan een logo. Zie config/media.php.
 *
 * **Autorisatie zit op de route en niet hier.** `can:manage portal` staat
 * op de hele groep in routes/website.php.
 */
class AboutFotoRequest extends FormRequest
{
    use LogoVelden;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Het bestand, het uitsnijvenster en het weghalen. Meer is er niet.
        return $this->logoRegels();
    }

    /** De foto heet `foto` in het formulier; zie de uitleg bovenaan. */
    protected function beeldVeld(): string
    {
        return 'foto';
    }

    /** En hij heeft zijn eigen grenzen; zie config/media.php. */
    protected function mediaSleutel(): string
    {
        return 'portret';
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['foto' => __('foto')];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->logoBerichten();
    }
}
