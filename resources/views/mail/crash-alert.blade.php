{{--
    De crashmelding. Karig met opzet: deze mail zegt dát er iets omviel en
    waar je moet kijken, niet wát er precies gebeurde. Een stacktrace in een
    postbus is een stacktrace buiten de applicatie.

    Zie docs/operations/monitoring.md.
--}}
<x-mail::message>
# {{ __('Er ging iets mis') }}

{{ __('De applicatie liep vast op een verzoek. Dit is de eerste melding hierover; herhalingen van dezelfde fout worden een tijdje onderdrukt.') }}

<x-mail::panel>
**{{ __('Soort') }}:** {{ $soort }}

**{{ __('Plek') }}:** `{{ $plek }}`

@if ($adres)
**{{ __('Verzoek') }}:** `{{ $adres }}`
@endif

@if ($gebruiker)
**{{ __('Gebruiker') }}:** #{{ $gebruiker }}
@endif
</x-mail::panel>

{{ __('De melding:') }}

<x-mail::panel>
{{ $melding }}
</x-mail::panel>

{{ __('De volledige stacktrace staat in storage/logs/laravel.log op de server. Die staat bewust niet in deze mail.') }}

{{ __('Groet') }},<br>
{{ config('app.name') }}
</x-mail::message>
