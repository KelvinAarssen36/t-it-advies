{{--
    Het bericht uit het contactformulier, naar de eigenaar.

    Deze mail staat in zíjn taal en niet in die van de bezoeker; welke taal
    de bezoeker gebruikte staat er als regel bij, zodat hij weet in welke
    taal hij moet antwoorden. Zie ContactController::verstuur().

    Zie docs/architecture/modules/contact.md.
--}}
<x-mail::message>
# {{ __('Nieuw bericht via je website') }}

<x-mail::panel>
{{ $body }}
</x-mail::panel>

**{{ __('Van') }}:** {{ $senderName }} &lt;{{ $senderEmail }}&gt;

@if ($senderCompany)
**{{ __('Bedrijf') }}:** {{ $senderCompany }}
@endif

@if ($senderPhone)
**{{ __('Telefoon') }}:** {{ $senderPhone }}
@endif

**{{ __('Onderwerp') }}:** {{ $senderSubject }}

@if ($senderLocale !== 'nl')
**{{ __('Taal van de bezoeker') }}:** {{ __('Engels') }}
@endif

{{ __('Je kunt rechtstreeks op deze mail antwoorden; het antwoord gaat naar :adres.', ['adres' => $senderEmail]) }}

{{ __('De aanvraag staat ook in je portaal, onder Beheer → Aanvragen.') }}
</x-mail::message>
