<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Website\Concerns\SchoneVelden;
use App\Models\FooterSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * De bedrijfsgegevens in de voet: KvK en btw.
 *
 * **Geen vertaling.** Een nummer is in elke taal hetzelfde, en een
 * vertaalknop erboven zou suggereren dat er iets te vertalen valt.
 *
 * **Het KvK-nummer wordt geteld en niet alleen gemeten.** Een KvK-nummer
 * heeft acht cijfers. Staan er zeven, dan klopt het niet, en dan staat er
 * straks een onwaarheid onder het kopje "KvK" op de site van de eigenaar --
 * precies het soort bewering dat niemand controleert omdat het er
 * officieel uitziet. De spaties mogen blijven staan: hoe hij het nummer
 * schrijft is aan hem, hoeveel cijfers erin zitten niet.
 *
 * **Het btw-nummer juist niet.** Dat heeft per land een andere vorm, en een
 * regel die alleen Nederlandse nummers toelaat is een belofte die we niet
 * kunnen waarmaken zodra hij over de grens factureert.
 */
class FooterBedrijfRequest extends FormRequest
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
            'kvk' => ['nullable', 'string', 'max:'.FooterSetting::NUMMER_MAX],
            'vat' => ['nullable', 'string', 'max:'.FooterSetting::NUMMER_MAX],
        ];
    }

    /**
     * Het aantal cijfers in het KvK-nummer.
     *
     * Als losse controle en niet als `regex`, omdat de melding dan kan
     * zeggen hoeveel cijfers hij er nú heeft staan. "Dit veld is ongeldig"
     * laat iemand drie keer tellen.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /*
             * **Eerst kijken of er wel tekst binnenkwam.** Laravel draait
             * een `after`-haak ook als de regels ervóór al zijn gevallen.
             * Stuurde iemand `kvk[]=1`, dan maakte `tekst()` daar een
             * string van een array van -- een PHP-waarschuwing, en die
             * wordt in dit project een 500. Nu valt het netjes terug op de
             * gewone foutmelding.
             */
            if (! is_string($this->input('kvk')) && $this->input('kvk') !== null) {
                return;
            }

            $ruw = $this->tekst('kvk');

            if ($ruw === null) {
                return;
            }

            $cijfers = (string) preg_replace('/\D/', '', $ruw);

            if (mb_strlen($cijfers) === FooterSetting::KVK_CIJFERS) {
                return;
            }

            $validator->errors()->add('kvk', (string) __(
                'Een KvK-nummer heeft :hoort cijfers; hier staan er :nu. Spaties mag je laten staan.',
                [
                    'hoort' => FooterSetting::KVK_CIJFERS,
                    'nu' => mb_strlen($cijfers),
                ],
            ));
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kvk' => __('KvK-nummer'),
            'vat' => __('btw-nummer'),
        ];
    }

    /**
     * De gevalideerde velden als modelvelden.
     *
     * @return array<string, mixed>
     */
    public function gegevens(): array
    {
        return [
            'kvk' => $this->tekst('kvk'),
            'vat' => $this->tekst('vat'),
        ];
    }
}
