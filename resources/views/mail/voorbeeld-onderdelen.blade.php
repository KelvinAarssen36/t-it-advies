{{--
    De bouwstenen van een mail, in de huisstijl.

    **Dit is geen mail die ooit wordt verstuurd.** Het is het voorbeeld
    onder Instellingen → Weergave, zodat de eigenaar ziet hoe een knop, een
    uitgelicht vak en een tabel in zijn huisstijl staan. Die komen in zijn
    beveiligingsmeldingen voor, niet in de bevestiging aan een bezoeker --
    en de bevestiging ernaast laten zien zónder knop was misleidend, want
    dan lijkt het of er geen knopstijl bestaat.

    Hetzelfde sjabloon en hetzelfde thema als de echte mail, dus wat hier
    staat is wat er werkelijk uitgaat. Zou dit een eigen stukje HTML zijn,
    dan klopt het tot de dag dat iemand het thema aanpast.

    Het scherm zegt erbij dat dit een voorbeeld van de onderdelen is en
    geen post die je kunt krijgen. Zie MailVoorbeeldController::onderdelen()
    en docs/architecture/mail-en-queues.md.
--}}
<x-mail::message>
# {{ __('Zo zien je mails eruit') }}

{{ __('Dit is geen echte mail maar een voorbeeld van de onderdelen. Zo weet je hoe een bericht van je website eruitziet voordat er een de deur uit gaat.') }}

{{ __('Dit is een gewone alinea. De regelafstand en de breedte zijn zo gekozen dat een mail op een telefoon net zo goed leest als op een scherm.') }}

<x-mail::panel>
{{ __('Een uitgelicht vak. Hier komt iets in te staan dat je snel wil kunnen vinden, zoals het onderwerp dat een bezoeker koos.') }}
</x-mail::panel>

<x-mail::button :url="config('app.url')">
{{ __('En zo ziet een knop eruit') }}
</x-mail::button>

{{ __('Een knop staat niet in elke mail. In de bevestiging aan een bezoeker zit er geen, want daar valt niets te openen; in een melding aan jou wel, bijvoorbeeld om meteen naar je logboek te gaan.') }}

<x-mail::table>
| {{ __('Onderdeel') }} | {{ __('Waarvoor') }}                           |
| :-------------------- | :--------------------------------------------- |
| {{ __('Kop') }}       | {{ __('Je naam, in de merkkleur') }}           |
| {{ __('Tekst') }}     | {{ __('Wat je zelf schrijft') }}               |
| {{ __('Voettekst') }} | {{ __('De afsluiting onderaan elke mail') }}   |
</x-mail::table>

{{ __('Met vriendelijke groet,') }}
@T IT Advies
</x-mail::message>
