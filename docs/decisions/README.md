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

---

## 009 -- Gebruikersbeheer als eerste echte gevoelige actie

**Keuze:** rollen wijzigen en accounts verwijderen op `/admin/users` zijn de
eerste handelingen die achter `2fa.confirm` zijn gezet.

**Alternatief:** het mechanisme ongebruikt laten staan tot er "echt iets
gevaarlijks" zou komen.

**Waarom:** een beveiligingsmechanisme dat nergens wordt gebruikt, werkt in
de praktijk niet. Het is nooit tegen een echte controller aan gehouden en
niemand merkt dat het stuk is. Bij het aansluiten bleek dat meteen: de
middleware onthield de URL van het `DELETE`-verzoek, en na het bevestigen
kwam de gebruiker met een GET op die route uit -- een 405. Dat was onzichtbaar
zolang alleen een GET-testroute de middleware raakte.

**Terugdraaien:** de routes zijn los te koppelen zonder dat het mechanisme
verandert.

---

## 010 -- Zelfbescherming in gebruikersbeheer, geen policy

**Keuze:** je kunt je eigen account niet aanpassen of verwijderen, en de
laatste beheerder blijft staan. Allebei afgedwongen in `UserController`, met
een melding op het scherm in plaats van een 403.

**Alternatief:** een `UserPolicy`, of helemaal geen grens en vertrouwen op de
oplettendheid van de beheerder.

**Waarom:** het is geen autorisatievraag. De uitvoerder _mag_ het -- hij heeft
`manage users` en een verse code. Het is een ongelukkenrem, en die hoort een
begrijpelijke melding te geven en geen 403. Een policy zou suggereren dat het
om rechten gaat, en dan gaat iemand later de verkeerde knop omzetten.

De laatste-beheerdergrens is de belangrijkste van de twee: zonder die grens
maakt één klik de applicatie onbeheerbaar, en er is geen scherm om dat te
herstellen. Dan moet je de seeder of de database in.

**Terugdraaien:** eenvoudig, maar bedenk dan eerst hoe je uit de situatie
komt die je daarmee mogelijk maakt.

---

## 011 -- Alarmering per e-mail, met drempel en afkoeltijd

**Keuze:** een geplande taak die elk uur in het logboek kijkt en per e-mail
meldt. Met een drempel per signaal en een afkoeltijd van drie uur.

**Alternatief:** meteen melden bij elke mislukte poging, of een externe
dienst zoals Sentry of Better Stack.

**Waarom:** melden bij elke mislukte poging levert ruis op -- mensen typen
hun wachtwoord verkeerd -- en ruis leert de ontvanger meldingen te negeren.
De drempel maakt van "er gebeurde iets" een "dit is meer dan normaal". De
afkoeltijd voorkomt dat één aanval van drie uur ook drie uur lang elk uur
mailt.

Geen externe dienst, omdat de gegevens die ertoe doen al in onze eigen tabel
staan en dit geen nieuwe leverancier, nieuw contract of nieuwe uitgaande
verbinding kost. Wordt de behoefte groter (escalatie, diensten, Slack), dan
is een externe dienst het overwegen waard.

**Bewust weggelaten uit de mail:** e-mailadressen van gebruikers. De melding
komt terecht in een postbus die minder goed is beveiligd dan de applicatie
zelf; wie details nodig heeft logt in.

**Terugdraaien:** de scanner staat los van het commando en is elders te
gebruiken.

---

## 012 -- De publieke site staat altijd in het donkere thema

**Keuze:** `PublicLayout` zet zelf `dark` op zijn wortel. De openbare pagina's
zijn navy, ook voor een bezoeker die zijn systeem op licht heeft staan. Het
beheergedeelte volgt de voorkeur van de gebruiker wél.

**Alternatief:** de publieke site laten meebewegen met de systeemvoorkeur, en
dus twee volwaardige versies onderhouden.

**Waarom:** Midnight Navy is volgens de huisstijl het fundament van de site en
draagt 55 tot 65 procent van het beeld. Een lichte versie van dezelfde pagina
is dan geen instelling maar een tweede ontwerp -- met eigen contrastvragen,
eigen schermafdrukken en een eigen kans om scheef te groeien. Voor een
marketingsite is een vaste uitstraling het punt.

Voor het beheergedeelte ligt dat anders: daar zit je soms een uur in, en dan
is de voorkeur van de gebruiker belangrijker dan de merkbeleving.

**Gevolg:** wil je op de publieke site een licht vlak, bouw dat dan als een
lichte sectie binnen het donkere geheel, en draai niet het thema per sectie
om. Dat laatste breekt de `dark:`-varianten van de componenten erin.

**Terugdraaien:** één klasse in `PublicLayout`. Reken er dan wel op dat elke
sectie op contrast nagelopen moet worden.
