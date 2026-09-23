# Beslislogboek

Keuzes met een reëel alternatief, zodat de volgende die ernaar kijkt niet
opnieuw hoeft af te wegen -- of juist weet waaróm hij het anders zou doen.

Voeg een regel toe zodra je een keuze maakt waarover je langer dan vijf
minuten hebt nagedacht.

---

## 001 -- Eén Laravel-project in plaats van losse diensten

**Keuze:** alles in één Laravel-applicatie: publieke site, authenticatie en
het beveiligde gedeelte.

**Alternatief:** een aparte frontend-app met een Laravel-API erachter, of
losse diensten per onderdeel.

**Waarom:** één deploy, één set credentials, één plek om te zoeken. Met een
losse frontend zou je autorisatie en validatie twee keer bouwen en een
API-oppervlak krijgen dat je apart moet beveiligen. Het bedrijf is één site;
de complexiteit van meer zou niets opleveren.

**Terugdraaien:** goed mogelijk. Inertia-controllers zijn met beperkte moeite
om te bouwen naar API-resources.

---

## 002 -- Inertia met Vue in plaats van een SPA met een API

**Keuze:** Inertia 3 met Vue 3.

**Alternatief:** Vue met een REST- of GraphQL-API, of Livewire.

**Waarom:** Inertia geeft de ontwikkelervaring van een SPA zonder een tweede
rechtenmodel in JavaScript. Livewire viel af omdat er echte, vloeiende
animatie nodig is en het ontwerp visueel hoogwaardig moet kunnen worden;
daarvoor wil je volledige controle in de browser.

---

## 003 -- Resend als mailprovider

**Keuze:** Resend, met `MAIL_MAILER=log` lokaal.

**Alternatief:** Postmark, Mailgun, SES.

**Waarom:** eenvoudige inrichting en goed gedocumenteerde webhooks. De keuze
is bewust ondiep: alleen `ResendWebhookController` weet iets van Resend. De
rest van de applicatie praat met de mail-abstractie van Laravel.

**Terugdraaien:** eenvoudig, behalve de webhookcontroller. Zie
[mail en queues](../architecture/mail-en-queues.md).

---

## 004 -- Recovery codes gelden niet bij gevoelige acties

**Keuze:** bij een gevoelige actie wordt uitsluitend een TOTP-code van zes
cijfers geaccepteerd. Bij het inloggen mag een recovery code wél.

**Alternatief:** overal recovery codes toestaan.

**Waarom:** een recovery code bewijst niet dat je de authenticator nog hebt.
Hij kan op een briefje staan, in een screenshot, of in de mailbox die net is
overgenomen. Bij inloggen is dat acceptabel, want dan is het doel juist om
weer binnen te komen. Bij een actie die je niet kunt terugdraaien is het dat
niet.

**Gevolg:** wie zijn authenticator kwijt is kan geen gevoelige actie
uitvoeren tot 2FA opnieuw is ingesteld. Dat is bedoeld.

---

## 005 -- Turnstile klapt dicht bij twijfel

**Keuze:** geen secret buiten local en testing, of Cloudflare onbereikbaar,
betekent weigeren.

**Alternatief:** doorlaten bij een storing, zodat het formulier blijft werken.

**Waarom:** met doorlaten is de bescherming te omzeilen door Cloudflare plat
te leggen of onbereikbaar te maken. Een formulier dat tijdens een storing
even niet werkt is beter dan een formulier dat tijdens een storing
onbeschermd is.

---

## 006 -- Een eigen tabel voor beveiligingsgebeurtenissen

**Keuze:** een eigen `security_events`-tabel, plus een apart logkanaal.

**Alternatief:** spatie/laravel-activitylog, of alleen tekstlogs.

**Waarom:** we hebben precies één ding nodig dat geen enkel pakket standaard
garandeert: dat er nooit een geheim in de opslag belandt. Met een eigen,
smalle laag is die redactie af te dwingen in code én in tests. Een
algemeen activiteitenlog doet meer en garandeert dit minder.

---

## 007 -- PHPUnit, geen Pest

**Keuze:** PHPUnit 12, zoals de starter kit het levert.

**Alternatief:** omzetten naar Pest.

**Waarom:** de kit levert klasse-gebaseerde tests. Half omzetten geeft twee
stijlen naast elkaar. Overstappen kan, maar dan in één keer voor de hele
suite.

---

## 008 -- Three.js nog niet installeren

**Keuze:** niet geïnstalleerd.

**Waarom:** forse afhankelijkheid, merkbaar bundelformaat, en de meeste
effecten die 3D lijken kunnen met GSAP en CSS. Zie
[frontend en animatie](../architecture/frontend-en-animatie.md) voor de
voorwaarden waaronder je hem wél toevoegt.
