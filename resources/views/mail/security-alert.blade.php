@component('mail::message')
# Beveiligingsmelding

Sinds {{ $since->toDateTimeString() }} zijn er signalen die aandacht vragen.

@foreach ($anomalies as $anomaly)
## {{ $anomaly->title }}

**{{ $anomaly->count }}** keer gezien, de drempel staat op {{ $anomaly->threshold }}.

@if ($anomaly->details !== [])
@foreach ($anomaly->details as $detail)
- {{ $detail }}
@endforeach
@endif

@endforeach

@component('mail::button', ['url' => $logUrl])
Bekijk het beveiligingslogboek
@endcomponent

Deze mail bevat bewust geen e-mailadressen of mailinhoud. Alle details staan
in het logboek, achter inloggen en tweestapsverificatie.

@endcomponent
