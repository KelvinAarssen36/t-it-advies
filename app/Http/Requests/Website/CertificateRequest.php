<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Website\Concerns\LogoVelden;
use App\Http\Requests\Website\Concerns\SchoneVelden;
use Illuminate\Foundation\Http\FormRequest;

/**
 * De invoer van één certificaat, voor zowel aanmaken als wijzigen.
 *
 * **De datums komen binnen als "2024-03" en niet als volledige datum.**
 * Je haalt een certificaat in maart; de dag erbij zou een precisie
 * suggereren die de klant moet opzoeken. `SchoneVelden::maand()` maakt
 * er de eerste van de maand van, zodat de database gewoon een
 * datumkolom kan zijn waarop je kunt sorteren en vergelijken.
 *
 * **Autorisatie zit op de route en niet hier.** `can:manage portal`
 * staat op de hele groep in routes/website.php; zie
 * docs/security/rollen-en-rechten.md.
 */
class CertificateRequest extends FormRequest
{
    use LogoVelden;
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
             * Of het certificaat op de website staat. Een nieuw
             * certificaat krijgt de keuze in het bevestigingsvenster
             * mee; bij het wijzigen komt hij terug zoals hij stond.
             */
            'published' => ['boolean'],

            // Het logo van de uitgever, met het uitsnijvenster erachter.
            ...$this->logoRegels(),

            'title_nl' => ['required', 'string', 'max:120'],
            'title_en' => ['nullable', 'string', 'max:120'],

            // Een eigennaam, dus maar één keer. Zie de migratie.
            'issuer' => ['required', 'string', 'max:120'],

            'issued_on' => ['required', 'date_format:Y-m'],

            /*
             * Leeg betekent "verloopt niet", en dat is een geldig
             * antwoord -- lang niet elk certificaat kent een
             * vervaldatum. `after_or_equal` vangt de omgekeerde volgorde
             * af: zonder die regel kun je er een invoeren die verliep
             * voordat hij behaald werd.
             */
            'expires_on' => ['nullable', 'date_format:Y-m', 'after_or_equal:issued_on'],

            'credential_id' => ['nullable', 'string', 'max:120'],

            // Ruim, maar dit is de tekst in het detailvenster en geen
            // artikel: het scherm zegt erbij dat kort beter leest.
            'body_nl' => ['nullable', 'string', 'max:2000'],
            'body_en' => ['nullable', 'string', 'max:2000'],

            /*
             * Zet de browser als de klant op "Vertaal automatisch"
             * drukte en daarna niets meer in de Engelse velden wijzigde.
             * Het is dus een mededeling en geen instelling.
             */
            'machine_translated' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'logo' => __('logo'),
            'title_nl' => __('naam'),
            'title_en' => __('Engelse naam'),
            'issuer' => __('uitgever'),
            'issued_on' => __('behaald in'),
            'expires_on' => __('geldig tot'),
            'credential_id' => __('certificaatnummer'),
            'body_nl' => __('toelichting'),
            'body_en' => __('Engelse toelichting'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expires_on.after_or_equal' => __('De geldigheid kan niet aflopen voordat je het certificaat haalde.'),
            ...$this->logoBerichten(),
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
            /*
             * Standaard aan als het veld er niet bij zit. Dat is
             * dezelfde keuze als de standaardwaarde in de migratie, en
             * hij is bewust die kant op: een certificaat dat je invoert
             * wil je op je website hebben.
             */
            'published' => $this->boolean('published', true),
            'title_nl' => $this->string('title_nl')->trim()->toString(),
            'title_en' => $this->tekst('title_en'),
            'issuer' => $this->string('issuer')->trim()->toString(),
            'issued_on' => $this->maand('issued_on'),
            'expires_on' => $this->maand('expires_on'),
            'credential_id' => $this->tekst('credential_id'),
            'body_nl' => $this->tekst('body_nl'),
            'body_en' => $this->tekst('body_en'),
        ];
    }

    /** Heeft de klant deze Engelse tekst door de vertaaldienst laten maken? */
    public function automatischVertaald(): bool
    {
        return $this->boolean('machine_translated');
    }
}
