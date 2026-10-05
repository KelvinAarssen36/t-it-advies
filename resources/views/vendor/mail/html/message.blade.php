{{--
    Het raamwerk van elke mail: kop, inhoud, naschrift, voettekst.

    **Gepubliceerd om één reden: de voettekst.** Die van Laravel vertaalt de
    Engelse zin "All rights reserved." -- een Engelse brontekst dus. Onze
    vertaling loopt de andere kant op: Nederlands is de sleuteltaal en
    `lang/en.json` maakt er Engels van. Die zin bleef daardoor in élke mail
    Engels staan, ook in de Nederlandse. Zie
    docs/architecture/vertalingen.md.

    Hem in `lang/nl.json` vertalen zou ook werken, maar dat bestand gaat
    via de gedeelde Inertia-props naar de browser: dan draagt elke pagina
    van de site een zin mee die alleen in een mail voorkomt, en de test
    `TranslationsTest::test_the_default_language_sends_nothing` legt die
    keuze juist vast. Daarom liever deze regel in ons eigen sjabloon.

    **Voor de rest is dit het sjabloon van Laravel, ongewijzigd.** Loopt
    het ooit uit de pas met een nieuwe versie, vergelijk dan met
    vendor/laravel/framework/src/Illuminate/Mail/resources/views/html/message.blade.php.

    Het beeldmerk staat in header.blade.php, de kleuren in themes/atit.css.

    Zie docs/architecture/mail-en-queues.md.
--}}
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. {{ __('Alle rechten voorbehouden.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
