<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Website\Concerns\SchoneVelden;
use Illuminate\Foundation\Http\FormRequest;

/**
 * De invoer van één contactonderwerp, voor zowel aanmaken als wijzigen.
 *
 * **Autorisatie zit op de route en niet hier.** `can:manage portal` staat
 * op de hele groep in routes/website.php.
 */
class ContactSubjectRequest extends FormRequest
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
             * Tachtig tekens. Dit is een regel in een keuzelijst en geen
             * zin: wordt hij langer, dan breekt hij af op een telefoon en
             * kiest niemand hem meer.
             */
            'label_nl' => ['required', 'string', 'max:80'],
            'label_en' => ['nullable', 'string', 'max:80'],

            'published' => ['boolean'],

            /*
             * Uitlichten: dit onderwerp komt als snelkeuze boven de
             * keuzelijst op de site. Geen plek in de volgorde maar een
             * eigen vlag, zodat de eigenaar zijn volgorde houdt als hij
             * iets anders wil uitlichten. Zie de migratie.
             */
            'featured' => ['boolean'],

            'automatisch_vertaald' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label_nl' => __('onderwerp'),
            'label_en' => __('Engelse naam'),
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
            'label_nl' => $this->string('label_nl')->trim()->toString(),
            'label_en' => $this->tekst('label_en'),
            'featured' => $this->boolean('featured'),
        ];
    }

    /** Of het Engels van de vertaaldienst komt en nog niet is nagelezen. */
    public function automatischVertaald(): bool
    {
        return $this->boolean('automatisch_vertaald');
    }
}
