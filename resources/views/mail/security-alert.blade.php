{{--
    De beveiligingsmelding aan de eigenaar.

    **Bewust karig**: deze mail zegt dát er iets opviel en waar je moet
    kijken, niet wát er precies gebeurde. Een e-mailadres of een IP-adres in
    een postbus is een gegeven buiten de applicatie, en dat is precies wat
    het beveiligingslogboek moet voorkomen.

    Zie docs/operations/monitoring.md.
--}}
<x-mail::message>
# {{ __('Beveiligingsmelding') }}

{{ __('Sinds :wanneer zijn er signalen die aandacht vragen.', ['wanneer' => $since->toDateTimeString()]) }}

@foreach ($anomalies as $anomaly)
## {{ $anomaly->title }}

{{ __('**:aantal** keer gezien, de drempel staat op :drempel.', ['aantal' => $anomaly->count, 'drempel' => $anomaly->threshold]) }}

@if ($anomaly->details !== [])
@foreach ($anomaly->details as $detail)
- {{ $detail }}
@endforeach
@endif

@endforeach

<x-mail::button :url="$logUrl">
{{ __('Bekijk het beveiligingslogboek') }}
</x-mail::button>

{{ __('Deze mail bevat bewust geen e-mailadressen of mailinhoud. Alle details staan in het logboek, achter inloggen en tweestapsverificatie.') }}
</x-mail::message>
