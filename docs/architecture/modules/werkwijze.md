# Module Werkwijze

Wat er gebeurt als iemand met de eigenaar in zee gaat, stap voor stap.

## Waarom dit een verbouwing is en geen nieuwbouw

Dit onderdeel bestond al, en het was mooi: vier genummerde stappen naast
elkaar met een lijn erboven die zich tekent terwijl je scrolt, en een
lichtpuntje dat erlangs reist. Alleen stonden die vier stappen **in het Vue-
bestand**, net als de kop erboven. Mooi gemaakt, maar niet van de klant --
en dit is zijn site.

De vormgeving is daarom met opzet níet meeverhuisd. Wat eraan veranderd is,
is veranderd omdat het móest: de lijn kon niet overweg met een ander aantal
stappen dan vier.

## Het datamodel

Eén tabel `work_steps`, opgezet als `services`.

| Kolom                   | Type                  | Waarvoor                            |
| ----------------------- | --------------------- | ----------------------------------- |
| `position`              | smallint, index       | Slepen -- én het nummer op de kaart |
| `published`             | bool, default true    | Aan/uit                             |
| `title_nl` / `_en`      | string(80)            | "Kennismaken"                       |
| `summary_nl` / `_en`    | string(300)           | De zin onder de titel               |
| `duration_nl` / `_en`   | string(40), nullable  | "1-2 weken"                         |
| `result_nl` / `_en`     | string(160), nullable | "Een plan met een prijs"            |
| `body_nl` / `_en`       | text, nullable        | Het hele verhaal, voor `/werkwijze` |
| `machine_translated_at` | timestamp, nullable   | Het merkje                          |

**Er is geen kolom voor het nummer**, en dat is de belangrijkste keuze in
dit datamodel. Het nummer volgt uit `position`. Zou de eigenaar het apart
kunnen invullen, dan krijg je vroeg of laat een lijst die begint bij 01, 03,
02 -- en dan betekent het niets meer.

Het scherm telt daarbij alléén wat online staat. Een offline stap bezet geen
nummer; de bezoeker ziet dus nooit 01, 02, 04.

### Wat erbij kwam, en waarom juist dat

De oude kaartjes beantwoordden alleen "wat". De twee velden die erbij zijn
gekomen beantwoorden de vragen die een bezoeker daarna heeft:

- **Duur** -- hoe lang duurt dit? Als tekst en niet als getal: "een
  middag", "1-2 weken", "doorlopend". Dat laat zich niet in dagen
  uitdrukken, en een schatting die te precies oogt is een belofte die je
  niet wilde doen.
- **Resultaat** -- wat heb ik aan het eind in handen? Voor een adviseur is
  dat precies het verschil tussen een belofte en een afspraak.

**Allebei optioneel.** Laat de eigenaar ze leeg, dan ziet de kaart er
exact uit als voorheen. Dat is met opzet: deze verbouwing hoort niets aan
zijn website te veranderen zolang hij niets invult. Om dezelfde reden staan
de oude teksten woord voor woord in `VoorbeeldDataSeeder` en in
`SectionHeading::standaard()`.

### De talen

**Alleen de titel valt terug op het Nederlands.** De rest wordt weggelaten
als het Engels leeg is.

Dat is een bewust verschil met `Service::samenvatting()`, die wél terugvalt:
daar staat de kaart leeg zonder tekst, hier blijft er een genummerde stap
met een titel over en dat leest nog steeds als een werkwijze. En de duur al
helemaal niet -- "1-2 weken" is geen naam maar een zin met een Nederlands
woord erin.

## De lijn

Het pad lag vast in de code: een handgetekende golf in een viewBox van 1000
bij 60, alleen zichtbaar op een breed scherm. Dat werkt zolang er precies
vier stappen op één rij staan. Bij drie loopt de lijn langs een leeg vak,
bij vijf vallen ze op twee rijen en loopt hij nergens meer langs -- en op
een telefoon, waar ze onder elkaar staan, kon hij helemaal niet bestaan.

**`tekenPad()` tekent het pad nu zelf, door de stappen heen.** Dat haalt die
uitzonderingen wég in plaats van er een toe te voegen: één rij, twee rijen,
of een kolom op een telefoon -- het is dezelfde code. En de lijn bestaat nu
ook op mobiel, waar hij verticaal loopt.

Vier dingen die daarbij bewust zo zijn:

- **Gemeten met `offsetLeft` en niet met `getBoundingClientRect`.** De
  stappen komen op met een verschuiving, en een rect geeft de verschóven
  plek terug. Dan tekent de lijn langs waar de kaarten even stonden.
- **De lijn hangt aan de nummers** (`[data-stap-punt]`) en niet aan het
  midden van de kaart. Door het midden zou hij op een telefoon dwars door
  de tekst lopen.
- **De tussenpunten worden afwisselend opzij geduwd**, loodrecht op de
  richting van de hele lijn. Een lijn langs punten die op één hoogte staan
  is kaarsrecht, en dat was juist de charme niet. De golf ontstaat nu
  vanzelf in de goede richting: op een rij op en neer, in een kolom naar
  links en rechts.
- **Waar een stap op het pad zit wordt opgezócht en niet uitgerekend.** Het
  pad golft, dus de helft van de lengte ligt niet bij de helft van de
  stappen. Tweehonderd monsters langs het pad, en per stap het dichtstbije.

### Rij of kolom, en niets ertussenin

**Tot vier stappen een rij, daarboven een kolom.** Geen raster van meerdere
rijen, en die keuze volgt rechtstreeks uit de lijn: een lijn die van het
einde van de ene rij naar het begin van de volgende moet, snijdt onderweg
dwars door de tekst van de kaarten ertussen. Er is geen route die dat niet
doet.

De eerste poging liet de lijn in dat geval maar weg. Dat was erger: bij vijf
stappen stond er ineens een leeg vak waar iets hoorde. Nu schakelt de opmaak
om naar één kolom -- precies wat een telefoon al deed -- en loopt de lijn er
verticaal langs. Die vorm werkt bij elk aantal.

Het component zet daarvoor `data-rij` op het raster bij **twee tot vier**
stappen; de CSS hangt de hele omschakeling daaraan op, zodat de opmaak op
één plek staat. In de kolomvorm is de breedte begrensd op 48rem: een regel
die over een breed scherm doorloopt is niet te lezen.

Eén stap telt niet als rij. De rijvorm houdt bovenaan vijf rem vrij voor de
lijn, en bij één stap is er geen lijn -- één punt verbindt niets. Zonder
die ondergrens staat er tachtig pixels leegte boven een enkele stap.

**Wat er gebeurt bij elk aantal:**

| Stappen | Breed scherm (≥64rem)               | Smaller                      |
| ------- | ----------------------------------- | ---------------------------- |
| 0       | Het hele onderdeel valt van de site | idem                         |
| 1       | Kolom, compact, geen lijn           | idem                         |
| 2-4     | Rij, lijn erboven met uitloop       | Kolom, lijn door de kantlijn |
| 5+      | Kolom, lijn door de kantlijn        | idem                         |

### Het maximum: zes

`WorkStep::MAXIMUM` staat op zes, en dat is **een inhoudelijke grens en
geen technische**. Een werkwijze is geen catalogus maar een verhaal dat een
bezoeker moet kunnen onthouden; drie tot zes stappen onthoud je, negen is
geen werkwijze meer maar een projectplan. Wie een zevende nodig heeft kan
er bijna altijd beter twee samenvoegen -- en dat zegt het scherm er ook
bij, in plaats van alleen te weigeren.

Praktisch telt mee dat de kolomvorm zo hoog is als de som van alle stappen.
Bij zes is dat zo'n 550 beeldpunten; bij tien is het geen sectie meer maar
een pagina.

De grens staat op drie plekken, en dat is met opzet:

- **`WorkStep::MAXIMUM`** is het getal zelf.
- **`WorkStepController::store()`** weigert een stap erboven, met een
  melding die uitlegt waarom. Op de server, want een uitgeschakelde knop
  is geen grendel. Niet in het formulier: de grens gaat niet over een veld
  maar over de lijst, en een foutmelding onder een invoerveld zou wijzen
  naar iets dat niet verkeerd is ingevuld.
- **Het beheerscherm** schakelt de knop uit zodra je er bent, met dezelfde
  uitleg eronder.

**Het alternatief dat het niet werd**: op de voorpagina een paar stappen
tonen met de rest achter _Lees hoe ik werk_, zoals de projecten doen. Dat
lost de lengte ook op, maar het verandert wat het blok zegt -- "01 tot en
met 04, en er zijn er nog vijf" leest als een onvolledige belofte. Bij een
project kan dat wel, want dat staat op zichzelf; een stap niet.

Moet dat getal ooit omhoog, dan is het één constante. Maar denk dan eerst
na of er niet twee stappen zijn die eigenlijk één stap zijn.

### De uitloop

De nummers staan links in hun kolom, dus het laatste nummer zit op
driekwart van de breedte. Een lijn die daar ophoudt leest als een streepje
dat toevallig tussen vier punten past, met een kwart leegte ernaast. Er komt
daarom een uitloop bij tot aan de rand -- wat het oude, vaste pad ook deed.

Die uitlooppunten staan bewust niet in de lijst waarop de posities worden
opgezocht: een punt dat geen stap is hoort daar niet bij. Gevolg is dat de
laatste stap oplicht vóórdat de lijn klaar is, en dat de staart daarna nog
doorloopt. Dat is ook hoe het hoort te lezen.

## De pagina `/werkwijze`

Op de voorpagina staan de stappen als korte kaarten; hier krijgt elke stap
de ruimte die daar niet is.

**Drie grendels, en de derde onderscheidt hem van `/projecten`:**

1. Het onderdeel moet aanstaan op de indelingspagina.
2. Er moet minstens één stap online staan.
3. **Minstens één van die stappen moet een verhaal hebben, in de taal van
   de bezoeker.**

Die derde is de regel die telt. Bij de projecten is elk item op zichzelf de
moeite; hier zijn de kaarten op de voorpagina het hele verhaal zolang
niemand er iets bij heeft geschreven. Een pagina die precies dezelfde vier
zinnen herhaalt is een omweg.

Om dezelfde reden verschijnt de knop ernaartoe pas als die pagina echt
bestaat -- en de server beslist dat, niet het scherm
(`PublicWerkwijzeController::ietsTeLezen()`). Zou het scherm zelf gaan
kijken of er ergens een verhaal is, dan wijst de knop vroeg of laat naar
een 404.

**In de taal van de bezoeker** en niet "in het Nederlands": schreef de
eigenaar zijn verhalen alleen in het Nederlands, dan heeft een Engelse
bezoeker daar niets aan en hoort die pagina voor hém niet te bestaan.

Een stap zónder verhaal blijft er wel gewoon op staan, met zijn korte tekst.
Hem weglaten zou de nummering onderbreken, en dan klopt "stap 2" op deze
pagina niet meer met "stap 2" op de voorpagina.

## De routes

| Route                            | Naam                      | Wat              |
| -------------------------------- | ------------------------- | ---------------- |
| `GET werkwijze`                  | `werkwijze`               | De hele pagina   |
| `GET website/werkwijze`          | `website.werkwijze.index` | Het beheerscherm |
| `POST website/werkwijze`         | `.store`                  |                  |
| `PUT website/werkwijze/kop`      | `.kop`                    |                  |
| `PUT website/werkwijze/volgorde` | `.volgorde`               |                  |
| `PUT website/werkwijze/{id}`     | `.update`                 |                  |
| `PATCH .../{id}/online`          | `.online`                 |                  |
| `DELETE .../{id}`                | `.destroy`                |                  |

`kop` en `volgorde` staan vóór `{workStep}`: allemaal een PUT op dezelfde
plek, en de eerste die past wint. De publieke route heeft `TelBezoek`.

## De plek in de rij

`PageSectionKey::Werkwijze` bestond al, op `standaardPositie() => 3`. Er is
dus **niets omgenummerd** -- het risicovolste deel van de vorige module
speelde hier niet.

Wat er wél bijkwam:

- **Een teller in `AppServiceProvider::configurePageSections()`.** Die had
  dit onderdeel nooit, en dat kón ook niet: de stappen stonden in het
  Vue-bestand, dus het was per definitie gevuld. Nu kan de eigenaar ze
  allemaal offline zetten en hoort het blok van de site. Dat raakt de vier
  exacte lijsten in `LandingSectionsTest`, die daarom een stap zaait.
- **Een kop in `section_headings`.** Dus `Werkwijze` staat nu in
  `SectionHeadingSeeder::MET_KOP` én in de handgehouden kopie
  `SectionHeadingTest::MET_KOP`. Die twee horen bij elkaar.
- **Een `beheerRoute()`**, waardoor `DocumentationTest` een kaart in de
  handleiding eist.

## Het beheerscherm

Het vaste patroon -- knoppenrij, kaarttabel, bewerkvenster, sleepvenster --
met drie dingen die hier anders zijn omdat de volgorde hier betekenis heeft:

- **Het nummer staat in de tabel**, zoals de bezoeker het ziet, en een
  offline stap heeft er geen.
- **Het sleepvenster toont het nummer per regel en rekent mee terwijl je
  sleept.** Je ziet de nummering veranderen voordat je opslaat; dat is het
  enige dat verandert, en dus precies wat je wil zien.
- **Elke bevestiging zegt "de stappen erna schuiven een nummer op".** Bij
  een vraag weghalen gebeurt er niets met de rest; hier wel.

Het bewerkvenster heeft vijf velden per taal, het Engels onder een streep --
hetzelfde patroon als bij een vraag. Alleen de titel en de korte tekst zijn
verplicht.

## Tests

| Bestand                  | Wat het bewaakt                                                                                                                                                                                                               |
| ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `WorkStepCrudTest`       | Rechten per route, aanmaken, wijzigen, verwijderen, de lengtegrenzen, dat een nieuwe stap achteraan komt, dat wijzigen de plek niet raakt, beide talen, het logboek                                                           |
| `WorkStepPublishingTest` | Het blok verdwijnt én komt terug, alles offline telt als leeg, de volgorde, de terugval per veld (titel wel, samenvatting en duur niet), de teller op het indelingsscherm, en wanneer de knop naar de eigen pagina verschijnt |
| `WorkStepPaginaTest`     | De drie grendels, dat een stap zonder verhaal zijn plek houdt, dat een Nederlands verhaal geen Engelse pagina maakt, en dat het bezoek wordt geteld                                                                           |

Bijgewerkt: `LandingSectionsTest` (zaait een stap), `SectionHeadingTest`,
`SiteNavigatieTest`. `AppSidebarTest` en `DocumentationTest` passen zichzelf
aan zolang de zijbalkregel en de handleidingkaart er staan.

## Wat er bewust niet in zit

- **Geen pictogram per stap.** De stappen hebben al een nummer; nummers
  plús iconen is ruis. En het zou deze module koppelen aan de iconenlijst
  van Diensten, waardoor een icoon toevoegen daar hier opduikt.
- **Geen tijdlijn met echte datums.** Dit is hoe hij werkt, geen planning
  van één opdracht.
- **Geen koppeling met Diensten.** "Welke stappen horen bij welke dienst"
  klinkt logisch en levert een koppeltabel op voor iets wat niemand heeft
  gevraagd.
- **Geen eigen venster per stap** naast de pagina. Eén plek waar het
  verhaal staat is genoeg.
