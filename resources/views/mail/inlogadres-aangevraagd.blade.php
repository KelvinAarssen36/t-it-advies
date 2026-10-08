{{--
    De waarschuwing naar het oude adres, meteen bij de aanvraag. De knop
    breekt de wijziging af, en diezelfde link draait hem later terug als
    hij toch is doorgevoerd -- zie EmailChangeController::revert().

    **Bewaar deze mail** is hier geen beleefdheidsfrase: dit is de enige
    plek waar de herstellink staat. Zie InlogadresGewijzigdMail voor
    waarom er geen tweede komt.

    Zie docs/security/inlogadres-wijzigen.md.
--}}
<x-mail::message>
# {{ __('Er is een ander inlogadres aangevraagd') }}

{{ __('Hallo :naam,', ['naam' => $naam]) }}

{{ __('Er is zojuist gevraagd om het inlogadres van je portaal te wijzigen naar:') }}

<x-mail::panel>
{{ $nieuwAdres }}
</x-mail::panel>

{{ __('Er is nog niets veranderd. Dat gebeurt pas als die nieuwe postbus de bevestigingslink opent.') }}

## {{ __('Was jij dit niet?') }}

{{ __('Druk dan op de knop hieronder. Die breekt de wijziging af -- en mocht hij ondertussen toch zijn doorgevoerd, dan zet dezelfde knop je oude adres terug.') }}

<x-mail::button :url="$afbreekLink" color="error">
{{ __('Nee, draai dit terug') }}
</x-mail::button>

{{ __('**Bewaar deze mail.** Deze knop blijft twee weken werken en is de enige weg terug als je straks niet meer binnenkomt.') }}

{{ __('Wijzig daarna ook je wachtwoord: wie dit heeft aangevraagd, kende het.') }}

{{ __('Groet') }},<br>
{{ config('app.name') }}
</x-mail::message>
