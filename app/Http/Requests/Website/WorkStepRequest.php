<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wat er in een stap van de werkwijze mag staan.
 *
 * `authorize()` geeft `true` terug: wie hier mag komen staat op de route
 * (`can:manage portal`), en die controle twee keer doen betekent dat hij
 * op één van de twee plekken ooit verkeerd komt te staan.
 *
 * **Het tweetalige patroon**: het Nederlandse veld is `required` met een
 * lengte, het Engelse is precies hetzelfde maar `nullable`. Geen regel die
 * ze aan elkaar koppelt -- het Engels mag achterlopen, en de website lost
 * dat op. Zie App\Models\WorkStep.
 *
 * **Er is geen veld voor het nummer.** Dat volgt uit de volgorde; zie de
 * toelichting bij het model.
 *
 * Zie docs/architecture/modules/werkwijze.md.
 */
class WorkStepRequest extends FormRequest
{
    /** Zo lang mag de zin onder de titel zijn. */
    public const SAMENVATTING_MAX = 300;

    /** En zo lang de regel over wat de klant overhoudt. */
    public const RESULTAAT_MAX = 160;

    /** Het hele verhaal op /werkwijze. */
    public const VERHAAL_MAX = 4000;

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
            'published' => ['boolean'],

            'title_nl' => ['required', 'string', 'max:80'],
            'title_en' => ['nullable', 'string', 'max:80'],

            /*
             * De samenvatting is verplicht: een stap met alleen een titel
             * zegt niets over wat er in die stap gebeurt, en dan is de
             * hele werkwijze een rijtje woorden.
             */
            'summary_nl' => ['required', 'string', 'max:'.self::SAMENVATTING_MAX],
            'summary_en' => ['nullable', 'string', 'max:'.self::SAMENVATTING_MAX],

            /*
             * De duur is kort gehouden met opzet. Veertig tekens is ruim
             * genoeg voor "1-2 weken" of "doorlopend", en te weinig voor
             * een zin -- die hoort in de samenvatting.
             */
            'duration_nl' => ['nullable', 'string', 'max:40'],
            'duration_en' => ['nullable', 'string', 'max:40'],

            'result_nl' => ['nullable', 'string', 'max:'.self::RESULTAAT_MAX],
            'result_en' => ['nullable', 'string', 'max:'.self::RESULTAAT_MAX],

            'body_nl' => ['nullable', 'string', 'max:'.self::VERHAAL_MAX],
            'body_en' => ['nullable', 'string', 'max:'.self::VERHAAL_MAX],

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
            'title_nl' => __('titel'),
            'title_en' => __('Engelse titel'),
            'summary_nl' => __('korte tekst'),
            'summary_en' => __('Engelse korte tekst'),
            'duration_nl' => __('duur'),
            'duration_en' => __('Engelse duur'),
            'result_nl' => __('resultaat'),
            'result_en' => __('Engels resultaat'),
            'body_nl' => __('uitgebreide tekst'),
            'body_en' => __('Engelse uitgebreide tekst'),
        ];
    }

    /**
     * De velden, klaar om weg te schrijven.
     *
     * @return array<string, mixed>
     */
    public function gegevens(): array
    {
        return [
            /*
             * Standaard aan als het veld er niet bij zit. Dezelfde keuze
             * als de standaardwaarde in de migratie, en bewust die kant
             * op: een stap die je invoert wil je op je website hebben.
             */
            'published' => $this->boolean('published', true),

            'title_nl' => $this->string('title_nl')->trim()->toString(),
            'title_en' => $this->tekst('title_en'),
            'summary_nl' => $this->string('summary_nl')->trim()->toString(),
            'summary_en' => $this->tekst('summary_en'),
            'duration_nl' => $this->tekst('duration_nl'),
            'duration_en' => $this->tekst('duration_en'),
            'result_nl' => $this->tekst('result_nl'),
            'result_en' => $this->tekst('result_en'),
            'body_nl' => $this->tekst('body_nl'),
            'body_en' => $this->tekst('body_en'),
        ];
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
