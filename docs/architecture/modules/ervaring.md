# De tijdlijn met ervaringen

De eerste module waarmee de klant echte inhoud beheert. Een loopbaan in
LinkedIn-vorm: functie, organisatie, periode, en wat er verder bij hoort.

Dit document beschrijft de module zelf. Hoe hij als onderdeel van de pagina
aan- en uitgaat en verplaatst wordt, staat in
[pagina-indeling](../pagina-indeling.md); hoe het automatisch vertalen
werkt, in [automatisch vertalen](../automatisch-vertalen.md).

## Wat er allemaal onder deze module valt

Niet alleen de losse ervaringen. **Het hele blok op de website is van deze
module**, en dat is precies één scherm in het portaal:

| Onderdeel                                      | Waar de klant het beheert              | Waar het vandaan komt                  |
| ---------------------------------------------- | -------------------------------------- | -------------------------------------- |
| De **kop** erboven: de titel en de zin eronder | Knop "Kop en cijfers" op het overzicht | `experience_headings`, één rij         |
| De **cijfers** erboven: hoogstens vier         | Diezelfde knop                         | `experience_stats`, één rij per cijfer |
| De **ervaringen** zelf                         | De tabel op het overzicht              | `experiences`                          |

Dat de kop en de cijfers in hetzelfde venster zitten is geen gemak maar
een gevolg: op de website staan ze in hetzelfde blok. Los opslaan zou
betekenen dat de eigenaar twee keer bevestigt voor één zichtbare
verandering, en dat er een tussenstand kan bestaan waarin de helft live
staat. Eén scherm, één opslag, één regel in het activiteitenlogboek per
ding dat veranderde.

**De koptekst stond eerst in het Vue-component.** Daarmee was het het enige
deel van dat blok dat de klant níet kon aanpassen, terwijl de rest wel van
hem is -- precies het soort halve module waar het uitgangspunt van dit
project tegen is. Zie
[de beheerbare klantsite](../pagina-indeling.md).

**Het opschrift "Ervaring" hoort er niet bij.** Dat is de naam van het
onderdeel zelf: hij staat ook in het menu van de site en op het
indelingsscherm. Zou de klant hem hier kunnen wijzigen, dan heet hetzelfde
onderdeel op drie plekken anders.

### Voor de volgende module

`experience_headings` is bewust een tabel met **één rij** en zonder
`key`-kolom. Er is één tijdlijn, dus één kop; een sleutelkolom zou
suggereren dat er meer bij kunnen komen, en een tabel die liegt over wat
hij bevat is erger dan een tabel met één rij.

Krijgt de volgende module óók een beheerbare kop -- en dat is
waarschijnlijk -- dan is dát het moment om te bedenken of het naar
`page_sections` moet, als tekstkolommen bij het onderdeel waar de kop bij
hoort. Nu zou dat een tabel aanpassen die alle onderdelen deelt, voor één
module die hem als enige gebruikt.

## De velden, en waarom die

| Veld          | Verplicht | Vertaalbaar | Waarom zo                                                                 |
| ------------- | --------- | ----------- | ------------------------------------------------------------------------- |
| Functie       | ✅        | ✅          |                                                                           |
| Organisatie   | ✅        | ❌          | Een bedrijfsnaam is een eigennaam.                                        |
| Website       |           | ❌          | Eén veld, en het maakt het item bruikbaar.                                |
| Logo          |           | n.v.t.      | Geüpload beeldmerk; zie [Het logo](#het-logo).                            |
| Pictogram     | (vast)    | n.v.t.      | Uit een vaste set, en de terugval als er geen logo is.                    |
| Dienstverband |           | centraal    | Enum: fulltime, parttime, freelance, vast, tijdelijk, stage, zelfstandig. |
| Werkvorm      |           | centraal    | Enum: op locatie, hybride, op afstand.                                    |
| Plaats        |           | ✅          | "Utrecht, Nederland" wordt "Utrecht, Netherlands".                        |
| Van           | ✅        | n.v.t.      | Maand en jaar.                                                            |
| Tot           |           | n.v.t.      | **Leeg betekent "tot heden".**                                            |
| Beschrijving  |           | ✅          |                                                                           |
| Online        | (vast)    | n.v.t.      | Staat hij op de website? Zie [Online en offline](#online-en-offline).     |

De verplichte velden dragen in het formulier een **sterretje**. Dat is een
projectafspraak en geldt voor elk formulier in de applicatie; zie
[formulieren](../formulieren-en-schuifbalken.md#verplichte-velden).

**Het dienstverband en de werkvorm zijn enums en geen vrije tekst.** Dat
scheelt de klant werk -- hij typt "Fulltime" niet vijf keer en daarna nog
eens vijf keer in het Engels -- en het houdt het Engels consistent. Een
tekstveld zou hier "fulltime", "Full-time" en "40 uur" naast elkaar
opleveren.

**De organisatie heeft geen tweede kolom.** "Van der Valk" wordt in het
Engels niet "Of the Falcon", en een vertaalveld ernaast nodigt alleen maar
uit tot die fout.

## Het lege-veldenprobleem

Dit is de belangrijkste ontwerpregel van de module, en hij geldt voor elke
volgende ook.

**Er staat nergens een kopje boven een leeg veld.** Geen "Beschrijving"
boven niets, geen streepje waar de plaats had moeten staan. De server geeft
alleen mee wat er is, en het component toont alleen wat het krijgt. Een
ervaring met alleen een functie, een organisatie en een periode wordt
daarmee een compacte kaart die eruitziet alsof dat zo hoort.

**De metaregel komt als array, niet als string.** Plaats, werkvorm en
dienstverband gaan als losse delen mee; de puntjes ertussen zet de CSS met
`> * + *::before`. Zou je ze in de tekst plakken, dan blijft er eentje
staan zodra een deel ontbreekt -- en dan staat er "Utrecht ·" op de
website.

### En als het Engels ontbreekt

Twee regels, en ze staan in
[`Experience`](../../../app/Models/Experience.php) en niet in de
Vue-componenten. Welk veld terugvalt is een inhoudelijke keuze en geen
opmaak; zou elk scherm dat zelf beslissen, dan staat er vroeg of laat half
Nederlands op een Engelse pagina.

| Engels leeg              | Wat er gebeurt                                                                                                               |
| ------------------------ | ---------------------------------------------------------------------------------------------------------------------------- |
| **Functie** (verplicht)  | Terugvallen op het Nederlands. Een kaart zonder titel is stuk.                                                               |
| **Plaats, beschrijving** | Weglaten. De kaart ziet er zonder ook goed uit, en half Nederlands op een Engelse pagina is slordiger dan geen beschrijving. |

Het beheerscherm zet er "Nog niet vertaald" bij, maar alleen als de
**functietitel** ontbreekt. Een lege Engelse beschrijving bij een lege
Nederlandse is geen ontbrekende vertaling maar een lege beschrijving, en
daar hoort geen waarschuwing bij.

## Het beeldmerk: een logo of een pictogram

Er zijn twee bronnen voor het rondje op de tijdlijn, en ze staan in deze
volgorde:

1. Heeft de klant een **logo** geüpload, dan staat dat er.
2. Zo niet, dan een **pictogram** uit een vaste set.

De set staat in
[`ExperienceIcon`](../../../app/Enums/ExperienceIcon.php), met sleutels als
`beveiliging` en niet `ShieldCheck`: wisselen we ooit van iconenbibliotheek,
dan verandert alleen
[`ErvaringIcoon.vue`](../../../resources/js/components/site/ErvaringIcoon.vue)
en hoeft er geen rij in de database mee.

Het pictogram blijft dus bestaan náást het logo, en dat is met opzet: een
logo heb je of je hebt het niet, en tot die tijd hoort er iets te staan.
Het formulier zegt dat ook -- staat er een logo, dan heet de keuzelijst
"Pictogram, voor als het logo ooit weggaat".

**Ze staan altijd op dezelfde plek en in dezelfde maat**, overal: in het
rondje op de tijdlijn, op de kaarten van de carrousel, in de tabel van het
beheerscherm en op de detailpagina. Eén beeldmerkslot, twee mogelijke
inhouden.

**En er is geen enkele uitzondering in de opmaak.** Geen `[data-logo]`,
geen extra padding, geen witte plaat in CSS -- overal gewoon
`object-fit: cover`. Dat kan omdat de server altijd hetzelfde aflevert:
een vierkant van 256 bij 256 met de achtergrond er al in gebakken. Hoe het
beeld in dat vierkant komt te staan, heeft de klant zelf bepaald.

Die vorige zin is het hele punt. Er is eerst geprobeerd het automatisch te
doen -- eerst bijsnijden vanuit het midden, daarna passend maken met een
witte plaat -- en allebei gaat het bij een deel van de beelden mis.
Bijsnijden knipt de bedrijfsnaam naast het teken eraf; passend maken laat
een foto met witranden staan. **Wie het beeld voor zich ziet, kiest in
twee seconden wat wij niet kunnen raden.** Zie hieronder.

## Nog twee keuzes die anders zijn dan LinkedIn

**De volgorde is niet instelbaar.** Wat nu loopt staat bovenaan, daarna op
periode van nieuw naar oud. Een loopbaan die niet op volgorde staat is
gewoon fout, en een sleepgreep nodigt uit tot die fout. Waar de volgorde
wél redactioneel is -- de onderdelen van de pagina -- kan de klant hem
wel aanpassen.

**Geen vaardigheden of tags.** Dat is een tweede CRUD-niveau binnen elk
item, en de module moet functioneel blijven.

## De datums

Alleen maand en jaar. In de database staat een gewone `date`-kolom met de
dag altijd op 1; die dag betekent niets en komt nergens op een scherm. Een
datumkolom is hier handiger dan twee getallen, want sorteren, vergelijken
en opmaken werken dan vanzelf.

Het formulier stuurt `"2021-03"`; de
[`ExperienceRequest`](../../../app/Http/Requests/Website/ExperienceRequest.php)
maakt daar de eerste van de maand van. De maandnamen en de jaren komen van
de **server** en niet uit `Intl` in de browser, om dezelfde reden als alle
andere datums: zie [vertalingen](../vertalingen.md#niet-in-de-browser).

Invoeren gebeurt met
[`MaandKiezer.vue`](../../../resources/js/components/MaandKiezer.vue): één
knop met "maart 2021" erop, die een paneel opent met een jaarkeuze en een
raster van twaalf maanden. Daarvoor stonden er vier losse keuzelijsten op
het scherm -- maand, jaar, maand, jaar -- en dan moet je voor elke datum
twee lijsten opentrekken. Dagen kun je niet kiezen, en dat is geen
beperking maar het punt: bij een loopbaan weet niemand meer op welke dag
hij ergens begon.

`ended_on` leeg betekent "tot heden". Dat is geen ontbrekende waarde maar
een betekenisvolle -- het is de ervaring die nu loopt, en die staat bovenaan
met een pulserend punt.

**De duur telt de eerste maand mee.** Maart tot en met maart is één maand
werk en niet nul; zonder die plus staat er bij een kort dienstverband
"0 maanden", en dat leest als een fout.

## Het beheerscherm

Twee schermen, en het onderscheid ertussen is de leidraad voor elke
volgende module:

| Scherm                   | Waar het voor is                                                       |
| ------------------------ | ---------------------------------------------------------------------- |
| `/website/ervaring`      | **Terugvinden.** Eén regel per ervaring, met zoeken en bladeren erbij. |
| `/website/ervaring/{id}` | **Nakijken.** Alles van één ervaring, met de twee talen naast elkaar.  |

Het overzicht was eerst een stapel kaarten waarin elk item helemaal was
uitgeschreven. Bij een loopbaan van vijfentwintig functies moest je dat
scherm scrollen om te ontdekken wát erin stond, en dat is precies wat een
overzicht niet hoort te zijn. Nu is het een tabel: beeldmerk, functie,
organisatie, periode, de staat van het Engels, en het schuifje
online/offline.

**Zoeken** kijkt naar de functie in allebei de talen, de organisatie en de
plaats. Wie zijn Engels nakijkt zoekt op de Engelse titel; wie een oude
functie terugzoekt weet vaak alleen nog waar het was.

De jokertekens van `LIKE` worden onschadelijk gemaakt, anders geeft een
zoekterm als "100%" ineens de hele lijst terug. Twee details in
`Experience::scopeZoek` die je niet moet wegpoetsen:

- **De `ESCAPE`-clausule staat er expliciet bij.** MySQL neemt zonder die
  clausule de backslash; SQLite -- waar de tests op draaien -- kent geen
  standaardteken. Zonder clausule doet het zoekveld in een test iets
  anders dan in productie.
- **Het teken is een uitroepteken en geen backslash.** MySQL verwerkt
  backslashes ín een tekst tussen aanhalingstekens en SQLite niet, dus
  `escape '\\'` betekent daar niet hetzelfde. Dat kostte eerst een
  foutmelding op SQLite; een uitroepteken is in allebei gewoon een
  uitroepteken.

**Bladeren** gebeurt per vijftien regels, met de zoekterm in de link --
zonder `withQueryString` sta je op pagina twee van iets anders.

**De detailpagina** zet Nederlands en Engels naast elkaar, met bij elk leeg
veld wat dat op de website betekent ("blijft weg op de Engelse site",
"de Nederlandse titel wordt getoond").

Onderaan staan de **buren** op de tijdlijn, zodat je de loopbaan van voren
naar achteren kunt nalopen zonder telkens terug te gaan naar de lijst.
Twee knoppen naast elkaar -- op een telefoon onder elkaar -- met de
functie, de organisatie én de periode erin. Die periode is geen
versiering: bij iemand die drie keer "Service Manager" is geweest zegt
alleen een titel niets over waar je heen gaat. De lege plek blijft staan
als er maar één buur is, zodat "ouder" altijd rechts zit en "nieuwer"
altijd links; springt zo'n knop van kant, dan moet je elke keer opnieuw
kijken.

**Ze mogen niet op de vakken erboven lijken, en dat ging eerst mis.** Met
dezelfde vulling en dezelfde rand als een inhoudsblok las je ze als nóg
een blok met gegevens in plaats van als iets waar je op drukt. Vier
dingen zetten dat recht: geen vulling (de inhoudsblokken zijn juist wél
gevuld), een streep erboven zodat ze bij de voet van de pagina horen, een
gevuld rondje met een pijl in de merkkleur, en het opschrift in diezelfde
kleur. Eén gekleurd element is genoeg om "hier gebeurt iets" te zeggen;
twee zou er een reclamebanner van maken.

**De tabel laat op een telefoon drie kolommen weg**: organisatie, periode
en de staat van het Engels. De eerste twee komen daar onder de
functietitel te staan, de derde zie je op de detailpagina. Zonder dat moet
je de tabel zijwaarts scrollen om bij het online-schuifje te komen, en dat
is precies de kolom waar je voor kwam.

**Verwijder je vanaf de detailpagina, dan land je op het overzicht.** Die
pagina bestaat dan immers niet meer. Kom je van het overzicht, dan ga je
terug naar precies dat overzicht -- mét je zoekterm en je paginanummer.
Zie `ExperienceController::naHetVerwijderen`; er staat een test op allebei.

Dat adres komt uit de Referer-header, en die stuurt de browser mee. Er zit
daarom een controle op de host bij: zonder die controle kun je iemand via
een geprepareerde header naar een vreemd domein sturen. Laag risico -- er
is een geldig CSRF-token voor nodig -- maar we geven hier zelf een adres
mee, en dan hoort het het onze te zijn.

**Bewerken en verwijderen kan vanaf allebei de schermen.** In de tabel
staan twee knopjes achteraan de regel, en op de detailpagina staan ze
bovenaan. De korte weg voor wie weet wat hij zoekt, de lange voor wie
eerst wil kijken.

Bewerken gebeurt in een **venster**, in twee stappen: eerst het Nederlands,
dan het Engels. Er wordt één keer opgeslagen, aan het eind. Zou stap 1 al
wegschrijven, dan staat er een half item live zodra de klant tussendoor
stopt -- en dan moet je ook nog uitleggen waarom aanmaken ineens twee
bevestigingen vraagt.

**Het venster gaat een paar keer dicht en weer open zonder dat de klant
iets anders is gaan doen**: bij een bevestiging die hij afbreekt, bij de
waarschuwing over het overschrijven van zijn Engels, en bij een
validatiefout van de server. Het mag zich dan **niet** opnieuw vullen uit
de opgeslagen ervaring, want dan is alles wat hij net had getypt weg. Daar
is de vlag `behoudInhoud` voor. Dit ging echt mis: bij een te lange
beschrijving drukte je op Opslaan, kwam het venster terug, en stond je
oude tekst er weer. Het trof elke validatiefout, niet alleen die ene.

De twee stappen dragen het **vlaggetje** van hun taal en schuiven in de
richting waarin je loopt: vooruit naar links, terug naar rechts. Bij een
formulier dat van gedaante verandert is de eerste vraag "ben ik ergens
anders terechtgekomen of veranderde dit scherm?", en dat beantwoordt de
beweging.

Het vlaggetje van stap 1 blijft staan als die stap klaar is; het vinkje
komt ernaast. Verving het vinkje de vlag, dan draagt de ene stap wel een
vlag en de andere niet -- en juist die vlaggen zijn hier het snelste
onderscheid tussen twee schermen die op elkaar lijken.

Het venster gaat dicht vóór de bevestiging in plaats van eronder te liggen;
waarom, staat in [pagina-indeling](../pagina-indeling.md#twee-vensters-niet-op-elkaar).

## Online en offline

Elke ervaring heeft een `published`-vlag. Wat offline staat blijft gewoon
in het portaal staan, maar bezoekers zien het niet.

Drie dingen die daarbij horen:

**De teller op het indelingsscherm telt alleen wat online staat.** Zou hij
het totaal gebruiken, dan meldt het indelingsscherm dat de tijdlijn gevuld
is terwijl er op de website niets verschijnt -- en dan is die melding erger
dan geen melding. Zie `Experience::scopeOnline` en de teller in
[`AppServiceProvider`](../../../app/Providers/AppServiceProvider.php).

**Het schuifje in de lijst vraagt twee keer**, net als elke andere
wijziging aan een bestaand item. Het verandert immers wat bezoekers zien.
De schakelaar volgt de waarde van de server en niet een eigen kopie: zegt
de klant nee, dan staat hij daarmee vanzelf nog op zijn oude stand.

**Bij het aanmaken staat de keuze in het bevestigingsvenster.** Niet in het
formulier: het is de laatste beslissing en hij hoort bij de handeling.
Daarvoor heeft `bevestig()` een optionele `keuze` gekregen; zie
[meldingen](../meldingen.md#een-keuze-in-de-bevestiging).

De terugval als het veld helemaal ontbreekt is **wel online** -- dezelfde
kant op als de standaardwaarde in de migratie. Zou hij op `false` staan,
dan verdwijnt alles wat langs een andere weg binnenkomt stilletjes van de
site.

## Het logo

De klant kan per ervaring een logo uploaden. Dat verving een eerdere keuze
voor "alleen pictogrammen"; de bezwaren die daar toen bij hoorden --
opslag, validatie, opruimen, en of het wel werkt in productie -- zijn geen
argument tegen meer, maar een lijst van wat er geregeld moest worden.

**De klant snijdt zelf bij.** Kiest hij een bestand, dan verschijnt er een
rond voorbeeld met een zoomschuif eronder; slepen verschuift het beeld.
Twee snelknoppen: "Vullend maken" (zoomt tot het rondje vol is) en "Hele
beeld" (terug naar passend). Daarnaast een schakelaar voor een **witte
ondergrond**, nodig bij een logo met doorzichtige randen.

Wat je in dat voorbeeld ziet is precies wat er op de website komt: de
server rekent met dezelfde verhoudingen -- bij zoom 1 past de langste
zijde, en de verschuiving is in halve vierkanten. Lopen die twee uiteen,
dan krijgt de klant iets anders dan hij instelde, en dat merkt hij pas op
zijn eigen site. Zie
[`LogoKiezer.vue`](../../../resources/js/components/LogoKiezer.vue) en
`Logo::verklein`.

**De achtergrond wordt in het bestand gebakken.** Dat is wat de opmaak zo
simpel houdt: na het opslaan is elk beeldmerk een gewoon vierkant plaatje
zonder bijzonderheden, en hoeft geen enkel scherm nog te weten of dit een
logo met doorzichtige randen was of een foto.

**Wat er verder met een upload gebeurt.** Het bestand gaat door
[`Logo`](../../../app/Support/Media/Logo.php): getekend in een vierkant van
256 bij 256 volgens de uitsnede, en opgeslagen als WebP (PNG als de server
geen WebP kan). Dat verkleinen is nodig, want wat de klant aanlevert is wat
hij toevallig heeft -- een schermafdruk van 3000 pixels breed. Zou je dat
met CSS in een rondje persen, dan downloadt elke bezoeker alsnog drie
megabyte.

**Bijsnijden kan alleen bij een nieuw bestand.** Wat er al staat is het
verwerkte vierkant; het origineel bewaren we niet. Wil de klant het anders
inkaderen, dan uploadt hij het beeld opnieuw. Dat scheelt een tweede
kopie van elk bestand op de schijf voor iets wat zelden gebeurt.

**Vijf dingen die in productie mis kunnen gaan, en wat eraan is gedaan:**

| Risico                                                  | Wat het tegenhoudt                                                                                                                         |
| ------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Een naam als `../../.env` of `foto.php.jpg`             | De naam wordt verzonnen; die van de klant gaat nergens heen.                                                                               |
| Een zoom van nul of min tien uit een aangepast verzoek  | Begrensd in de validatie **en** in `Uitsnede` -- die laatste is de laatste halte voordat GD ermee gaat rekenen.                            |
| Iets dat geen afbeelding is                             | `image` (kijkt naar de inhoud) én `mimes` (naar de extensie), en daarna wordt het beeld door GD heen opnieuw opgebouwd.                    |
| Een plaatje van 20.000 bij 20.000 dat het geheugen vult | `dimensions` met een maximum uit `config/media.php` (3000 pixels). Uitgepakt kost dat GD zo'n 36 MB; bij 20.000 was het ruim een gigabyte. |
| Bestanden die blijven slingeren                         | Het oude bestand gaat weg bij vervangen, en het model ruimt op bij `deleting`.                                                             |
| Een server zonder de GD-extensie                        | Dan wordt het bestand opgeslagen zoals het binnenkwam. Een groot logo is vervelend; een kapot beheerscherm erger.                          |

**De database bewaart een pad, geen URL** (`ervaring/abc123.webp`). Waar
dat vandaan komt bepaalt de schijf in `config/filesystems.php`. Verhuizen
de bestanden ooit naar een andere opslag, dan verandert er één regel in de
configuratie en geen rij in de database.

**In productie moet `php artisan storage:link` hebben gedraaid**, anders
zijn de bestanden niet bereikbaar. Dat staat ook in
[deployment](../../operations/deployment.md).

**Er staat geen `2fa.confirm` op de wijzigende routes**, anders dan bij het
gebruikersbeheer. Daar kun je jezelf buitensluiten; hier gaat het om inhoud,
met een dubbele bevestiging in het scherm en een regel in het
[activiteitenlogboek](../../security/activiteitenlogboek.md) waarin staat
wát er stond. Een verse authenticator-code bij elke tekstwijziging zou de
klant leren zijn telefoon erbij te houden en verder niets opleveren.

## De tijdlijn op de site

Er zijn **twee weergaven**, en de bezoeker kiest zelf met een knopje
rechtsboven de tijdlijn:

| Weergave                                      | Component                                                                                       | Waarvoor                                                                      |
| --------------------------------------------- | ----------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| **Carrousel** (standaard op een breed scherm) | [`ErvaringCarrousel.vue`](../../../resources/js/components/site/sections/ErvaringCarrousel.vue) | Eén functie groot, met de buren eromheen. Je scrolt erin; het vak is de zone. |
| **Lijst** (standaard op een telefoon)         | [`ErvaringLijst.vue`](../../../resources/js/components/site/sections/ErvaringLijst.vue)         | Alles onder elkaar. Voor het geheel overzien, en voor Ctrl+F.                 |

Klikken op een ervaring opent in allebei de gevallen hetzelfde venster met
de volledige tekst.

De keuze verschijnt pas vanaf vier ervaringen; daaronder staat de hele
loopbaan toch al in beeld en is een carrousel alleen maar extra werk. Welke
van de twee de standaard is, staat als één constante bovenin
[`ErvaringSection.vue`](../../../resources/js/components/site/sections/ErvaringSection.vue).
**Bevalt de carrousel niet, dan is teruggaan dus geen herbouw maar één
woord.**

### De carrousel

Het probleem dat hij oplost is hoogte. Ook met compacte regels en
dichtgeklapte beschrijvingen is vijfentwintig functies onder elkaar meters
pagina. Dit onderdeel is precies **één scherm hoog**; hoeveel functies
erbij komen verandert daar niets aan, alleen hoe lang je erdoorheen
scrolt.

**Je klikt nergens op: je scrolt in het vak.** Hang je met je muis in het
omlijnde vak, dan gaat je scroll naar de loopbaan; hang je ernaast, dan
scrolt de pagina gewoon door. De pagina blijft ondertussen staan waar hij
staat. De functie waar je bent staat vooraan op een eigen kaart; die
ervóór en die erna staan kleiner erboven en eronder, **leesbaar** -- je
ziet dus niet alleen dát er iets voor en na komt, maar ook wát.

**Waarom niet het blok vastzetten en de pagina eronder laten doorlopen.**
Dat was de eerste opzet, en hij werkte technisch prima. Alleen: dan is het
vak niet het gebied waar het gebeurt. Je scrolt ver van het vak vandaan en
de kaarten schuiven mee, of je scrolt er vlak naast en er lijkt niets te
gebeuren. Dat leest als een onnauwkeurig onderdeel, ook al doet de code
precies wat er staat. Nu is de afbakening echt: het vak is de zone, en de
rand licht op zodra je erin hangt.

Onderin staat waar je bent (`9 / 27`), een zinnetje dat scrollen het
middel is, en een voortgangsbalk. Dat zinnetje wordt duidelijker zodra je
muis in het vak hangt -- dan is het ook waar.

De afstanden tussen de kaarten **schalen mee met de hoogte van het vak**.
Met vaste getallen staan ze op een groot scherm als een klein kluitje in
een half leeg vak -- precies de lege ruimte die dit onderdeel moest
oplossen. Een `ResizeObserver` houdt die maat bij.

Zes dingen die daarbij bewust zo zijn:

- **Je komt er altijd uit.** Ben je aan het begin of het eind van de
  loopbaan, dan houden we de scroll niet meer tegen en gaat de pagina
  gewoon verder. Zonder die uitweg zit de bezoeker vast in het vak, en dat
  is het ergste wat een onderdeel als dit kan doen. Om dezelfde reden
  pakt het vak je scroll pas zodra het het midden van het scherm beslaat
  -- anders werkt het tegen terwijl je er alleen maar langsschuift.
- **Met het toetsenbord werkt het ook.** Het vak is aan te wijzen met Tab,
  en dan bladeren de pijltjes, Page Up/Down, Home en End erdoorheen. Een
  onderdeel dat alleen met een muis te bedienen is, is stuk voor wie geen
  muis gebruikt. Op een telefoon veeg je erin.
- **De kaarten worden rechtstreeks verplaatst, buiten Vue om.** Dat loopt
  met elke scrollstap mee; zou Vue elke kaart opnieuw moeten tekenen, dan
  gaat het haperen. Wat Vue wél bijhoudt is welke ervaring vooraan staat
  -- dat verandert een paar tientallen keren en niet per beeldje.
- **Er zijn twee posities: een doel en een stand.** Je scroll verzet het
  doel; de stapel kruipt daar elk beeldje een stukje naartoe, op de ticker
  van GSAP. Zonder dat tussenstation springt de stapel per
  scrollgebeurtenis, en die komen bij een muiswiel in schokken van honderd
  tegelijk binnen -- dan hapert het. Mét dat tussenstation loopt de
  beweging nog even door nadat je bent gestopt, en glijdt het.
- **De afstanden worden gemeten, niet geraden.** Er stonden eerst vaste
  getallen en daarna een percentage van de hoogte van het vak; allebei
  gaan ze mis zodra een kaart hoger of lager uitvalt dan verwacht, en dat
  gebeurt bij elke schermbreedte en bij elke beschrijving opnieuw. Nu
  vragen we de kaarten zelf hoe hoog ze zijn. Daarom wordt er ook opnieuw
  gemeten zodra er een andere kaart vooraan komt: die draagt zijn
  beschrijving en is dus hoger dan de rest.
- **Alleen de voorste kaart draagt zijn beschrijving**, en die is begrensd
  tot vier regels. Het vak past op één scherm; een lange tekst zou de
  buren eronder wegduwen. Zouden de buren hun tekst óók meedragen, dan is
  er niets meer dat de voorste kaart onderscheidt. Wie alles wil lezen,
  schakelt over naar "Alles op een rij" -- daar staat de volledige tekst.
  De afstand tot de eerste buur is daarom ook groter dan die tussen de
  buren onderling: de voorste kaart is nu eenmaal hoger.
- **Alle ervaringen staan in de HTML**, niet alleen de actieve. Er is er
  één zichtbaar; de rest draagt `inert`, zodat hij niet in de tab- of
  voorleesvolgorde zit maar wél door een zoekmachine wordt gelezen.
- **De jaartallen langs de rail worden uitgedund.** Alleen waar een nieuw
  jaar begint, en alleen met genoeg ruimte sinds het vorige. Bij
  vijfentwintig functies over vijfendertig jaar zouden ze anders over
  elkaar vallen, en dan is het geen oriëntatiepunt meer maar een grijze
  streep. Erop klikken springt naar die plek.
- **Bij `prefers-reduced-motion` bestaat de carrousel niet.** Een vak dat
  je scroll overneemt is precies waar iemand met bewegingsklachten last
  van heeft. Er is dan geen afgezwakte versie; er is een lijst, en die is
  compleet. De keuzeknop verdwijnt daarom ook -- een knop die iets
  aanbiedt wat we bewust niet doen, is een kapotte knop.

Het opvangen van het wiel en de vinger staat als `scrollThrough` in
[`motion.ts`](../../../resources/js/lib/motion.ts), zodat een volgende
module hetzelfde kan doen. Eén ding dat daarbij makkelijk wordt vergeten:
`data-lenis-prevent` hoort op het element, anders neemt Lenis het wiel als
eerste af en komt onze afhandeling nooit aan de beurt.

Het pictogram in het rondje schaalt mee met het rondje (42% van de
diameter) in plaats van een vaste maat te hebben. Met een vast formaat
rammelt het in het grote rondje en puilt het uit het kleine.

### Het venster, met twee gezichten

[`ErvaringVenster.vue`](../../../resources/js/components/site/ErvaringVenster.vue)
opent op twee manieren:

| Geopend met                        | Wat je ziet                                        |
| ---------------------------------- | -------------------------------------------------- |
| een ervaring (klik op de tijdlijn) | Die ene, helemaal uitgeschreven.                   |
| niets (de knop "Toon alles")       | De hele loopbaan als lijst, om in door te klikken. |

**Waarom dat één venster is en geen twee.** Vanuit die lijst wil je op een
ervaring kunnen drukken, en dan zou er een tweede venster over het eerste
komen. Twee vensters over elkaar is precies waar dit project eerder op is
stukgelopen: ze zetten `pointer-events` op de body, en wie er als laatste
sluit zet het terug -- waarna het onderste venster wel te zien maar niet
meer aan te klikken is. Zie
[pagina-indeling](../pagina-indeling.md#twee-vensters-niet-op-elkaar).

In plaats daarvan **verandert dit venster van inhoud**. Je zakt erin en
komt met de pijl terug. Op een telefoon is dat doorzakken bovendien
precies wat je van een blad verwacht.

**Sluiten gaat één stap terug.** Zat je in een ervaring die je vanuit de
lijst had opengedaan, dan brengt sluiten je terug naar die lijst -- met
Escape, met een klik naast het venster, en met de pijl. Pas vanaf de
lijst gaat het venster echt dicht. Zonder dat ben je met één toetsaanslag
allebei je plekken kwijt en moet je de lijst opnieuw openen en je ervaring
opnieuw opzoeken.

Dat is ook de reden dat `open` hier met de hand wordt afgehandeld en niet
met een `v-model` op `DialogRoot`: die zou bij Escape gewoon alles
sluiten.

In die stand staat er ook **geen kruisje** naast de terugpijl. Twee
knoppen die allebei "weg hier" betekenen maar iets anders doen, is precies
waar je op misklikt. Open je meteen één ervaring vanaf de tijdlijn, dan is
er niets om naar terug te gaan en staat het kruisje er wél.

Dat venster lost een spanning op die er anders niet uit komt: beide
weergaven moeten **compact** blijven, en dan past een beschrijving van een
paar alinea's er niet in. Die tekst wegmoffelen achter "…" is zonde van de
inhoud; hem overal voluit tonen maakt de pagina onleesbaar.

Op een telefoon komt hij van onderen omhoog als een blad, op een breed
scherm in het midden. Dat is de conventie van het apparaat: op een
telefoon verwacht je dat een detail van onderen komt, en een venster dat
daar in het midden zweeft voelt als een foutmelding.

Het paneel schuift met CSS -- dat kan de browser zelf -- en de regels
erbinnen komen daarna met GSAP één voor één omhoog. Die getrapte beweging
is wat het venster het gevoel geeft dat het zich _opent_ in plaats van dat
het er ineens staat.

In de CSS heet dit `brand-blad-*` en niet `brand-venster-*`: dat laatste
is al de browserlijst op het indelingsscherm.

**Het venster draagt `data-lenis-prevent`.** Zonder dat attribuut vangt
Lenis het muiswiel af om de pagina soepel te laten scrollen -- ook als je
muis boven het venster hangt -- en was een lange beschrijving niet te
scrollen: je verschoof de pagina erachter.

**Het carrouselvak heeft `isolation: isolate` nodig**, en dat is geen
detail. De kaarten daarbinnen krijgen van JavaScript een `z-index` tot
100, en de randvervaging en de voet zitten daar nog boven. Zonder die
isolatie tellen die getallen mee in de stapeling van de hele pagina, en
dan staat dit venster er ineens áchter -- je zag de kaart dwars door de
waas heen. Zet je ergens een `z-index` op iets binnen een onderdeel, zet
dan ook `isolation: isolate` op dat onderdeel.

### De lijst

Eén kolom met een rail links -- het LinkedIn-model, en veel beter op een
telefoon dan afwisselend links en rechts.

**Elke ervaring is één regel, en die regel is een knop.** Erop drukken
opent het venster hierboven.

De beschrijving klapte hier eerst uit ónder de regel. Op een breed scherm
werkte dat, maar op een telefoon duwde een uitgeklapte tekst de rest van
de lijst weg en was je het overzicht kwijt -- precies wat een lijst niet
hoort te doen. Nu is elke regel even hoog.

Het is een echte `<button>` en geen `div` met een klikafhandeling: zo kom
je er met het toetsenbord bij, leest een schermlezer hem als iets waar je
op kunt drukken, en werkt Enter vanzelf. Eén gevolg: de organisatie is
hier géén link, want een link in een knop mag niet. In het venster is hij
dat wél.

De regel zelf is een **raster** met
`grid-template-columns: auto minmax(0, 1fr)`: de eerste kolom is het
rondje, de tweede de kaart. Die `minmax(0, …)` hoort erbij, want de
ondergrens van een gewone `1fr` is de breedte van het langste woord, en
dan puilt het raster buiten een smal scherm.

### De bug die dit op mobiel sloopte

Het is de moeite waard om te onthouden, want hij was op een breed scherm
volstrekt onzichtbaar.

Een jaargroep is een raster van twee kolommen: het jaartal links, de
ervaringen rechts. Op een smal scherm staat dat jaartal op
`display: none` -- daar is de breedte te kostbaar. **Daarmee is de rij met
ervaringen het enige element in het raster, en zoekt de browser er zelf
een plek voor: de eerste kolom.** Die is op mobiel nul breed.

Het gevolg: elke kaart geperst tot de breedte van zijn langste woord, met
de functietitel over vier regels, terwijl er rechts een half scherm leeg
bleef. Op een breed scherm viel er niets van te merken, want daar vult het
jaartal die eerste kolom gewoon.

De oplossing is één regel -- `grid-column: 2` op de ervaringen, en
`grid-column: 1` op het jaartal -- en de les erachter is algemener:
**laat een raster nooit zelf plekken zoeken als een van de vakjes weg kan
vallen.** Wat er automatisch gaat, gaat ergens anders automatisch fout.

**Op een telefoon begin je hier**, en niet in de carrousel. Een vak dat je
veeg afvangt zit daar in de weg: je scrolt met hetzelfde gebaar waarmee je
de pagina leest. Het blijft wel een beginstand -- wisselen kan altijd met
de knop erboven. De periode schuift op een telefoon naar zijn eigen regel;
naast de titel is te weinig breedte, en dan breekt elke functietitel over
vier regels.

**Je bladert met genummerde pagina's**: zes per pagina op een breed
scherm, **vier op een telefoon**. Daar is een regel hoger, want de periode
zakt naar een eigen regel -- en een keer extra doorklikken is er
prettiger dan langer scrollen. Een `matchMedia`-luisteraar houdt dat bij,
en begrenst meteen het paginanummer: word je scherm smaller, dan kun je
anders op pagina 7 van 5 blijven staan.

**Op een telefoon staan er geen nummers maar de stand: "3 / 7".** Met
zeven pagina's stonden er negen knopjes naast elkaar, en die vielen op
een smal scherm op twee regels -- waarbij de laatste pagina onderaan
losraakte van de rest. Een vaste rij van drie past altijd, hoeveel
pagina's er ook zijn. Je verliest daarmee het rechtstreeks naar pagina
vijf springen, en dat is op een telefoon ook geen echte handeling: die
knopjes zijn daar zo klein dat je ze toch niet gericht raakt.

Dat was eerst één knop "Toon alles" die de rest uitklapte. Bladeren is
hier beter: elke pagina is even hoog, dus de rest van de landingspagina
blijft op zijn plek staan, en je ziet hoeveel er nog komt. Boven de zeven
pagina's worden de nummers uitgedund tot de eerste, de laatste en die om
je heen -- anders neemt die rij knopjes op een telefoon zelf twee regels
in beslag.

Er wordt bij het bladeren **niet naar boven gescrold**. De lijst is zes
regels hoog en de knopjes staan er vlak onder; springt de pagina, dan ben
je die knopjes kwijt en moet je ze terugzoeken voor de volgende pagina.

Wie tóch alles in één keer wil zien, drukt op **"Toon alles"** links boven
de tijdlijn. Dat opent het venster op zijn lijstweergave.

### De kop erboven

De titel en de zin eronder komen uit `experience_headings` en niet uit het
Vue-component. De terugval tussen de talen werkt hier net als bij een
ervaring, en om dezelfde reden:

- **De titel valt terug op het Nederlands.** Een blok zonder kop is stuk.
- **De inleiding niet.** Half Nederlands op een Engelse pagina is
  slordiger dan geen zin, en zonder die zin ziet het blok er ook goed uit.

Ontbreekt de prop helemaal -- een verse database zonder seeder, of een
oude pagina uit de cache -- dan valt
[`ErvaringSection`](../../../resources/js/components/site/sections/ErvaringSection.vue)
terug op de oorspronkelijke tekst. Dan staat er een kop in plaats van een
gat.

### De cijfers erboven

Hoogstens vier getallen boven de tijdlijn. Ze tellen omhoog zodra je ze in
beeld scrolt, en het eindgetal staat al in de HTML -- gaat er iets mis met
het tellen, dan staat er nog steeds het goede getal.

**De klant beheert de lijst zelf.** Hij bepaalt hoeveel cijfers er staan,
in welke volgorde, hoe ze heten en waar het getal vandaan komt. Vier is
geen technische grens maar een ontwerpkeuze: vijf getallen naast elkaar
boven een lijst is geen samenvatting meer. De grens wordt afgedwongen in
de validatie en staat als `ExperienceStat::MAXIMUM`.

#### Drie dingen bepalen wat er komt te staan

| Veld                    | Wat het zegt                                                                    |
| ----------------------- | ------------------------------------------------------------------------------- |
| `key`                   | Het **soort**: jaren, functies, organisaties, of `eigen`.                       |
| `modus`                 | **Wat ermee gebeurt**: automatisch, een eigen getal, of niet tonen.             |
| `label_nl` / `label_en` | Het **woord eronder**. Leeg betekent: gebruik het standaardwoord van het soort. |

De modus is er later bij gekomen, en dat loste een echt probleem op. Eerst
betekende een leeg getalveld "reken het uit", en dus kon de klant een
cijfer helemaal niet weglaten: één veld met twee betekenissen kan geen
derde aan. Nu zegt de modus wat er hoort te gebeuren en zegt `value`
alleen nog hoeveel. Zie
[`ExperienceStatModus`](../../../app/Enums/ExperienceStatModus.php).

Bij **automatisch** rekenen wij het uit de tijdlijn. Die rekensom staat in
[`Loopbaan`](../../../app/Support/Loopbaan.php), want twee plekken moeten
hem kennen: de website die de cijfers toont, en het beheerscherm dat laat
zien wat er zou komen te staan. Zou elk scherm het zelf uitrekenen, dan
belooft het portaal iets anders dan de site laat zien.

Wélke soorten er bestaan staat in
[`ExperienceStatKey`](../../../app/Enums/ExperienceStatKey.php) en niet in
de database -- dat is code, geen inhoud, net als bij `PageSectionKey`. Van
een soort dat wij tellen mag er precies één zijn; van `eigen` meerdere,
want daarmee komt de klant aan een getal dat niets met de tijdlijn te
maken heeft ("12 certificeringen").

Het woord leeg laten is hier de betere keuze dan het overtypen: het
standaardwoord is vertaald, dus het blijft ook op de Engelse site
kloppen. Zie `ExperienceStat::woord()`. Vult de eigenaar er tóch zelf een
woord in, dan staat er een klein vertaalknopje naast het Engelse veld;
waarom dat een andere vorm heeft dan de grote knop bij de tekst staat in
[automatisch vertalen](../automatisch-vertalen.md).

De jaren lopen van de vroegste startdatum tot het laatste einde en zijn
**niet** de som van alle periodes: functies overlappen, en dan tel je
jezelf rijk. Loopt er nog iets, dan wordt er tot **vandaag** gerekend --
dus het getal gaat vanzelf omhoog zodra er een jaar volgemaakt is. Niet op
1 januari: begon de loopbaan in juni, dan springt het in juni. Er staan
geen cijfers als er geen ervaringen zijn; nullen boven een lege lijst zijn
erger dan geen cijfers.

#### Wat er bij het soort hoort, staat niet in de rij

Dit is de belangrijkste regel in dit onderdeel, en hij is met schade en
schande geleerd.

Het beheerscherm kreeg eerst per rij mee wat er bij dat soort hoort: het
standaardwoord, de uitleg erbij, en wat wij op dat moment voor dát soort
zouden tellen. Zodra de eigenaar in het venster een ander soort koos,
klopte die meegestuurde bagage niet meer -- het scherm wist niet wat er
bij het nieuwe soort hoort en zette er nul neer. Je zag dan "nu zouden wij
er 0 tellen" boven een tijdlijn van vijfendertig jaar.

Daarom draagt een rij nu alleen nog wat de klant zelf heeft ingesteld:
`id`, `key`, de twee woorden, `modus` en `waarde`. Alles wat uit het soort
volgt zit in `cijferKeuzes.soort`, en het scherm zoekt het daar op. Dan
klopt het bij elke keuze -- ook bij een keuze die nog niet is opgeslagen.
`ExperienceStatsTest` bewaakt allebei de kanten van die afspraak.

#### Beheren

Via de knop "Kop en cijfers" op het overzicht -- dezelfde knop als voor de
titel erboven, want het is op de website hetzelfde blok.

Elke kaart in dat venster toont bovenaan **een voorbeeld van het tegeltje**
zoals het op de website komt te staan: het getal groot, het woord eronder.
Dat is de kortste weg naar begrip. De keuzelijsten eronder zeggen wát er
gebeurt, maar pas het voorbeeld laat zien wát er komt te staan -- en het
verandert meteen mee.

Een cijfer weghalen vraagt om een bevestiging, ook al gebeurt het pas echt
bij Opslaan. Een prullenbakje dat een ingevuld cijfer meteen laat
verdwijnen voelt als iets kwijtraken, en er is geen ongedaan maken in dit
formulier. De bevestiging wijst ook de weg naar het alternatief: wil je
het alleen even niet tonen, kies dan "Niet tonen" -- dan blijven het woord
en het getal bewaard.

**Een ingevuld cijfer werkt niet vanzelf bij, en dat is de val.** Typt de
klant er 27 in en komt er daarna een functie bij, dan blijft er 27 staan
-- op de voorpagina, waar hij zelf nooit kijkt. Daarom meldt het scherm
het: onder het veld staat "Wij tellen er 28", en op de knop komt een
waarschuwingsteken zodra een cijfer achterloopt. Terug op automatisch zetten
lost het op.

### De animaties van de lijst

De animaties staan in
[`motion.ts`](../../../resources/js/lib/motion.ts) en niet in het
component, zodat de volgende module ze kan hergebruiken:

1. **De lijn tekent zichzelf** terwijl je scrolt. Dat is het
   signatuureffect: de tijdlijn ontstaat onder je ogen.
2. **De punten kleuren op** zodra de lijn ze passeert. Gekoppeld aan de
   voortgang van de lijn en niet aan hun eigen trigger, zodat het één
   doorlopende beweging is en geen losse trucjes die toevallig na elkaar
   afgaan.
3. **De kaarten komen binnen** vanaf de kant van de rail, in golfjes, en
   alleen de kaart -- niet het punt. Zou het punt meebewegen, dan meet
   `drawTimeline` zijn plek op de lijn terwijl hij nog verschoven staat, en
   lichten de punten net te vroeg of te laat op.
4. **Het lopende punt pulseert** heel zacht. Eén punt dat leeft; meer zou
   het een kerstboom maken.
5. **De cijfers tellen omhoog** (`countUp`).
6. **Het jaartal licht op** zodra je door zijn groep scrolt
   (`followYears`). De grens ligt op het midden van het scherm, zodat er
   altijd precies één actief is.
7. **De regel onder je muis licht op en de rest zakt weg.** Pure CSS. Bij
   een lijst van gelijkvormige regels is dat het enige dat zegt welke regel
   je te pakken hebt.
8. **Zes ervaringen per pagina op een breed scherm, vier op een telefoon**, met genummerde knopjes eronder. Een
   tijdlijn die de hele pagina vult duwt de rest onder de vouw.

Na het bladeren wordt de tijdlijn opnieuw opgemeten -- de punten staan
ergens anders -- en komen de nieuwe kaarten met `revealCards({ direct })`
binnen. Met een scroll-trigger zouden ze onzichtbaar blijven hangen, want
ze staan al in beeld.

Drie dingen die hier makkelijk stukgaan en daarom met een toelichting in de
code staan:

- **JavaScript raakt de opmaak niet aan.** Het zet `--tijdlijn-voortgang`
  en een attribuut op de punten; de kleuren staan in CSS. Zo blijft de
  huisstijl op één plek en schaalt de lijn met `transform`, wat op de GPU
  draait.
- **De posities van de punten worden gemeten met `getBoundingClientRect`
  en niet met `offsetTop`.** De punten zitten in hun eigen
  `position: relative`-kaart, dus hun `offsetTop` gaat over die kaart en
  niet over de lijn.
- **Uitklappen animeert met `revealNow` en niet met een scroll-trigger.**
  De nieuwe kaarten staan al in beeld, dus een trigger zou nooit afvuren en
  dan blijven ze op `opacity: 0` hangen. Daarna volgt een
  `ScrollTrigger.refresh()`, want de lijn is ineens twee keer zo lang.

Alles staat uit bij `prefers-reduced-motion`, met de lijn meteen vol en
alle punten gemarkeerd -- de inhoud is dan compleet, alleen zonder
beweging.

### Het jaartal dat meeloopt

Op een breed scherm staat links van de rail een jaartal dat blijft hangen
zolang je in zijn jaar scrolt, en dat door het volgende wordt weggeduwd.

Dat is **pure CSS** en geen scroll-code: elke jaargroep is een rij in een
raster, en `position: sticky` houdt een element binnen zijn eigen rij.
Twee dingen die daarbij makkelijk stukgaan staan met een toelichting in de
stylesheet -- `align-self: start` (zonder dat rekt het jaartal op tot de
volle hoogte en kán het niet meer schuiven) en de rail die met dezelfde
variabele meeschuift als de kolombreedte.

Op een telefoon is er geen kolom: daar is de breedte te kostbaar, en de
periode staat toch al op elke kaart.

**Eén gevolg van "wat nu loopt staat bovenaan":** is de lopende ervaring
eerder begonnen dan een afgeronde daaronder, dan telt het jaartal niet
netjes af. Het klopt nog steeds -- het jaar hoort altijd bij de items
eronder -- maar verbaas je er niet over.

## Twee sloten op de link naar de organisatie

Het enige veld waarin de klant iets invult dat de browser gaat **uitvoeren**
in plaats van tonen. Daar zitten dus twee controles op:

1. **Bij de invoer:** `url:http,https`, en niet alleen `url`. Dat weigert
   `javascript:`, `data:` en `file:`. Er staat een test op met alle vier de
   varianten, inclusief `JaVaScRiPt:`.
2. **Bij de uitvoer:** `Experience::website()` geeft alleen een adres terug
   dat met `http://` of `https://` begint, en anders niets.

Die tweede lijkt overbodig en is dat niet. Vue schoont een `:href` niet, en
de eerste controle zit op de invoer -- een waarde die er via een seeder,
een import of een later toegevoegde regel in komt, glipt daar langs. Een
link die verdwijnt is een schoonheidsfoutje; een link die code uitvoert op
de publieke site is dat niet.

De link krijgt daarnaast `rel="noopener noreferrer"` bij `target="_blank"`,
zodat de pagina waar hij heen gaat geen greep krijgt op de onze.

## Tests

| Bestand                                                                                   | Wat het bewaakt                                                                                                                                                                                                                                                                            |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| [ExperienceCrudTest](../../../tests/Feature/Website/ExperienceCrudTest.php)               | Rechten per route -- **de volledige lijst staat hier en nergens anders** -- beide talen opslaan, een lege waarde die `null` wordt en geen `""`, een omgekeerde periode, en wat er in het logboek komt.                                                                                     |
| [ExperienceOverviewTest](../../../tests/Feature/Website/ExperienceOverviewTest.php)       | Zoeken (inclusief een zoekterm met een jokerteken erin), bladeren, het verschil tussen "nog niets" en "niets gevonden", de detailpagina met zijn buren, en waar je belandt na het verwijderen.                                                                                             |
| [ExperiencePublishingTest](../../../tests/Feature/Website/ExperiencePublishingTest.php)   | Online en offline: het schuifje, dat offline werk niet op de site komt, dat de hele sectie verdwijnt als alles offline staat, en de cijfers erboven.                                                                                                                                       |
| [ExperienceHeadingTest](../../../tests/Feature/Website/ExperienceHeadingTest.php)         | De kop: allebei de talen opslaan, een verplichte titel, een lege inleiding die `null` wordt, de terugval die de titel wél en de inleiding niet doet, en dat een deploy de tekst van de klant niet terugzet.                                                                                |
| [ExperienceStatsTest](../../../tests/Feature/Website/ExperienceStatsTest.php)             | De cijfers: de drie modussen, hoogstens vier, geen dubbel soort, een eigen getal dat een getal nodig heeft, hernoemen met terugval op het standaardwoord, dat wat wij zouden tellen bij het **soort** hoort en niet bij de rij, en dat de route niet door de detailpagina wordt opgeslokt. |
| [ExperienceLogoTest](../../../tests/Feature/Website/ExperienceLogoTest.php)               | De upload: dat er een vierkantje van 256 uit komt, dat de naam van de klant niet wordt gebruikt, dat rommel wordt geweigerd, en dat er niets blijft slingeren.                                                                                                                             |
| [ExperienceTimelineTest](../../../tests/Feature/Website/ExperienceTimelineTest.php)       | Wat de bezoeker krijgt: de volgorde, de terugval op het Nederlands, en dat een leeg veld niets oplevert.                                                                                                                                                                                   |
| [ExperienceTranslationTest](../../../tests/Feature/Website/ExperienceTranslationTest.php) | De vertaalknop, met een dubbel in plaats van de echte dienst.                                                                                                                                                                                                                              |
| [ExperienceSeederTest](../../../tests/Feature/Database/ExperienceSeederTest.php)          | De echte loopbaan die met elke deploy meegaat: twee keer draaien verandert niets, en de seeder overschrijft nooit wat de klant zelf aanpaste.                                                                                                                                              |

De twee die je bij een volgende module niet moet vergeten: **de spiegel van
de lege sectie** (verschijnt hij ook weer zodra er iets in staat) en **het
onderscheid tussen `null` en `""`**, want de website beslist op `filled()`
of een veld getoond wordt.
