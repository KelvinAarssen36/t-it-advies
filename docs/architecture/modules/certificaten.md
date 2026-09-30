# De certificaten

Wat de eigenaar heeft gehaald en bij wie, als een raster van tegels op de
landingspagina: het logo van de uitgever groot bovenaan, de naam
eronder. Klik je erop, dan opent er een venster met het
certificaatnummer en de toelichting. Daaronder staat optioneel een kort
lijstje met zijn opleiding.

> **Waarom dit een module is.** De tijdlijn vertelt wát hij heeft
> gedaan; dit is het bewijs erbij. Voor een IT-adviseur is dat geen
> detail -- een bezoeker die een logo van Microsoft of Cisco herkent is
> in één blik overtuigd van iets waar drie alinea's tekst niet tegenop
> kunnen.

| Onderdeel           | Waar de klant het beheert              | Waar het vandaan komt                     |
| ------------------- | -------------------------------------- | ----------------------------------------- |
| De **certificaten** | Website → Certificaten                 | `certificates`                            |
| De **opleidingen**  | Tweede blok op datzelfde scherm        | `educations`                              |
| De **kop** erboven  | Knop "Kop erboven" op datzelfde scherm | `section_headings`, de rij `certificaten` |
| De **volgorde**     | Knop "Volgorde", slepen in een venster | `certificates.position`                   |

Dit is de vierde module. Van [Diensten](diensten.md) komt de lijst met
een eigen volgorde en een venster per item; van [Ervaring](ervaring.md)
het logo met zijn uitsnijvenster en de maandkiezer. De kop draait op de
gedeelde tabel; zie [kopteksten](../kopteksten.md).

## Wat er in een certificaat staat

| Veld                    | Verplicht     | Lengte | Waarvoor                                |
| ----------------------- | ------------- | ------ | --------------------------------------- |
| `title_nl` / `title_en` | nl ja, en nee | 120    | De naam van het certificaat             |
| `issuer`                | ja            | 120    | Wie het heeft uitgegeven                |
| `logo_path`             | nee           | —      | Het logo van die uitgever               |
| `issued_on`             | ja            | maand  | Wanneer hij het haalde                  |
| `expires_on`            | nee           | maand  | Tot wanneer het geldig is               |
| `credential_id`         | nee           | 120    | Het nummer van de uitgever              |
| `body_nl` / `body_en`   | nee           | 2000   | Korte toelichting, in het detailvenster |
| `published`             | —             | bool   | Online of offline                       |
| `position`              | —             | int    | De volgorde                             |

**De kolommen heten `title` en `body`, net als bij een dienst.** Dat is
geen luiheid maar hergebruik: `TranslateController` kent die veldnamen
al, dus de vertaalknop werkte hier zonder dat er iets bij hoefde.

**De uitgever wordt niet vertaald.** "Microsoft" is een eigennaam, net
als `Experience::organisation`. Een veld dat in beide talen hetzelfde is
hoor je niet twee keer te laten invullen.

**De datums staan op maandnauwkeurigheid.** Je haalt een certificaat in
maart 2024; de dag erbij zetten suggereert een precisie die niemand
nodig heeft en die de klant moet opzoeken. In de database staat het toch
als datum -- de eerste van de maand -- zodat je erop kunt sorteren en
vergelijken. Zie `SchoneVelden::maand()`.

### Geen keuzelijst met pictogrammen

Anders dan bij Diensten en Ervaring. Daar kiest de klant er een; hier is
het **logo van de uitgever** wat de tegel zijn gezicht geeft, en staat er
geen, dan komt er één vast badge-teken. Een keuzelijst met acht
pictogrammen zou een keuze zijn die niets oplevert: een bezoeker scant
logo's die hij herkent, niet abstracte tekentjes.

### Verlopen is iets voor het portaal, niet voor de website

`Certificate::verlopen()` zegt of de `expires_on` voorbij is. Er zijn
twee dingen die daar wél en niet mee gebeuren, en ze horen bij elkaar.

**Op de website gebeurt er niets.** Het certificaat blijft staan --
behaald is behaald -- en er staat nergens dát het verlopen is. Er heeft
een "Verlopen"-label op de tegel gestaan; dat is er bewust weer af
gehaald. Een bezoeker kijkt naar een etalage en hoeft niet te weten dat
één papiertje aan vernieuwing toe is.

Dat betekent ook: **geen "geldig tot" met een datum van vorig jaar
erachter.** Dat is dezelfde mededeling in andere woorden. De server
stuurt die datum daarom niet mee zodra hij voorbij is; zie
`HomeController::certificaat()`. Een bezoeker ziet geen verschil tussen
"verlopen" en "verloopt niet", en dat is precies de bedoeling.

**In het portaal staat het nadrukkelijk wél.** Een label in de lijst en
een mededeling erboven met het aantal. Daar is het iets om over te
beslissen: de eigenaar bepaalt of hij het weghaalt, offline zet of laat
staan. Wij halen nooit iets van zijn site af zonder dat hij het zegt.

De berekening staat op het model en niet in Vue, omdat er twee plekken
op leunen: het beheerscherm toont hem, en de website gebruikt hem juist
om de datum wég te laten. Zouden die twee elk hun eigen som doen, dan
lopen ze op een dag uiteen.

`CertificateExpiryTest` legt allebei de helften vast.

### De terugval tussen de talen

Dezelfde verdeling als overal (zie [vertalingen](../vertalingen.md)):
**verplicht valt terug op het Nederlands, optioneel wordt weggelaten.**

| Veld        | Engels leeg                                          |
| ----------- | ---------------------------------------------------- |
| Naam        | Terugvallen -- een tegel zonder naam is stuk.        |
| Toelichting | Weglaten -- dan is de tegel gewoon niet aanklikbaar. |

Dat de naam terugvalt is hier zelden een probleem: de naam van een
certificaat is meestal al Engels, en het scherm zegt dat er ook bij.

## Wat er in een opleiding staat

Bewust een stuk kaler. Dit is een lijstje van twee of drie regels onder
het raster, geen tweede etalage.

| Veld                    | Verplicht     | Lengte | Waarvoor                    |
| ----------------------- | ------------- | ------ | --------------------------- |
| `title_nl` / `title_en` | nl ja, en nee | 120    | De opleiding of het diploma |
| `institution`           | ja            | 120    | De school of hogeschool     |
| `level_nl` / `level_en` | nee           | 60     | Bijvoorbeeld "MBO niveau 4" |
| `started_on`            | ja            | maand  | Begin                       |
| `ended_on`              | nee           | maand  | Eind; leeg betekent "heden" |
| `published`             | —             | bool   | Online of offline           |

**Geen logo, geen detailvenster, geen certificaatnummer -- en geen
`position`.** Die laatste is de opvallendste: overal elders in dit
project bepaalt de klant de volgorde met slepen. Hier niet, want bij twee
of drie opleidingen is "laatst afgeronde bovenaan" altijd de goede
volgorde, en dan is een sleepvenster gereedschap voor een probleem dat
niet bestaat. Zie `Education::scopeOpPeriode()`.

De periode leest als **"2018 — 2022"**, alleen met jaartallen en anders
dan bij een certificaat. Een opleiding duurt jaren; de maand erbij zetten
maakt de regel langer zonder dat iemand er iets aan heeft. In het
formulier kiest hij wél een maand, want dat is wat de maandkiezer doet en
het sorteert nauwkeuriger.

### Waarom een eigen tabel en geen soort binnen `certificates`

De velden verschillen echt: een certificaat heeft een behaaldatum en een
geldigheidsdatum, een opleiding een periode. Alles in één tabel zou een
rij kolommen opleveren die de helft van de tijd leeg is, met validatie
die per soort andersom werkt.

Op de **pagina** zijn het wél één ding: `PageSectionKey::Certificaten`
toont allebei, en de teller telt ze bij elkaar op. Een blok met alleen
een opleiding erin is niet leeg.

### `educations` en niet `education`

De tabel heet `educations` en het model zegt dat er expliciet bij met
`protected $table`. Laravel leidt de tabelnaam normaal af uit de
klassenaam, maar "education" is in het Engels niet-telbaar: de
meervoudsvormer laat hem staan en zoekt naar een tabel `education`. Dat
gaf een `no such table` op élke pagina die de teller aanriep -- dus op de
hele website. Een tabel in het enkelvoud tussen `certificates` en
`services` leest als een fout, dus de naam bleef en het model kreeg de
regel erbij.

## Hoe het eruitziet op de landingspagina

De sectiekop zoals overal (opschrift, titel, zin), daaronder het raster.

**De tegel**: het logo in een lichte plaat bovenaan, daaronder de naam
van het certificaat, en klein eronder de uitgever met het jaar. Is er een
toelichting of een certificaatnummer, dan is de tegel een echte
`<button>` -- dus ook met het toetsenbord te bereiken -- en opent hij
`CertificaatVenster.vue`. Is er geen van beide, dan is het een `div` en
doet hij niets. Dezelfde regel als bij een dienst zonder lang verhaal:
een venster dat opengaat met niets erin is erger dan geen venster.

De tekst staat **gecentreerd**. Een raster van vier smalle tegels met
links uitgelijnde tekst leest als een tabel die scheef staat;
gecentreerd hangt alles aan het logo erboven.

**Bewegen** met wat er al ligt: `kaartenBinnen()` voor het opkomen en
`kantelKaarten()` voor het aanwijzen. Bij het aanwijzen licht **de plaat
met het logo** op en niet de hele tegel -- het logo is waar je naar
kijkt, en acht tegels die allemaal iets doen is een kermis.

**Elke tegel reageert op aanwijzen, ook die zonder venster erachter.**
Dat was eerst niet zo: alleen een klikbare tegel kwam op. In een raster
van negen zag je dan vier tegels bewegen en vijf niet, en dat leest als
kapot in plaats van als een verschil met betekenis. Wat er wél alleen
bij een klikbare tegel hoort is het handje en de focusrand -- die twee
zeggen "hier gebeurt iets als je klikt"; het oplichten zegt alleen "ik
zie je".

### Bladeren

**Acht op een breed scherm, vier op een telefoon**, met dezelfde
knopjes als bij de diensten en de tijdlijn, inclusief het terugspringen
naar de bovenkant van het blok.

Meer dan de vier bij de diensten omdat een tegel hier een logo met twee
regels eronder is en geen kaart met een alinea: er passen twee rijen van
vier in dezelfde ruimte. Het raster staat op **twee kolommen onder de
40rem en vier daarboven**, dus allebei de keren is een volle pagina twee
rijen. Die getallen en dat raster horen bij elkaar; verander je `BREED`
of `SMAL` in `CertificatenSection.vue`, verander dan ook
`.brand-certificaten` in `app.css`.

### De opleidingen eronder

Een streep, een klein opschrift, en per regel de opleiding met de
instelling eronder en rechts het niveau met de periode. Zijn er geen, dan
staat de streep er ook niet.

Het is een `grid` en geen `flex` met `justify-between`, zodat de periodes
van meerdere regels onder elkaar uitgelijnd staan in plaats van elk op
hun eigen plek.

## Het beheerscherm

`website/Certificaten`, met een eigen regel **Certificaten** in de
zijbalk onder Website, na Ervaring.

Vier knoppen in de kop: _Bekijk het resultaat_ (met `VerlaatPortaal`),
_Kop erboven_, _Volgorde_ en _Nieuw certificaat_.

Daaronder de lijst in `brand-tabel-kaarten` -- dezelfde kaarttabel die op
een telefoon leesbaar blijft -- met per regel het logo, de naam, de
uitgever, de datums, het online-schuifje en de knoppen
bewerken/verwijderen. Is de lijst leeg, dan dezelfde uitleg als bij de
andere modules: het onderdeel staat aan maar is leeg, dus het staat niet
op de website.

Boven de lijst staan twee mededelingen als ze van toepassing zijn: dat er
meer dan één pagina op de website staat, en hoeveel certificaten er
verlopen zijn.

**Geen zoekveld en geen paginering**, net als bij de diensten: een
handvol certificaten is geen lijst om in te zoeken.

Onder die lijst een tweede, kleiner blok **Opleidingen**, met zijn eigen
knop en een kortere tabel. Twee lijsten op één scherm, want op de website
zijn ze samen één blok -- ze uit elkaar trekken zou betekenen dat de
eigenaar twee plekken moet onthouden voor iets dat hij als één ding
ziet.

### De vensters

1. **Nieuw certificaat / bewerken** — twee stappen: stap 1 het Nederlands
   met het logo en de datums, stap 2 het Engels met de Nederlandse bron
   erboven. Eén keer opslaan aan het eind, want een half opgeslagen
   certificaat staat live.
2. **Nieuwe opleiding / bewerken** — hetzelfde in het klein.
3. **Volgorde** — `SortableList` in een venster; slepen én
   pijltjestoetsen. Alleen voor de certificaten.
4. **Kop erboven** — de gedeelde `KoptekstDialoog`.

Overal de vaste bevestigingsstroom: twee vragen bij bewerken, één bij
aanmaken en één bij verwijderen.

**Het formulier gaat altijd als `multipart/form-data` de deur uit**, ook
zonder bestand. Een upload kan niet als JSON, en een wijziging moet dan
via POST met `_method` -- Laravel leest dat alleen uit formuliergegevens.
Eén weg dus, in plaats van twee die net iets anders werken. Zelfde
afweging als bij `ErvaringDialoog`.

## Wat er gedeeld werd met de andere modules

Deze module heeft drie stukken uit bestaande bestanden getrokken in
plaats van ze te kopiëren:

| Nieuw                                                                          | Wat er anders twee keer had gestaan                                                      |
| ------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------- |
| [`LogoVelden`](../../../app/Http/Requests/Website/Concerns/LogoVelden.php)     | Zeventig regels validatie voor de upload en het uitsnijvenster, uit `ExperienceRequest`. |
| [`SchoneVelden`](../../../app/Http/Requests/Website/Concerns/SchoneVelden.php) | `tekst()` en `maand()`, dezelfde twee omzettingen.                                       |
| `Datum::maanden()` en `Datum::jaren()`                                         | De lijsten voor de maandkiezer, uit `ExperienceController`.                              |

`Datum::jaren()` kreeg er een `$vooruit` bij, want "geldig tot" ligt per
definitie in de toekomst en een lijst die bij dit jaar ophoudt is daar
onbruikbaar. Overal elders blijft hij nul: een ervaring die volgend jaar
begint bestaat niet.

## Wat er bewust niet in zit

- **Geen verificatielink.** Het certificaatnummer staat er wel, maar er
  is geen knop die naar de controlepagina van de uitgever gaat. Komt hij
  later, dan is dat één kolom en één knop in het detailvenster.
- **Geen eigen tabel voor uitgevers.** Vijf certificaten van Microsoft
  betekent vijf keer hetzelfde logo uploaden. Dat is vervelend maar geen
  ramp, en een tweede beheerscherm ervoor weegt daar nu niet tegenop.
- **Geen maximum aantal certificaten.** Wat begrensd is, is hoeveel er
  tegelijk te zien zijn.

## Wat waar staat

| Bestand                                                                                             | Wat het doet                                                           |
| --------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| [`Certificate`](../../../app/Models/Certificate.php)                                                | De rij, de terugval per veld, `verlopen()`, het opruimen van het logo. |
| [`Education`](../../../app/Models/Education.php)                                                    | De kleine lijst en zijn eigen volgorde.                                |
| [`CertificateController`](../../../app/Http/Controllers/Website/CertificateController.php)          | Allebei de lijsten, de volgorde en de kop.                             |
| [`CertificateRequest`](../../../app/Http/Requests/Website/CertificateRequest.php)                   | De validatie, inclusief de omgekeerde geldigheid.                      |
| [`EducationRequest`](../../../app/Http/Requests/Website/EducationRequest.php)                       | Hetzelfde, korter.                                                     |
| [`CertificatenSection.vue`](../../../resources/js/components/site/sections/CertificatenSection.vue) | Wat de bezoeker ziet, met het bladeren.                                |
| [`CertificaatTegel.vue`](../../../resources/js/components/site/CertificaatTegel.vue)                | Eén tegel; knop of vlak, afhankelijk van de inhoud.                    |
| [`CertificaatVenster.vue`](../../../resources/js/components/site/CertificaatVenster.vue)            | De details achter een tegel.                                           |
| [`Certificaten.vue`](../../../resources/js/pages/website/Certificaten.vue)                          | Het beheerscherm, met allebei de lijsten.                              |
| [`CertificateCrudTest`](../../../tests/Feature/Website/CertificateCrudTest.php)                     | Rechten per route, beide talen, het logo, het logboek.                 |
| [`CertificatePublishingTest`](../../../tests/Feature/Website/CertificatePublishingTest.php)         | Online/offline, de volgorde, en het leeglopen van het blok.            |
| [`CertificateExpiryTest`](../../../tests/Feature/Website/CertificateExpiryTest.php)                 | Dat verlopen een label is en geen verdwijntruc.                        |
| [`EducationCrudTest`](../../../tests/Feature/Website/EducationCrudTest.php)                         | De kleine lijst en zijn automatische volgorde.                         |

## Seeddata

**Alleen de koptekst**, als startpunt dat de eigenaar omschrijft. Er
worden geen certificaten geseed: zonder inhoud staat het onderdeel
vanzelf niet op de website, met een uitroepteken op het indelingsscherm
zodat hij ziet waarom.

Komen zijn échte certificaten er als productiedata bij, dan gaan ze in
een `CertificateSeeder` naast de andere -- zie
[uitleg-voor-de-eigenaar](../uitleg-voor-de-eigenaar.md) voor wat er wél
en niet geseed hoort te worden.
