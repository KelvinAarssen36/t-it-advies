<?php

namespace App\Http\Requests;

use App\Rules\TurnstileRule;
use App\Support\Contact\Contactformulier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validatie van het contactformulier.
 *
 * Dit is de plek waar de server het laatste woord heeft. De frontend mag
 * meedenken, maar niets hier is optioneel omdat de browser het al
 * gecontroleerd zou hebben.
 *
 * **De regels staan niet in deze klasse maar in
 * [`Contactformulier`](../../Support/Contact/Contactformulier.php)**, want
 * de eigenaar bepaalt zelf welke velden er staan en welke moeten. Wat hier
 * overblijft is wat níet van hem is: de verificatie van Cloudflare en de
 * controle of de instellingen onderweg zijn veranderd.
 *
 * De honeypot zit niet in deze regels: die wordt afgehandeld door de
 * middleware van spatie/laravel-honeypot, voordat we hier zijn.
 */
class ContactRequest extends FormRequest
{
    /**
     * Het formulier zoals het op dit moment is ingesteld.
     *
     * **Één exemplaar voor dit hele verzoek.** Zonder dit loste de
     * validatie er één op, `attributes()` een tweede en de controller een
     * derde. Dat kostte een dubbele query, maar erger was dat validatie en
     * opslag dan met twee verschillende momentopnamen werkten: de ene
     * controleert welke velden mogen, de andere schrijft op welke velden
     * aanstonden. Nu kunnen die twee niet uiteenlopen.
     *
     * **Bewust hier en niet als `scoped()` in de container.** Een gebonden
     * exemplaar blijft in de tests het hele testgeval leven, en dan ziet
     * een tweede inzending na een gewijzigde instelling nog de oude
     * momentopname. Dat is precies de stilte die we niet willen. Dit is
     * per verzoek, en een verzoek duurt kort.
     */
    private ?Contactformulier $formulier = null;

    public function authorize(): bool
    {
        return true;
    }

    public function formulier(): Contactformulier
    {
        return $this->formulier ??= app(Contactformulier::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            ...$this->formulier()->regels(),

            /*
             * Via `veld()` en niet met de regels hier uitgeschreven. Daar
             * staat waarom dat uitmaakt: met `nullable` was de botcheck te
             * omzeilen door het token weg te laten.
             */
            'cf-turnstile-response' => TurnstileRule::veld(),

            /*
             * De vingerafdruk van de instellingen op het moment dat het
             * formulier werd getekend. Zie `after()`.
             */
            'instellingen' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * Een verouderd formulier is een validatiefout en geen mededeling.
     *
     * De eigenaar kan een veld aanzetten terwijl een bezoeker zit te
     * typen. Dan mist de inzending dat veld, geeft de validatie een
     * foutmelding bij een veld dat niet op het scherm staat, en kijkt de
     * bezoeker naar een formulier dat niet verstuurt en nergens rood is.
     * Dat is de stilste storing die er bestaat.
     *
     * **De eerste oplossing maakte er een stillere van.** De controller
     * stuurde een `status`-melding terug, en dat is hetzelfde kanaal als
     * een geslaagde inzending: de bezoeker kreeg een groen vinkje met
     * "Aanvraag gelukt", het formulier verdween met zijn tekst erin, en er
     * was niets opgeslagen en niets verstuurd.
     *
     * Als validatiefout gedraagt het zich meteen goed, zonder dat daar een
     * tweede mechanisme voor nodig is:
     *
     * - het is geen succes, dus er komt geen bevestigingsvak;
     * - Inertia bewaart de ingevulde waarden bij een foutantwoord, dus de
     *   bezoeker houdt zijn bericht;
     * - het formulier wordt opnieuw getekend met de velden die nu gelden,
     *   inclusief een verse vingerafdruk, dus opnieuw versturen lukt;
     * - `@error` op het formulier haalt een vers Turnstile-token op.
     *
     * De sleutel is `instellingen`, het verborgen veld waar het om gaat.
     * Het formulier toont die melding als een waarschuwing bovenaan en niet
     * bij een veld, want er is geen veld dat de bezoeker kan verbeteren.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $meegestuurd = $this->string('instellingen')->toString();

                if (blank($meegestuurd) || $meegestuurd === $this->formulier()->versie()) {
                    return;
                }

                $validator->errors()->add('instellingen', __(
                    'Het formulier is net aangepast terwijl je aan het typen was. Je tekst staat er nog -- kijk je even na of alles klopt en verstuur het opnieuw?'
                ));
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->formulier()->attributen(),
            'cf-turnstile-response' => __('verificatie'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            /*
             * Het onderwerp is twee velden -- een keuze en een eigen tekst
             * -- en die heten voor de bezoeker allebei "onderwerp". Zonder
             * deze regel maakt Laravel daar "het onderwerp is verplicht
             * wanneer onderwerp niet aanwezig is" van, en dat is geen
             * Nederlands.
             */
            'subject_text.required_without' => __('Kies een onderwerp of typ er zelf een.'),

            /*
             * Het tokenveld is nu verplicht, en de standaardmelding
             * daarvoor ("het veld verificatie is verplicht") zegt een
             * bezoeker niets -- hij heeft dat veld nooit gezien, want
             * Cloudflare vult het zelf in. Ontbreekt het, dan is de widget
             * niet geladen, en dan is verversen het enige dat helpt.
             */
            'cf-turnstile-response.required' => __('De verificatie kon niet worden geladen. Ververs de pagina en probeer het opnieuw.'),
        ];
    }
}
