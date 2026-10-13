{{--
    De bevestiging aan de bezoeker.

    **De tekst is van de eigenaar** en komt uit `contact_settings`; hij
    staat hier dus niet in `__()`. Wat er wél omheen staat -- de aanhef, het
    kopje boven de samenvatting -- is van ons en gaat wel door de vertaling.

    `nl2br` op de tekst: de eigenaar typt hem in een gewoon tekstvak, en
    witregels die hij daar maakt horen in de mail te blijven staan. `e()`
    eromheen, want die tekst gaat als HTML de mail in.

    Zie docs/architecture/modules/contact.md.
--}}
<x-mail::message>
# {{ __('Bedankt voor je bericht') }}

{{ __('Beste :naam,', ['naam' => $naam]) }}

{!! nl2br(e($tekst)) !!}

<x-mail::panel>
**{{ __('Je onderwerp') }}:** {{ $samenvatting }}
</x-mail::panel>

{{ __('Met vriendelijke groet,') }}
@T IT Advies
</x-mail::message>
