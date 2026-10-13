# De diensten

Wat de eigenaar aanbiedt, als kaarten op de landingspagina: een
pictogram, een titel, een korte tekst, en de expertise die eronder valt.
Klik je op een kaart, dan opent er een venster met het hele verhaal.

> **Waarom dit een module is.** De drie diensten stonden hardgecodeerd in
> `DienstenSection.vue`, met een `01/02/03` erboven. Dat component zei in
> zijn eigen docblock al dat dit tijdelijk was.

| Onderdeel              | Waar de klant het beheert              | Waar het vandaan komt              |
| ---------------------- | -------------------------------------- | ---------------------------------- |
| De **diensten** zelf   | Website → Diensten                     | `services`                         |
| De **expertisepunten** | In het venster van een dienst          | `service_points`                   |
| De **kop** erboven     | Knop "Kop erboven" op datzelfde scherm | `section_headings`, rij `diensten` |
| De **volgorde**        | Knop "Volgorde", slepen in een venster | `services.position`                |

Dit is de derde module, en hij combineert de twee die er al waren. Van
[Ervaring](ervaring.md) komt de lijst met een venster per item; van
[Kop](kop.md) het beheren van vaste tekst boven een blok.

## Wat er in een dienst staat

| Veld                        | Verplicht     | Lengte      | Waarvoor                         |
| --------------------------- | ------------- | ----------- | -------------------------------- |
| `icon`                      | ja            | enum        | Het pictogram op de kaart        |
| `title_nl` / `title_en`     | nl ja, en nee | 80          | De kop van de kaart              |
| `summary_nl` / `summary_en` | nl ja, en nee | 300         | De tekst op de kaart             |
| `body_nl` / `body_en`       | nee           | 5000        | Het hele verhaal, in het venster |
| expertisepunten             | nee, 0–8      | 60 per punt | De labels onder de tekst         |
| `published`                 | —             | bool        | Online of offline                |
| `position`                  | —             | int         | De volgorde                      |

**`summary` is verplicht en `body` niet, en dat verschil is het ontwerp.**
Een kaart met alleen een titel is een lege kaart. Het lange verhaal is een
aanvulling: heeft een dienst dat niet, dan is de kaart niet aanklikbaar en
staat er ook geen "Lees meer". Zo bepaalt de klant per dienst of het de
moeite waard is om door te klikken.

### Geen maximum aantal diensten, wel een maximum per pagina

Anders dan bij de cijfers boven de tijdlijn, waar vier een harde grens is.
De klant mag er zoveel neerzetten als hij wil; wat begrensd is, is
hoeveel er **tegelijk** te zien zijn.

**Vier op een breed scherm, drie op een telefoon**, en daarna blader je
verder. Zelfde afweging als bij de tijdlijn: een blok dat met elke dienst
langer wordt duwt de rest van de pagina onder de vouw, en dan leest
niemand meer waar het contactformulier staat. Elke pagina is even hoog,
dus de pagina eronder blijft op zijn plek staan.

Op een telefoon drie en niet vier omdat de kaarten daar onder elkaar
staan: vier zou betekenen dat je moet scrollen om de knopjes te vinden
waarmee je verder bladert.

**Wel hoogstens acht expertisepunten per dienst.** Acht korte labels
vullen ongeveer drie regels onder de tekst; daarboven lopen de kaarten in
het raster uit elkaar. Die grens staat als `Service::PUNTEN_MAXIMUM` en
wordt in `ServiceRequest` afgedwongen.

### De terugval tussen de talen

De regel is overal dezelfde: **een verplicht veld valt terug op het
Nederlands, een optioneel veld wordt weggelaten.**

| Veld              | Engels leeg?                                    |
| ----------------- | ----------------------------------------------- |
| Titel             | valt terug op het Nederlands                    |
| Korte tekst       | valt terug op het Nederlands                    |
| Uitgebreide tekst | valt **niet** terug; er komt dan geen venster   |
| Een expertisepunt | valt **niet** terug; dat punt staat er dan niet |

Dat laatste is een keuze die het uitleggen waard is. Een lijstje waarin
twee van de vijf labels ineens Nederlands zijn leest slechter dan een
lijstje van drie. Een Engelse bezoeker ziet dus liever een korter lijstje
dan een gemengd lijstje.

Die keuzes staan in `App\Models\Service` en `App\Models\ServicePoint`, en
niet in Vue. Zou de component het zelf moeten bedenken, dan staat er vroeg
of laat half Nederlands op een Engelse pagina.

## Hoe het op de landingspagina staat

Een sectiekop, en daaronder een raster met kaarten.

**Zolang alles op één pagina past volgt het aantal kolommen het aantal
diensten**, zodat de rij vol staat:

| Diensten | Kolommen op desktop    |
| -------- | ---------------------- |
| 1        | 1, in een smalle kolom |
| 2        | 2                      |
| 3        | 3                      |
| 4        | 4                      |

Vier worden er **twee bij twee** en niet vier naast elkaar: vier kaarten
op één rij zijn zo smal dat de tekst erin op zes regels valt.

Zodra er gebladerd wordt ligt het raster vast op twee kolommen. Anders
zou de laatste pagina met één overgebleven dienst een kaart van de volle
breedte tonen, en springt de maat van de kaarten bij elke klik — dat
leest als een andere sectie in plaats van als de volgende pagina.

Op een telefoon altijd 1 kolom.

### Bladeren springt naar de kop van het onderdeel

Een nieuwe pagina is korter of langer dan de vorige, dus alles eronder
verschuift. Doe je daar niets aan, dan druk je op "volgende" en kijk je
ineens naar de tijdlijn — en op een telefoon, waar de kaarten onder
elkaar staan, begin je zelfs midden in de tweede kaart. Erger nog: de
knopjes schuiven weg onder je muis, dus je volgende klik landt op de
pager van het onderdeel eronder.

**Er is eerst geprobeerd het blok een vaste hoogte te geven** — de
hoogte van de grootste pagina die het had gehad. Dat loste het
verschuiven op en leverde iets ergers op: op een telefoon is een volle
pagina drie kaarten hoog, dus onder de laatste pagina met één kaart gaapte
een gat van bijna twee schermen.

Nu springt de pagina naar de kop van dit onderdeel, met `scrollNaar`.
Dat is wat je van bladeren verwacht, het werkt op elke schermbreedte, en
de knopjes staan een scherm lager gewoon weer op hun plek. De tijdlijn
doet hetzelfde, om dezelfde reden.

### Nieuwe kaarten moeten zichtbaar gemaakt worden

Een kaart begint op doorzichtigheid nul — die klasse zit in
`FeatureCard` — en wordt pas zichtbaar doordat `kaartenBinnen()` hem
ophaalt. Verschijnt er een kaart zonder dat die functie draait, dan
blijft hij onzichtbaar.

Dat is één keer misgegaan bij het overgaan van een **breedtegrens**: op
een telefoon staan er drie kaarten, op een breed scherm vier. Ging je van
smal naar breed, dan kwam die vierde er wel bij te staan maar zag je hem
niet — pas na het verversen van de pagina stond hij er.

Vandaar `herstelKaarten()`, dat na élke wisseling van de inhoud draait:
bij het bladeren én bij het overgaan van de grens. Bij het opstarten
juist niet, want daar hoort de binnenkomst aan de scroll te hangen.

### De hover

Kom je met de muis op een kaart, dan gebeuren er vier dingen tegelijk:
de kaart kantelt licht mee met de cursor en er loopt een glans overheen
(dat deed hij al), hij komt drie pixels naar je toe, het pictogram wordt
iets groter met een gloed eromheen, en de expertisepunten lichten **van
links naar rechts** op.

Die laatste vertraging is wat het effect maakt. Allemaal tegelijk is één
blok dat van kleur wisselt; achter elkaar leest als een lijn die zich
vult, en je oog loopt er vanzelf langs. Bij het weggaan van de muis
doven ze wél in één keer — een uitdovende golf voelt traag, want je kijkt
dan al ergens anders.

Wie in zijn systeeminstellingen om minder beweging heeft gevraagd krijgt
de kleuren wel en het verschuiven niet.

De klassen staan voluit in die tabel en worden niet in elkaar gezet.
Tailwind leest de broncode om te weten welke klassen bestaan; een naam die
pas tijdens het draaien ontstaat vindt hij niet, en dan mist die klasse in
de gebouwde stylesheet.

**De kaart is een `<button>` zodra er een venster achter zit**, en anders
een `<article>`. Zo is hij met de tabtoets bereikbaar en met Enter te
bedienen, en is er geen knop die niets doet. "Lees meer" verschijnt pas bij
hover of toetsenbordfocus; op een aanraakscherm staat hij altijd, want daar
is geen hover.

De animaties zijn die van eerder: `kaartenBinnen()` en `kantelKaarten()`
uit `motion.ts`, op elementen met `data-kaart`.

## Het beheerscherm

`website/Diensten`, met een eigen regel **Diensten** in de zijbalk tussen
Kop en Ervaring — dezelfde volgorde als op de site.

**Geen zoekveld en geen paginering**, anders dan bij de tijdlijn. Een
loopbaan telt tientallen functies; diensten zijn er een handvol. Een
zoekveld boven vier regels is gereedschap dat in de weg staat.

**Geen detailpagina.** Alles van een dienst past in één venster. Bij een
ervaring is die pagina er om na te kijken — twee talen naast elkaar, de
datums, het logo — en dat weegt hier niet op tegen een klik extra.

Vier vensters:

1. **Nieuwe dienst / dienst bewerken** — twee stappen, zoals bij een
   ervaring: stap 1 het Nederlands, stap 2 het Engels met de Nederlandse
   bron erboven. Eén keer opslaan aan het eind, want een half opgeslagen
   dienst staat live.
2. **De expertisepunten** zitten in dat venster als een lijst met
   toevoegen en weghalen. Het weghalen van een _ingevuld_ punt vraagt om
   een bevestiging; een leeg punt niet, daar valt niets aan kwijt te
   raken.
3. **Volgorde** — `SortableList` in een venster. Slepen én
   pijltjestoetsen; die pijltjes zijn niet de tweede keus maar de
   betrouwbare weg.
4. **Kop erboven** — opschrift, titel, zin eronder, in beide talen.

## De volgorde bewaren

Opnieuw nummeren vanaf 1 en niet verschuiven, hetzelfde patroon als bij
[de indeling van de pagina](../pagina-indeling.md). Dan kan er geen gat of
dubbele positie ontstaan, hoe vaak je ook sleept.

**De hele lijst moet meekomen.** Zou een verzoek met drie van de zes ids
binnenkomen, dan krijgen die drie positie 1, 2 en 3 en botsen ze met de
andere drie. De server vergelijkt daarom wat hij krijgt met wat er staat,
en weigert het verzoek als dat niet klopt.

## De expertisepunten gelijktrekken

`ServiceController::bewaarPunten()` werkt bij wat er al was, vult aan wat
erbij komt en haalt weg wat er niet meer bij zit. Alles weggooien en
opnieuw aanmaken zou makkelijker zijn, maar dan krijgt elk punt bij elke
opslag een nieuw id — en verandert er dus altijd iets, ook als de klant
niets heeft aangeraakt.

Een **lege lijst** betekent "haal ze allemaal weg" en niet "laat maar
staan". Vandaar `present` in de validatie: een ontbrekende sleutel zou dat
verschil wegpoetsen.

`ServicePoint` heeft **geen eigen activiteitenlogboek**. Een punt bestaat
niet los van zijn dienst, en de eigenaar bewerkt ze in hetzelfde venster.
Zou elk punt een eigen regel krijgen, dan levert één keer opslaan er zes
op en is het logboek niet meer te lezen.

## Vertalen

De grote knop in stap 2 vertaalt de titel, de korte tekst en het verhaal
in één verzoek. **De expertisepunten hebben elk een eigen klein knopje**,
net als het woord onder een cijfer: ze staan in een rij, en één knop die
er acht tegelijk overschrijft is te grof.

Op de gedeelde route (`POST website/vertalen`) kwamen hiervoor twee velden
bij: `summary_nl` en `punt_nl`. De titel en het verhaal gebruiken de
bestaande `title_nl` en `description_nl`. Zie
[automatisch vertalen](../automatisch-vertalen.md).

## De kop staat in de gedeelde tabel

Hier stond `service_headings`, de derde bijna identieke koptabel. Bij de
[certificaten](certificaten.md) zou het de vierde worden, en dat was het
afgesproken moment om ze samen te voegen: er is nu één
`section_headings` met een regel per onderdeel.

Voor dit scherm verandert er niets aan de buitenkant -- hetzelfde adres,
dezelfde drie teksten. Wat eronder anders werkt staat in
[kopteksten](../kopteksten.md).

## Wat waar staat

| Bestand                                                                                     | Wat het doet                                                      |
| ------------------------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| [`Service`](../../../app/Models/Service.php)                                                | De dienst, de terugval tussen de talen, het logboek.              |
| [`ServicePoint`](../../../app/Models/ServicePoint.php)                                      | Eén expertisepunt.                                                |
| [`SectionHeading`](../../../app/Models/SectionHeading.php)                                  | De kop boven het blok, gedeeld met de andere onderdelen.          |
| [`ServiceIcon`](../../../app/Enums/ServiceIcon.php)                                         | De vaste set pictogrammen.                                        |
| [`ServiceController`](../../../app/Http/Controllers/Website/ServiceController.php)          | Het scherm, de opslag, de volgorde en de kop.                     |
| [`ServiceRequest`](../../../app/Http/Requests/Website/ServiceRequest.php)                   | Wat er in een dienst mag staan.                                   |
| [`ServiceSeeder`](../../../database/seeders/ServiceSeeder.php)                              | De drie diensten als startpunt.                                   |
| [`Diensten.vue`](../../../resources/js/pages/website/Diensten.vue)                          | Het overzicht.                                                    |
| [`DienstDialoog.vue`](../../../resources/js/components/website/DienstDialoog.vue)           | Aanmaken en bewerken, in twee stappen.                            |
| [`VolgordeDialoog.vue`](../../../resources/js/components/website/VolgordeDialoog.vue)       | De volgorde slepen.                                               |
| [`DienstenSection.vue`](../../../resources/js/components/site/sections/DienstenSection.vue) | Wat de bezoeker ziet.                                             |
| [`DienstVenster.vue`](../../../resources/js/components/site/DienstVenster.vue)              | Het hele verhaal achter één dienst.                               |
| [`DienstIcoon.vue`](../../../resources/js/components/site/DienstIcoon.vue)                  | De enige koppeling naar de iconenbibliotheek.                     |
| [`ServiceCrudTest`](../../../tests/Feature/Website/ServiceCrudTest.php)                     | Rechten, aanmaken, wijzigen, verwijderen, validatie, het logboek. |
| [`ServicePointsTest`](../../../tests/Feature/Website/ServicePointsTest.php)                 | Het maximum, de volgorde, het gelijktrekken en de terugval.       |
| [`ServicePublishingTest`](../../../tests/Feature/Website/ServicePublishingTest.php)         | Online/offline, de volgorde en het leeg-gedrag van de sectie.     |
| [`ServiceHeadingTest`](../../../tests/Feature/Website/ServiceHeadingTest.php)               | De kop en de terugval per veld.                                   |
