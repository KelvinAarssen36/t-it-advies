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
| expertisepunten             | nee, 0–8      | 60 per punt | De onderdelen onder de tekst     |
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

**Zes op een breed scherm, drie op een telefoon**, en daarna blader je
verder. Zelfde afweging als bij de tijdlijn: een blok dat met elke dienst
langer wordt duwt de rest van de pagina onder de vouw, en dan leest
niemand meer waar het contactformulier staat.

Zes en niet meer, omdat een dienst op een breed scherm één regel in een
index is en geen kaart (zie hieronder): zes namen onder elkaar overzie je
nog in één blik, acht wordt een lijst die je moet lezen. Op een telefoon
drie, want daar staan ze als kaarten onder elkaar en horen de knopjes om
verder te bladeren nog in beeld te komen. De getallen staan als `BREED`
en `SMAL` in `DienstenSection.vue`.

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

Een sectiekop, en daaronder de **lens-index** ("Helder zicht", zie
[frontend en animatie](../frontend-en-animatie.md#de-landing-als-scène)).

### Breed: een index met een paneel ernaast

Vanaf de `tablet:`-grens (50rem) staan links de namen van de diensten
onder elkaar, elk met zijn pictogram in een ringetje. Rechts ligt
één glazen paneel met het verhaal van de dienst onder de lens: het
pictogram in een klein diafragma, de titel, de samenvatting, de
expertisepunten en -- alleen als er een heel verhaal is -- de knop "Lees
meer", die `DienstVenster` opent.

Waarom zo en niet als raster kaarten: zes gelijke tegels lezen als een
sjabloon, en je ziet pas wát er is als je ze allemaal hebt gelezen. Een
index laat in één oogopslag het aanbod zien; het paneel vertelt daarna
hoe.

- **Het paneel volgt de dienst die je aanwijst, aantikt of met de
  tabtoets bereikt**; standaard de eerste van de pagina. Aanwijzen met de
  muis heeft een drempel van 90 ms, zodat een muis die schuin over de
  lijst trekt het paneel niet laat flakkeren.
- **Een lichte rail links schuift naar de actieve naam** (de "lens").
  Gemeten met `offsetTop` van de rij, als `--lens-y` en een schaal
  `--lens-schaal`, zodat er alleen een transform verandert.
- **De namen zijn echte knoppen** met `aria-controls` naar het paneel en
  `aria-current` op de actieve. Ze openen niets; ze kiezen wat het paneel
  toont. Het venster opent alleen met de knop in het paneel, en die staat
  er alleen als er een verhaal is -- dezelfde regel als bij de kaart.
- **Alle panelen van de pagina liggen op elkaar** in één rastercel;
  alleen het actieve is zichtbaar, de andere zijn `inert` en
  `aria-hidden`. Daardoor is het blok zo hoog als het langste verhaal en
  verspringt de pagina eronder niet als je wisselt. Het paneel plakt
  (`sticky`) onder de kopbalk, zodat het bij een lange index in beeld
  blijft.
- **Eén dienst in totaal**: dan geen index, want een inhoudsopgave van één
  regel is geen inhoudsopgave. Het paneel krijgt de breedte alleen.
- Met een muis volgt een zacht spotlicht de cursor over de rand van het
  paneel (`spotlicht()` en `.brand-site-spot`).

### De maat: compact, uit de rollen

In de eerste ronde stonden de namen op 30 pixels en de titel in het paneel
op 36; het blok nam op een laptop een heel scherm voor zes woorden. Nu
komen alle maten uit de rollen in `basis.css`: de namen op `--site-maat-kop`
(17 tot 21 pixels), de paneeltitel op `--site-maat-titel-lang`, de tekst op
`--site-maat-lead`. Op 1440 breed ging het blok van 1202 naar 952 pixels,
op een telefoon van 1605 naar 1357.

### De binnenkomst: één beweging, van boven naar beneden

Dit is de beweging van deze sectie, en ze loopt in één richting: het licht
valt van boven naar beneden, zoals bij een lens die scherpstelt. Eén
tijdlijn (`binnenkomst()` in `DienstenSection.vue`), eenmalig, ruim een
seconde:

| Tijd   | Wat er gebeurt                                                        |
| ------ | --------------------------------------------------------------------- |
| 0      | De rail van de index tekent zich omlaag (`scaleY`, 0,95 s)            |
| 0,05 s | De namen komen gestaffeld omhoog, rij voor rij (icoon en naam samen)  |
| 0,08 s | Het paneel rolt van boven open (`clip-path: inset`, 0,9 s)            |
| 0,4 s  | De lens verschijnt naast de actieve naam                              |
| 0,42 s | De inhoud van het paneel komt op: teken, titel, zin, onderdelen, knop |
| ~0,6 s | De onderdelen lichten een voor een aan (CSS, `data-licht`)            |

Daarna gaan alle inline stijlen eraf. Staat de sectie al in beeld bij het
laden, dan speelt hij meteen; anders bij `top 82%`.

**Hier stonden drie bewegingen die elk hun eigen gang gingen**, en dat was
wat de eigenaar slordig vond: de namen schoven per regel uit een masker,
de pictogrammen ploften er met een schaal achteraan, de rail liep met de
scroll mee, en het paneel ging pas daarna open als een cirkel vanuit het
midden -- met halverwege een losse ronde vlek en een scheef afgesneden
hoek. Drie tempo's, twee richtingen, en een halve sectie die leeg bleef.
Gezien op een filmstrook tijdens het scrollen, niet op een stilstaand
beeld.

Bij bladeren komen alleen de nieuwe namen op (`rijenOp()`); het paneel
staat dan al open.

### Scrollen is geen aanwijzen

**Met een stilstaande muis boven de index wisselde het paneel tijdens het
scrollen van dienst**: de namen schuiven onder de wijzer door, en de
browser meldt dat als "de muis komt binnen". Gemeten: vier wissels in tien
wielstappen. Nu reageert een naam op `mousemove`, en alleen als de wijzer
zelf van plek is veranderd (`wijzerBewoog()`); doorschuivende inhoud
verandert de plek van de wijzer niet. Daarna: nul wissels.

**Wisselen tussen diensten is kort**: een kruisovergang met een kleine stap
omhoog, onder de 220 milliseconden, met CSS-overgangen zodat snel langs de
namen gaan de lopende overgang ombuigt. Dit gebeurt bij elke beweging van
de muis over de index; het hoort te voelen als een paneel dat volgt, niet
als een animatie waarop je wacht. De lens schuift in 0,3 seconde.

### Smal: kaarten onder elkaar

Onder 50rem bestaat aanwijzen niet en past een paneel naast een lijst
niet. Dan staat elke dienst als glazen kaart onder de vorige
(`FeatureCard`), met de samenvatting altijd zichtbaar (geklemd op vijf
regels) en "Lees meer" eronder als er een verhaal is.

**De kaart is een `<button>` zodra er een venster achter zit**, en anders
een `<article>`. Zo is hij met de tabtoets bereikbaar en met Enter te
bedienen, en is er geen knop die niets doet.

Breed of smal wordt met een `matchMedia`-luisteraar bepaald, en er staat
altijd maar één van de twee in de pagina: geen dubbele tekst voor een
schermlezer of een zoekmachine.

### Bladeren springt naar de kop van het onderdeel

Een nieuwe pagina is korter of langer dan de vorige, dus alles eronder
verschuift. Doe je daar niets aan, dan druk je op "volgende" en kijk je
ineens naar het onderdeel eronder -- en de knopjes schuiven weg onder je
muis, dus je volgende klik landt op de pager daarvan.

**Er is eerst geprobeerd het blok een vaste hoogte te geven.** Dat leverde
op een telefoon een gat van bijna twee schermen op onder de laatste
pagina. Nu springt de pagina naar de kop van dit onderdeel, met
`scrollNaar`. Bij een nieuwe pagina staat de lens weer op de eerste
dienst.

### Nieuwe rijen moeten zichtbaar gemaakt worden

Een kaart (smal) begint op doorzichtigheid nul en wordt pas zichtbaar
doordat `kaartenBinnen()` hem ophaalt (op elementen met `data-kaart`); een
naam in de index (breed) begint onder zijn masker. Verschijnt er iets
zonder dat de binnenkomst draait, dan blijft het weg.

Dat is één keer misgegaan bij het overgaan van een **breedtegrens**. Nu
draait `herstel()` (de index of de kaarten opnieuw opbouwen) na élke
wisseling van de inhoud: bij het bladeren én bij het overgaan van de grens
-- waar de hele opbouw wisselt van index naar kaarten, en ook het paneel
opnieuw begint (`bouwPaneel()`). Wat al in beeld staat speelt meteen; de
rest wacht op de scroll. Na de eerste binnenkomst speelt die niet opnieuw:
bij bladeren komen alleen de namen op.

### De onderdelen: een blok met tekens

Wat er onder een dienst valt ("monitoring", "back-ups", "updates",
"storingen") staat als **één blok gelijke vakjes** met een haarlijn
ertussen, zoals de kerngegevens: elk vakje een klein teken in een vak en
het steekwoord ernaast, met het kopje "Waar dit over gaat" erboven.
Hetzelfde blok in het paneel, op de kaart en in `DienstVenster`
([`DienstOnderdelen.vue`](../../../resources/js/components/site/DienstOnderdelen.vue)).
Hier stonden kleine pillen met een stip, en dat las als een verzameling
losse tags.

- **Twee kolommen zodra er plaats is, anders één**, zonder mediaquery
  (`minmax(max(8.5rem, 50% - 1px), 1fr)`). Een oneven laatste vakje neemt
  de hele rij. De lijnen tekent elk vakje zelf links en boven; het blok
  knipt de buitenkant weg, dus er staan alleen lijnen tussen de vakjes.
- **Het teken kiest de site zelf**, op grond van het woord
  ([`onderdeelIcoon.ts`](../../../resources/js/lib/onderdeelIcoon.ts)):
  een vaste lijst woordbegins in het Nederlands en het Engels
  (`back-up`/`backup` → back-up, `monitor`/`bewak` → hartslag, `update`/
  `patch` → verversen, `storing`/`incident` → sirene, enzovoort). Wat niet
  herkend wordt krijgt een vinkje: dat past bij elk steekwoord, terwijl een
  verkeerd geraden teken iets beweert. Er is bewust geen veld om per
  onderdeel een teken te kiezen; komt er een woord bij dat een eigen teken
  verdient, zet het dan in die lijst.
- **Niet klikbaar, en zo gedraagt het zich.** Onder de muis licht het
  vakje zacht op en wordt het teken cyaan; geen rand per vakje, geen
  handje, geen indrukken.
- **Een dienst die in beeld komt** laat zijn onderdelen na elkaar opkomen,
  met een korte flits op elk teken (`brand-site-onderdeel-licht`, 45 en
  70 ms ertussen). Pas als de sectie binnenkomt (`data-licht` op de
  stapel); daarvoor zou de eerste dienst zijn opkomst afspelen buiten
  beeld. Bij een wissel speelt het opnieuw.
- **Op een telefoon** is er geen paneel en geen muis: daar licht elk teken
  kort op terwijl het vakje het scherm in schuift, met de scroll mee en
  terug (`animation-timeline: view()`, alleen CSS). Een browser die dat niet
  kent toont het blok rustig.

Wie om minder beweging heeft gevraagd krijgt het paneel en de lens zonder
overgang: de inhoud wisselt meteen, de namen staan er al, het spoor is vol,
het paneel staat open en de onderdelen lichten niet op. Niets schuift.

De opmaak staat in
[`resources/css/site/diensten.css`](../../../resources/css/site/diensten.css).

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
| [`FeatureCard.vue`](../../../resources/js/components/site/FeatureCard.vue)                  | Eén dienst als kaart, op een smal scherm.                         |
| [`diensten.css`](../../../resources/css/site/diensten.css)                                  | De opmaak van de index, het paneel en de kaarten.                 |
| [`DienstIcoon.vue`](../../../resources/js/components/site/DienstIcoon.vue)                  | De enige koppeling naar de iconenbibliotheek.                     |
| [`ServiceCrudTest`](../../../tests/Feature/Website/ServiceCrudTest.php)                     | Rechten, aanmaken, wijzigen, verwijderen, validatie, het logboek. |
| [`ServicePointsTest`](../../../tests/Feature/Website/ServicePointsTest.php)                 | Het maximum, de volgorde, het gelijktrekken en de terugval.       |
| [`ServicePublishingTest`](../../../tests/Feature/Website/ServicePublishingTest.php)         | Online/offline, de volgorde en het leeg-gedrag van de sectie.     |
| [`ServiceHeadingTest`](../../../tests/Feature/Website/ServiceHeadingTest.php)               | De kop en de terugval per veld.                                   |
