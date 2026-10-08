{{--
    Het bericht naar het oude adres dat de wijziging is doorgevoerd.

    Hier staat met opzet geen nieuwe herstellink; die staat in de mail van
    de aanvraag en blijft werken. Zie InlogadresGewijzigdMail voor waarom
    dat robuuster is dan een vers token.

    Zie docs/security/inlogadres-wijzigen.md.
--}}
<x-mail::message>
# {{ __('Je inlogadres is gewijzigd') }}

{{ __('Hallo :naam,', ['naam' => $naam]) }}

{{ __('De wijziging is zojuist bevestigd en doorgevoerd.') }}

<x-mail::panel>
**{{ __('Was') }}:** {{ $oudAdres }}

**{{ __('Is nu') }}:** {{ $nieuwAdres }}
</x-mail::panel>

{{ __('Vanaf nu log je in met het nieuwe adres. Dit oude adres werkt niet meer om mee in te loggen.') }}

## {{ __('Was jij dit niet?') }}

{{ __('Zoek dan de mail op met als onderwerp "Er is een ander inlogadres aangevraagd". Daar staat een knop die je oude adres terugzet; die blijft nog :dagen dagen werken.', ['dagen' => $geldigeDagen]) }}

{{ __('Wijzig daarna meteen je wachtwoord.') }}

{{ __('Groet') }},<br>
{{ config('app.name') }}
</x-mail::message>
