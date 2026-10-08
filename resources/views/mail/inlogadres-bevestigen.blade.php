{{--
    De bevestigingslink naar het nieuwe inlogadres. Dit is de grendel:
    zolang deze link niet is geopend verandert er niets aan het account.

    Zie docs/security/inlogadres-wijzigen.md.
--}}
<x-mail::message>
# {{ __('Bevestig je nieuwe inlogadres') }}

{{ __('Hallo :naam,', ['naam' => $naam]) }}

{{ __('Je hebt gevraagd om voortaan met dit adres in te loggen op je portaal. Klik op de knop om dat te bevestigen.') }}

<x-mail::panel>
**{{ __('Nu') }}:** {{ $oudAdres }}

**{{ __('Straks') }}:** {{ $nieuwAdres }}
</x-mail::panel>

<x-mail::button :url="$link">
{{ __('Ja, dit wordt mijn inlogadres') }}
</x-mail::button>

{{ __('Deze link is :minuten minuten geldig en werkt één keer.', ['minuten' => $geldigeMinuten]) }}

{{ __('Zolang je hem niet opent verandert er niets: je logt gewoon in met je huidige adres. Doe je niets, dan vervalt de aanvraag vanzelf.') }}

{{ __('Heb je dit niet aangevraagd? Negeer deze mail dan en wijzig je wachtwoord. Er is dan iemand die bij je portaal kan.') }}

{{ __('Groet') }},<br>
{{ config('app.name') }}
</x-mail::message>
