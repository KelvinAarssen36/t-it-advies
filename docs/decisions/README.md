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
`manage portal` en een verse code. Het is een ongelukkenrem, en die hoort een
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

---

## 013 -- Registratie uit, accounts via de opdrachtregel

**Keuze:** `Features::registration()` staat uit. Accounts maak je met
`php artisan user:create`.

**Alternatief:** registratie aan laten staan en afschermen met een
uitnodigingscode, of een scherm voor gebruikersbeheer met een
aanmaakformulier.

**Waarom:** deze site heeft één gebruiker, de eigenaar. Een open
registratieformulier geeft vreemden een account op een applicatie die verder
alleen voor hem is, en elk account dat je niet nodig hebt is een ingang die
je wel moet bewaken. Een uitnodigingscode is een tweede mechanisme dat
onderhouden en getest moet worden voor iets wat één keer gebeurt.

Het wachtwoord is in dat commando een vraag en geen optie: opties belanden in
de shell-geschiedenis en zijn op een gedeelde server zichtbaar in `ps`.

Het aangemaakte account krijgt meteen een geverifieerd e-mailadres. Zonder
dat komt de eigenaar niet in het beheergedeelte, en op het moment dat je dit
commando draait staat de mailprovider vaak nog niet ingesteld.

**Terugdraaien:** de feature terugzetten in `config/fortify.php`. Dan faalt
`RegistrationTest` -- met opzet, zodat het een beslissing blijft. Je hebt dan
ook `auth/Register.vue`, `Fortify::registerView` en een `CreatesNewUsers`-actie
weer nodig; die staan in de geschiedenis van deze commit.

---

## 014 -- MyMemory voor het automatisch vertalen, zonder account

**Keuze:** MyMemory, via een gewone HTTP-aanroep, achter het eigen contract
`App\Support\Translation\Vertaler`.

**Alternatieven:** DeepL, Google Cloud Translation, een taalmodel, of
LibreTranslate zelf hosten.

**Waarom:** de harde eis werd **geen account en geen sleutel**. Een knop die
pas werkt nadat de eigenaar zich ergens heeft aangemeld en een sleutel in
een configuratiebestand heeft gezet, is voor hem geen knop -- en het is ook
niet iets wat je bij een oplevering wilt overdragen. MyMemory staat open:
5.000 tekens per dag anoniem, 50.000 als je een adres meestuurt, zonder
registratie.

De kwaliteit is een stap minder dan die van DeepL, en dat is een bewuste
ruil. Wat de knop oplevert is een startpunt; negen van de tien keer
herschrijft de klant het toch in eigen woorden.

Dit was eerst wél DeepL. Dat viel af op precies dat ene punt: de gratis
laag vraagt een account. Google viel af op hetzelfde plus een
service-account. Een taalmodel is beter in toon maar vraagt ook een sleutel
en kost per aanroep. LibreTranslate zelf hosten vraagt Docker en een paar
gigabyte aan modellen -- dat is een server om te onderhouden voor een knop
die af en toe wordt gebruikt.

**Terugdraaien:** eenvoudig, en dat is bewezen. Alleen `MyMemoryVertaler`
weet iets van de dienst; de overstap vanaf DeepL was één nieuwe klasse en
één regel in `AppServiceProvider`. Wil je ooit betere kwaliteit en is een
account dan geen bezwaar, dan is DeepL de voor de hand liggende stap.

**Let op:** met `TRANSLATE_ENABLED=false` valt de applicatie terug op
`GeenVertaler` en verdwijnt de knop uit het scherm. Dat is ook de stand in
elke test. Zie
[automatisch vertalen](../architecture/automatisch-vertalen.md).

---

## 015 -- Toch een geüpload logo bij een ervaring, naast het pictogram

**Keuze:** de klant kan per ervaring een logo uploaden. Staat er geen, dan
gebruikt de tijdlijn een pictogram uit de vaste set.

**Alternatief:** alleen pictogrammen -- de keuze die hier eerst stond.

**Waarom:** de opdrachtgever wilde de echte beeldmerken van de organisaties
op zijn tijdlijn, en dat is bij een loopbaan een redelijke wens: het maakt
de lijst in één oogopslag herkenbaar op een manier die tien algemene
pictogrammen niet kunnen.

De bezwaren die bij de eerdere keuze hoorden -- opslag, validatie,
opruimen, en of het wel werkt in productie -- bleken bij nader inzien geen
argumenten tégen maar een lijst van wat er te regelen was. Elk punt heeft
nu een antwoord, met een test erbij: de bestandsnaam van de klant wordt
niet gebruikt, het beeld wordt door GD heen opnieuw opgebouwd, de
afmetingen zijn begrensd zodat het geheugen niet volloopt, en het bestand
gaat mee als de ervaring wordt verwijderd.

Het pictogram **blijft** bestaan en verdwijnt niet naar de achtergrond. Een
logo heb je of je hebt het niet, en tot die tijd hoort er iets te staan.
Zonder die terugval zou een ervaring zonder logo een gat in de lijn zijn.

Het bezwaar dat aangeleverde logo's in willekeurige kwaliteit binnenkomen
blijft staan; dat is nu een kwestie van wat de klant aanlevert en niet meer
van wat de applicatie ermee doet. Wat er binnenkomt wordt bijgesneden tot
een vierkant van 256 pixels en als WebP opgeslagen.

**Terugdraaien:** de kolom `logo_path` leegmaken en het uploadveld
weghalen; de tijdlijn valt dan vanzelf terug op het pictogram, want die weg
bestaat nog.

**Let op:** in productie moet `php artisan storage:link` hebben gedraaid en
moet de `gd`-extensie er zijn. Zonder de link geeft elk logo een 404;
zonder GD werkt alles nog, maar wordt het bestand opgeslagen zoals het
binnenkwam. Zie
[de ervaringsmodule](../architecture/modules/ervaring.md#het-logo) en
[deployment](../operations/deployment.md).

---

## 016 -- De loopbaan als carrousel, met de lijst ernaast

**Keuze:** de tijdlijn op de publieke site staat standaard als carrousel:
het blok blijft in beeld staan terwijl je scrolt, en de loopbaan loopt er
in die tijd doorheen -- één functie groot, de buren kleiner eromheen. De
lijst blijft bestaan als tweede weergave, met een knopje ertussen.

**Alternatieven:** alleen de lijst houden, of hem vervangen door de
carrousel.

**Waarom de carrousel:** hoogte. Ook nadat de lijst compact was gemaakt --
één regel per functie, beschrijving dichtgeklapt -- is vijfentwintig
functies nog altijd meters pagina, en dan komt de bezoeker niet meer bij
het contactformulier. De carrousel is één scherm hoog, hoeveel functies er
ook bij komen; alleen hoe lang je erdoorheen scrolt verandert.

**Waarom de lijst blijft:** hij doet iets wat de carrousel niet kan --
alles in één blik, en vindbaar met Ctrl+F. Dat is precies wat je wilt als
je iemands loopbaan aan het beoordelen bent. Ze naast elkaar laten staan
kost één knopje en een component dat er toch al was.

En er is een tweede reden, en die is eerlijk gezegd de belangrijkste: de
lijst was er eerst en werkte goed. Hem weggooien voor iets nieuwers
betekent dat terugkeren een herbouw is. Nu is het één constante in
`ErvaringSection.vue`.

De bezoeker ziet de keuze pas vanaf vier ervaringen. Daaronder staat de
hele loopbaan toch al in beeld, en is een carrousel alleen maar extra werk.

**Terugdraaien:** `STANDAARD` in
[`ErvaringSection.vue`](../../resources/js/components/site/sections/ErvaringSection.vue)
op `'lijst'` zetten. Wil je de carrousel helemaal weg, dan kan het
component erbij weg en vervalt de keuzeknop vanzelf.

**Hoe je erdoorheen komt is onderweg veranderd.** Eerst zat het blok vast
in beeld terwijl de pagina eronder doorliep. Technisch prima, maar dan is
het kader niet het gebied waar het gebeurt: je scrolt er ver vandaan en de
kaarten schuiven mee, of je scrolt er vlak naast en er lijkt niets te
gebeuren. Dat leest als een onnauwkeurig onderdeel. Nu vangt het vak zelf
het muiswiel op -- alleen als je muis erin hangt, en alleen als het vak
het midden van het scherm beslaat -- en blijft de pagina staan. De rand
licht op zodra je erin bent, dus de afbakening is echt.

De bekende val van zo'n vak is dat je er niet meer uit komt. Daarom laat
het de scroll los zodra je aan het begin of het eind van de loopbaan
bent, en werkt het ook met Tab plus de pijltjestoetsen en met een veeg op
een telefoon.

Bij `prefers-reduced-motion` verschijnt de carrousel niet en verdwijnt
ook de keuzeknop: een vak dat je scroll overneemt is precies waar iemand
met bewegingsklachten last van heeft, en de lijst is een volwaardig
alternatief.
