{{--
    Het buitenste raamwerk van elke mail.

    **Gepubliceerd om één reden: Laravel zegt in elke mail dat hij licht
    is.** Er staan twee metategels in met `content="light"` vast erin
    getypt. Voor het lichte thema klopt dat; voor de huisstijl -- een
    donkerblauwe mail -- is het precies verkeerd, en dat heeft twee
    gevolgen die je allebei ziet:

    1. **De schuifbalk.** Een browser tekent zijn balk naar het schema dat
       het document opgeeft. Een donkere mail die "licht" zegt krijgt dus
       een witte balk met pijltjes ernaast -- en dat is waar het voorbeeld
       onder Instellingen → Weergave in valt, want dat is een echte
       mailpagina in een `iframe`.

    2. **Mailprogramma's die zelf omkleuren.** Apple Mail en Outlook.com
       kijken naar `supported-color-schemes` om te beslissen of ze een mail
       naar donker omzetten. Zegt onze donkere mail dat hij alleen licht
       kan, dan gaat zo'n programma hem alsnog te lijf en haalt het de
       kleuren door de war.

    De stijl komt uit het thema dat op dit moment wordt gerenderd.
    `Markdown` is een singleton en `Mailable::markdownRenderer()` zet het
    thema erop vóór het renderen, dus dit is altijd het thema van déze
    mail -- ook bij de twee voorbeeldroutes, die het thema zelf meegeven.

    **Voor de rest is dit het sjabloon van Laravel, ongewijzigd.** Loopt het
    ooit uit de pas met een nieuwe versie, vergelijk dan met
    vendor/laravel/framework/src/Illuminate/Mail/resources/views/html/layout.blade.php.

    De schuifbalk zelf staat in de thema's en niet hier: zie het kopje
    erover in themes/atit.css. Zie docs/architecture/mail-en-queues.md.
--}}
@php
    $mailstijl = \App\Enums\MailStijl::vanThema(
        app(\Illuminate\Mail\Markdown::class)->getTheme(),
    );

    $kleurschema = $mailstijl->donker() ? 'dark' : 'light';
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="{{ $kleurschema }}">
<meta name="supported-color-schemes" content="{{ $kleurschema }}">
<style>
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
