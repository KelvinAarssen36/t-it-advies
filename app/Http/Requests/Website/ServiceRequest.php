<?php

namespace App\Http\Requests\Website;

use App\Enums\ServiceIcon;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wat er in een dienst mag staan.
 *
 * `authorize()` geeft `true` terug: wie hier mag komen staat op de route
 * (`can:manage portal`), en die controle twee keer doen betekent dat hij
 * op één van de twee plekken ooit verkeerd komt te staan.
 *
 * **Het tweetalige patroon**: het Nederlandse veld is `required` met een
 * lengte, het Engelse is precies hetzelfde maar `nullable`. Geen regel
 * die ze aan elkaar koppelt -- het Engels mag achterlopen, en de website
 * lost dat op met terugvallen. Zie App\Models\Service.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
class ServiceRequest extends FormRequest
{
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
            'icon' => ['required', Rule::enum(ServiceIcon::class)],
            'published' => ['boolean'],

            'title_nl' => ['required', 'string', 'max:80'],
            'title_en' => ['nullable', 'string', 'max:80'],

            /*
             * De samenvatting is verplicht en het verhaal niet. Een kaart
             * met alleen een titel is een lege kaart; een kaart zonder
             * lang verhaal is gewoon een kaart die niet opengaat.
             */
            'summary_nl' => ['required', 'string', 'max:300'],
            'summary_en' => ['nullable', 'string', 'max:300'],

            'body_nl' => ['nullable', 'string', 'max:5000'],
            'body_en' => ['nullable', 'string', 'max:5000'],

            /*
             * De expertisepunten komen als lijst mee, ook als hij leeg
             * is: `present` en niet `nullable`, want een ontbrekende
             * sleutel zou "laat maar staan" betekenen en een lege lijst
             * "haal ze allemaal weg". Dat verschil moet blijven bestaan.
             */
            'punten' => ['present', 'array', 'max:'.Service::PUNTEN_MAXIMUM],
            'punten.*.text_nl' => ['required', 'string', 'max:60'],
            'punten.*.text_en' => ['nullable', 'string', 'max:60'],

            'machine_translated' => ['boolean'],
        ];
    }

    /**
     * De namen zoals ze in een foutmelding horen te staan.
     *
     * Kleine letters en gewoon Nederlands, want ze komen midden in een
     * zin terecht: "Het veld Engelse titel mag niet meer dan ...".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'icon' => __('pictogram'),
            'title_nl' => __('titel'),
            'title_en' => __('Engelse titel'),
            'summary_nl' => __('korte tekst'),
            'summary_en' => __('Engelse korte tekst'),
            'body_nl' => __('uitgebreide tekst'),
            'body_en' => __('Engelse uitgebreide tekst'),
            'punten' => __('expertisepunten'),
            'punten.*.text_nl' => __('expertisepunt'),
            'punten.*.text_en' => __('Engels expertisepunt'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'punten.max' => __(
                'Meer dan :aantal expertisepunten passen er niet onder een dienst.',
                ['aantal' => Service::PUNTEN_MAXIMUM],
            ),
            'punten.*.text_nl.required' => __('Vul dit expertisepunt in, of haal het weg.'),
        ];
    }

    /**
     * De velden van de dienst zelf, klaar om weg te schrijven.
     *
     * De punten zitten hier niet bij: die zijn eigen rijen en worden
     * apart bijgewerkt. Zie ServiceController::bewaarPunten().
     *
     * @return array<string, mixed>
     */
    public function gegevens(): array
    {
        return [
            'icon' => $this->string('icon')->toString(),

            /*
             * Standaard aan als het veld er niet bij zit. Dezelfde keuze
             * als de standaardwaarde in de migratie, en bewust die kant
             * op: een dienst die je invoert wil je op je website hebben.
             */
            'published' => $this->boolean('published', true),

            'title_nl' => $this->string('title_nl')->trim()->toString(),
            'title_en' => $this->tekst('title_en'),
            'summary_nl' => $this->string('summary_nl')->trim()->toString(),
            'summary_en' => $this->tekst('summary_en'),
            'body_nl' => $this->tekst('body_nl'),
            'body_en' => $this->tekst('body_en'),
        ];
    }

    /**
     * De expertisepunten zoals ze binnenkwamen, op volgorde.
     *
     * @return array<int, array{text_nl: string, text_en: string|null}>
     */
    public function punten(): array
    {
        /** @var array<int, array<string, mixed>> $punten */
        $punten = $this->input('punten', []);

        return array_values(array_map(
            fn (array $punt) => [
                'text_nl' => trim((string) ($punt['text_nl'] ?? '')),
                'text_en' => blank($punt['text_en'] ?? null)
                    ? null
                    : trim((string) $punt['text_en']),
            ],
            $punten,
        ));
    }

    public function automatischVertaald(): bool
    {
        return $this->boolean('machine_translated');
    }

    /**
     * Een leeg tekstveld wordt `null` en geen lege string.
     *
     * Dat verschil bepaalt of de website er iets neerzet: bij `null`
     * blijft het weg, bij `""` staat er een leeg element. Zie
     * docs/development/testen.md.
     */
    private function tekst(string $veld): ?string
    {
        $waarde = $this->string($veld)->trim()->toString();

        return $waarde === '' ? null : $waarde;
    }
}
