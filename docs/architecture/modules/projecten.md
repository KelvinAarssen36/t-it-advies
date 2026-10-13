# Module Projecten

De etalage van de eigenaar: wat hij heeft gedaan, in de volgorde die hij
zelf kiest, met hoogstens één stuk dat vooraan staat.

## Waarom dit geen tweede Ervaring is

Die vraag komt als eerste, want de twee lijken op elkaar: allebei een lijst
met een organisatie, een rol en een periode. Het verschil zit in wat ze
beantwoorden.

|             | Ervaring                       | Projecten               |
| ----------- | ------------------------------ | ----------------------- |
| De vraag    | Waar heb je gewerkt?           | Wat heb je gedáán?      |
| De volgorde | Vast, op datum, nieuwste eerst | De eigenaar sleept      |
| Uitlichten  | Nee                            | Zoveel als hij wil      |
| Eigen adres | Nee, een venster               | Ja, `/projecten/{slug}` |

Een loopbaan is chronologie en die hoort niet versleepbaar te zijn -- dan
kun je de volgorde van je eigen geschiedenis veranderen. Een etalage is het
omgekeerde: daar bepaalt de eigenaar wat het eerst opvalt, en dat is zelden
het meest recente.

## Het type maakt de module breed

De module heet Projecten, maar lang niet elk item is een klassiek project.
Het kan een interim-opdracht zijn, een migratie, een audit of een stage.
Daarom draagt elk item zijn eigen woord, uit
[`ProjectType`](../../../app/Enums/ProjectType.php):

`project`, `opdracht`, `interim`, `consultancy`, `implementatie`,
`migratie`, `audit`, `transformatie`, `stage`, `anders`.

**Een vaste lijst met één uitweg, en geen vrij tekstveld.** Dezelfde
afweging als bij de onderwerpen van het contactformulier: een vrij veld
levert binnen een jaar "Migratie", "migratie" en "Migratieproject" op, en
dan staan er drie badges op de site die hetzelfde betekenen. Een vaste lijst
vertaalt bovendien centraal -- de eigenaar hoeft "Consultancy" niet tien
keer in twee talen te typen.

De uitweg is `Anders`: dan vult hij `type_label_nl` en `type_label_en` zelf
in. Twee dingen horen daarbij:

- **`type_label_nl` is dan verplicht** (`required_if`), anders staat er
  letterlijk "Anders" op de badge van zijn website.
- **Kiest hij daarna weer een gewoon soort, dan worden beide labelvelden op
  `null` gezet.** Zou het oude woord blijven staan, dan komt het terug zodra
  iemand ooit weer "Anders" kiest -- met een label van een project van twee
  jaar geleden erin. Zie `ProjectRequest::gegevens()`.

Een soort erbij is één `case` en één regel in `label()`. De database hoeft
niet mee.

## Het datamodel

Eén tabel `projects`. Negen inhoudelijke velden en drie voor de weergave --
bewust geen twintig.

| Kolom                   | Type                          | Waarom                        |
| ----------------------- | ----------------------------- | ----------------------------- |
| `position`              | smallint, index               | Slepen                        |
| `published`             | bool, default true            | Aan/uit, eigen route          |
| `featured`              | bool, default false           | Uitgelicht, eigen route       |
| `slug`                  | string(160), unique           | Het adres van de detailpagina |
| `type`                  | string(32), default `project` | Cast naar `ProjectType`       |
| `type_label_nl` / `_en` | string(60), nullable          | Alleen bij `anders`           |
| `title_nl` / `_en`      | string(120)                   | `_en` valt terug              |
| `organisation`          | string(120)                   | Niet tweetalig                |
| `role_nl` / `_en`       | string(120)                   | `_en` valt terug              |
| `started_on`            | date, index                   | Dag altijd 1                  |
| `ended_on`              | date, nullable                | Leeg = loopt nog              |
| `summary_nl` / `_en`    | string(300)                   | Geen terugval                 |
| `body_nl` / `_en`       | text, nullable                | Geen terugval                 |
| `result_nl` / `_en`     | text, nullable                | Geen terugval                 |
| `image_path`            | string, nullable              | Optioneel                     |
| `machine_translated_at` | timestamp, nullable           | Het merkje                    |

**Wat er bewust niet in zit:** locatie, een adres van de organisatie, en een
aparte tabel voor expertise of technologie. Die laatste zou een tabel, een
`FormRequest`, vier routes en een sleepvenster kosten voor iets dat nog
niemand heeft gemist. Komt hij later, dan gaat dat precies zoals de punten
van een dienst -- een eigen tabel, want met een JSON-kolom kun je geen
foutmelding bij de juiste regel zetten.

### De instellingen

Eén rij in `project_settings`, met één kolom `layout` (`lijst` of
`raster`). Zie _De vorm is instelbaar_ verderop.

## De periode

Letterlijk het patroon van Ervaring, en dat is geen gemakzucht: het is
uitgedacht en het werkt.

- **Twee `date`-kolommen waarvan alleen de maand en het jaar betekenis
  hebben.** De dag staat altijd op 1 en komt nergens op een scherm. Een
  echte datumkolom is toch handiger dan twee getallen: sorteren, vergelijken
  en opmaken werken dan gewoon.
- **`ended_on` leeg betekent "loopt nog".** Geen ontbrekende waarde maar een
  betekenisvolle.
- **Het formulier werkt met `"2021-03"`** via
  [`MaandKiezer`](../../../resources/js/components/MaandKiezer.vue), de
  validatie is `date_format:Y-m` met `after_or_equal:started_on`, en
  `SchoneVelden::maand()` maakt er de eerste van die maand van.
- **Het schuifje "Dit project loopt nog" stuurt expliciet `ended_on: ''`**,
  ook als er nog een maand in het veld stond van vóór het aanvinken.

**`periode()` en `duur()` worden op de server opgemaakt, nooit in de
browser.** Anders hangt de maandnaam af van de taal van het
besturingssysteem in plaats van die van de bezoeker. De `+1` in `duur()` is
verplicht: maart tot en met maart is één maand werk en niet nul.

## De talen

| Veld                   | Engels leeg                       |
| ---------------------- | --------------------------------- |
| Titel, rol, eigen type | **Terugvallen** op het Nederlands |
| Samenvatting           | **Weglaten**                      |
| Omschrijving           | **Weglaten**                      |
| Resultaat              | **Weglaten**                      |

Het verschil zit in de soort tekst. Een titel en een rol zijn namen:
"Migratie Exchange" leest een Engelse bezoeker prima, en een kaart zonder
titel bestaat niet. Proza is iets anders -- een Nederlandse alinea tussen
Engelse tekst leest als een fout, en weglaten is dan eerlijker.

De organisatie valt daarbuiten: een bedrijfsnaam is een eigennaam en wordt
niet vertaald, net als `Experience::organisation`. Het type valt er ook
buiten zolang het uit de lijst komt -- dat vertaalt centraal via de enum.

De vertaalknop stuurt vier velden: de titel, de samenvatting, de
omschrijving en het resultaat. De rol en het eigen type zijn korte woorden
die de eigenaar sneller zelf typt dan nakijkt, en de vertaaldienst kapt af
boven de twaalf velden.

## Het beeld

Eigen blok `media.project` in [`config/media.php`](../../../config/media.php)
met dezelfde getallen als het portret bij "Over mij": hoogstens 1536 kB,
kortste zijde 240, langste 3000. **Een eigen blok en niet `portret`
hergebruikt**, want het zijn twee verschillende soorten beeld -- zou de
eigenaar ooit willen dat zijn portret scherper mag zijn, dan hoort dat niet
stil zijn projectlogo's mee te veranderen.

**Vierkant op 640 beeldpunten.** Dat is geen smaak maar een gevolg:
`Logo::bewaar()` tekent altijd in een vierkant
(`imagecreatetruecolor($maat, $maat)`), dus een breed hero-beeld bestaat in
dit project niet. Landschap zou de gedeelde klasse raken en daarmee ook de
certificaten en Ervaring.

De rest is het bekende patroon: de drie gevallen bij een opslag (nieuw
bestand / niets meegekomen / weghalen), het oude bestand pas weg ná een
gelukte opslag, en opruimen op `deleting` en niet op `deleted`.

**Geen afbeelding is een geldige toestand.** Er is geen standaardbeeld: de
kaart vult die plek met de eerste letter van de organisatie, in de
huisstijl. Op het uitgelichte blok vervalt de beeldkolom helemaal en loopt
de tekst door -- een plaatsvervangend vlak van driehonderd beeldpunten is
daar geen accent meer maar een gat.

> **Bekend gat.** [`beeldmerk.ts`](../../../resources/js/lib/beeldmerk.ts)
> kent alleen de logogrenzen (48 beeldpunten), dus de browser keurt een
> beeld van 100 goed en de server weigert het -- met een duidelijke melding,
> maar wel pas na het versturen. Dat geldt vandaag net zo voor het portret
> bij "Over mij"; het oplossen betekent grenzen door `LogoKiezer` voeren en
> raakt vier andere vensters.

## De uitgelichte projecten

De eigenaar zet een sterretje bij alles wat hij vooraan wil hebben. Dat
mogen er zoveel zijn als hij wil, en nul mag ook.

- **`PATCH website/projecten/{project}/uitlichten`** zet de vlag van dít
  project om, en laat de rest met rust.
- **Nog een keer op hetzelfde project drukken zet het uit.** Zo kan hij ook
  tijdelijk niets uitlichten, zonder dat daar een tweede knop voor nodig is.
- **Uitlichten raakt `position` niet.** Uitlichten en sorteren zijn twee
  verschillende dingen -- en die volgorde doet er juist toe: hij bepaalt
  welke dia het eerst komt en welk project groot op de voorpagina staat.
- **Een offline project mag uitgelicht worden**; dat is een voorbereiding.
  Publiek geldt overal `online() AND featured`.
- **Een project zonder sterretje neemt die plek nooit over.** Staat het
  bovenste uitgelichte project offline, dan schuift het volgende
  _uitgelichte_ project door -- nooit het eerstvolgende uit de lijst. Stil
  iets uitlichten is iets kiezen wat de eigenaar niet heeft gekozen.

### Waarom dit ooit "precies één" was

De eerste versie dwong er één af, met een transactie die de rest uitzette.
Dat is omgedraaid toen de slideshow op `/projecten` erbij kwam: een
slideshow heeft meer dan één dia nodig, en een keuze tussen "één uitlichten"
en "een slideshow" is geen keuze -- dan licht je gewoon meer uit.

Wat ervan overblijft is de regel dat de **voorpagina** er één groot toont.
`Project::uitgelicht()` geeft daarvoor het bovenste online uitgelichte
project terug, en `OP_DE_VOORPAGINA` (3) kaarten staan ernaast.

[`ProjectUitgelichtTest`](../../../tests/Feature/Website/ProjectUitgelichtTest.php)
bewaakt het geheel: dat een tweede de eerste laat staan, dat de voorpagina
de bovenste kiest, dat een project zonder sterretje nooit invalt, en dat het
overzicht een project in de slideshow óf in de lijst zet en nooit in beide.

## De publieke kant

### De voorpagina

De uitgelichte projecten groot -- **als slideshow zodra het er meer dan één
zijn**, precies dezelfde als bovenaan `/projecten` -- daaronder hoogstens
`OP_DE_VOORPAGINA` (3) andere kaarten, en een knop _Bekijk alle projecten_
als er meer zijn.

**Een uitgelicht project staat niet nog eens tussen de kaarten.** Een
project staat in de slideshow óf in de rij, nooit in allebei; anders lijkt
het alsof de eigenaar het twee keer heeft ingevoerd. Dezelfde regel als op
`/projecten`.

**Een kort blok, en de rest achter een knop.** Een etalage die met elk
project langer wordt maakt de voorpagina op den duur onleesbaar -- en dit is
de module waarvan je mag aannemen dat er elk jaar iets bij komt.

**Het bladeren gebeurt dus niet in het blok maar op een eigen pagina**, en
dat is anders dan bij de vragenlijst. Daar staan álle vragen in de DOM en is
alleen de huidige bladzijde zichtbaar, juist omdat een zoekmachine ze anders
niet ziet. Hier heeft elk project zijn eigen adres, dus die vindbaarheid zit
al ergens anders -- en dan is het onnodig om tien kaarten mee te sturen die
niemand ziet.

### `/projecten`

Alle online projecten in de volgorde uit het beheer. Geen grens, geen
bladeren, geen filters. De architectuur kan filteren aan (het type staat als
eigen kolom), maar een filterbalk boven zes kaarten is gereedschap voor een
probleem dat niet bestaat.

De pagina bestaat alleen als het onderdeel aanstaat **én** er minstens één
project online staat. Een adres dat bestaat maar leeg is, is erger dan een
adres dat niet bestaat.

De opbouw is tweeledig:

1. **Bovenaan de uitgelichte projecten als slideshow** over de volle
   breedte, met pijlen en bolletjes. Elke dia is hetzelfde blok als op de
   voorpagina (`ProjectUitgelicht`), zodat de twee plekken niet uiteen
   kunnen lopen -- alleen zonder de opkomanimatie, want die hoort bij
   scrollen en niet bij schuiven. Is er één uitgelicht project, dan
   verdwijnen de pijlen en de bolletjes vanzelf; is er geen enkel, dan
   begint de pagina met de lijst.
2. **Daaronder de rest**, in de vorm die de eigenaar heeft gekozen.

**De server splitst, het scherm kiest niets.** Een project zit in `featured`
óf in `projects`, nooit in allebei.

#### De vorm is instelbaar

|                     | Wat het is                                                               | Wanneer                                                                  |
| ------------------- | ------------------------------------------------------------------------ | ------------------------------------------------------------------------ |
| `lijst` (standaard) | Elk project een eigen regel onder elkaar, allemaal even groot en compact | Het makkelijkst te scannen, en het blijft kort ook bij twintig projecten |
| `raster`            | Dezelfde projecten als kaarten naast elkaar                              | Luchtiger, en fijner zodra er afbeeldingen bij staan                     |

`ProjectWeergave` in één rij in `project_settings`, met een venster
_Weergave_ op het beheerscherm.

- **Een eigen tabel en niet `site_settings`.** Die is er voor wat over de
  hele site gaat; de mailstijl geldt ook voor een beveiligingsmelding die
  niets met de website te maken heeft. Dit gaat over één onderdeel.
- **`huidige()` geeft een niet-opgeslagen exemplaar** als er nog nooit is
  geseed, zodat het tonen van de publieke site niets wegschrijft en er geen
  controle op `null` door de applicatie loopt. Zelfde patroon als
  `SiteSetting` en `ContactSetting`.
- **De knop verschijnt pas vanaf twee projecten.** Met één is er niets om
  naast of onder elkaar te zetten.
- **Het raakt alleen `/projecten`.** Het blok op de voorpagina blijft wat
  het is -- dat is een voorproefje en geen overzicht. Het venster zegt dat
  erbij, zodat de eigenaar niet gaat zoeken naar een verandering die daar
  niet komt.

#### Wat hier eerst stond, en waarom het eruit is

Twee vormen hebben het niet gehaald, en dat is het vermelden waard omdat ze
allebei redelijk klonken.

**Een licht lijntje tussen de kaarten.** De brede regel kreeg bewust minder
opmaak dan de kaarten, zodat hij zou verschillen van het uitgelichte blok.
Naast twee nette kaarten las dat niet als "groter en belangrijker" maar als
**"hier ontbreekt een kaart"** -- zeker zolang een project geen afbeelding
heeft.

**Afwisselend één brede en twee smalle kaarten.** Een tijdschriftritme, met
het beeld om en om links en rechts. Daar kwam steeds dezelfde vraag op:
waarom is díe ene anders? Het eerlijke antwoord was "omdat hij toevallig op
plek één van de ronde staat", en dat is geen antwoord. Een lijst waarin
alles gelijk is, lees je sneller dan een ritme dat je eerst moet doorzien.

Wat ervan overblijft is de regel dat de pagina **compact** moet blijven: de
lijst heeft geen grens, dus elke regel die hoger is dan nodig maakt de
pagina meteen een stuk langer. Vandaar een beeld van 4rem, de periode naast
het soort in plaats van op een eigen regel, en hoogstens twee regels
samenvatting met `-webkit-line-clamp`. Wie meer wil weten klikt door -- daar
is de projectpagina voor.

#### De slideshow

**Het spoor volgt je vinger.** Niet pas springen bij het loslaten maar
meebewegen terwijl je sleept, met weerstand aan de uiteinden -- een derde
van de afstand -- zodat je voelt dat er niets meer komt. Tijdens het slepen
staat de overgang uit (`data-sleept`), want anders loopt het spoor achter je
vinger aan in plaats van eronder. `touch-action: pan-y` houdt het verticaal
scrollen van de pagina intact.

**Een sleep is geen klik.** De dia's zijn links, dus zonder vanger opent een
veeg het project in plaats van door te schuiven. Na een beweging van meer
dan zes beeldpunten vangt een `click`-luisteraar in de capture-fase de
eerstvolgende klik weg. Dat was een echte fout, geen afwerking.

**Wat wegschuift dimt en krimpt.** Vier procent en wat helderheid, meer
niet. Zonder dat verschil ziet een dia die halverwege het beeld uit loopt
eruit als dezelfde dia die scheef staat; met diepte lees je het als iets dat
vertrekt. Het hoort op te vallen tijdens de beweging en niet als effect.

#### De hoogte: twee omwegen en de echte oorzaak

**Alle dia's zijn even hoog, en er wordt niets aan hoogte geanimeerd.** Dat
klinkt als de simpelste oplossing en dat is het ook -- maar er zijn twee
pogingen aan voorafgegaan, en die zijn het onthouden waard.

**De eerste** maakte de dia's gelijk zonder iets aan het hoogteverschil te
doen. Een dia zonder afbeelding werd uitgerekt tot de hoogte van een dia mét
afbeelding, met een half blok leegte onder de knop.

**De tweede** liet het venster meebewegen naar de hoogte van de dia in
beeld, gemeten met een `ResizeObserver`. Dat zag er op papier goed uit, maar
`height` is een eigenschap die de browser dwingt om elke frame de hele
pagina eronder opnieuw door te rekenen. Het resultaat was hakkelen -- en
daarmee was de kuur erger dan de kwaal.

**De oorzaak zat ergens anders.** Op een telefoon staat het beeld bóven de
tekst en is het dus volledige breedte; met `aspect-ratio: 1` wordt dat op
een scherm van 370 beeldpunten een afbeelding van 370 hoog, tegenover zo'n
300 voor een complete dia zónder beeld. Een verschil van meer dan twee keer,
en geen animatie die dat mooi maakt.

De oplossing is dan ook geen animatie maar een begrenzing: `max-height:
13rem` op het beeld onder de tabletgrens, met `object-fit: cover` zodat het
gevuld blijft. Dat is sowieso beter -- een vierkant van volledige breedte
duwde het hele verhaal onder de vouw. Wat er aan hoogteverschil overblijft
verdelen we met `align-content: center` gelijk over boven en onder, in
plaats van alles onderaan te laten vallen.

Wat dit oplevert: de bediening onder de dia's staat stil, er beweegt geen
enkele eigenschap die de pagina herberekent, en een dia zonder afbeelding
heeft geen gat meer.

**Vastpakken gebeurt pas zodra het écht een sleep is.** Met
`setPointerCapture` meteen bij het neerzetten gaan ook de muisgebeurtenissen
naar het venster, en dan valt `click` op het venster in plaats van op de link
van de dia -- waardoor een gewone klik op _Bekijk project_ niets meer doet.
Door pas te grijpen na een beweging van een paar beeldpunten blijft een klik
een klik, en krijgt een sleep alsnog de muis mee als hij buiten het venster
belandt. Een luisteraar op `window` vangt het geval waarin de sleep onder die
grens blijft en buiten het venster eindigt.

**Het doorschuiven stopt zodra iemand kijkt**: bij de muis erop, bij
toetsenbordfocus in het blok, tijdens het slepen, en zodra het tabblad naar
de achtergrond gaat. Tekst die wegschuift terwijl je hem leest is het ergste
wat een slideshow kan doen.

**Het bolletje van de huidige dia is een baantje met een vulling** die
meeloopt met de wachttijd, zodat het doorschuiven niet uit het niets komt.
Twee dingen zitten daar vast aan elkaar: de wachttijd gaat als CSS-variabele
mee vanuit het component (die twee kunnen dus niet uit elkaar lopen), en de
`:key` van de vulling loopt mee met een teller die bij elke wissel opschuift
-- anders loopt de animatie door waar hij was en staat er een ring van
tachtig procent onder een dia die net is begonnen. Staat de slideshow stil,
dan staat de vulling ook stil.

**Bij `prefers-reduced-motion` schuift en draait er niets**, en dimmen de
dia's ook niet. Dan is het een stapel waar je met de pijlen doorheen klikt.

**Dia's die niet in beeld staan krijgen `inert`**, anders loopt de
tabvolgorde door knoppen die niemand ziet staan.

De overgang zelf is 600 ms op `cubic-bezier(0.16, 1, 0.3, 1)`: vlot
vertrekken, zacht tot stilstand komen. Korter voelt schokkerig op een blok
van deze maat, langer gaat vervelen zodra je twee keer achter elkaar
doorklikt.

### `/projecten/{slug}` -- en waarom een pagina en geen venster

Elders op deze site opent "meer over dit item" een overlay zonder route; zie
[`ErvaringVenster`](../../../resources/js/components/site/ErvaringVenster.vue).
Voor een project is dat niet genoeg, om drie redenen:

1. **Je stuurt het iemand toe.** Een project is precies het soort ding dat
   de eigenaar in een mail of op LinkedIn deelt. Een overlay heeft geen
   adres, dus dat kan niet.
2. **De sitemap.** [`docs/openstaand.md`](../../openstaand.md) zegt dat een
   sitemap pas zinvol is "zodra er pagina's zijn, en die komen uit de
   modules". Dit is de eerste module die er echt meerdere oplevert.
3. **Dit project koos al twee keer voor vindbaarheid**: de vragenlijst houdt
   alle vragen in de DOM, en levert structuurdata mee. Diezelfde afweging
   geldt hier sterker.

De prijs is de eerste slug en de eerste publieke route met een parameter in
dit project. Dat is één kolom en een unieke index.

### De slug

`Str::slug(title_nl)`, **bij het aanmaken gemaakt en daarna nooit meer
bijgewerkt**. Verandert de titel later, dan blijft het adres staan: een link
die iemand heeft gedeeld of die in een zoekresultaat staat hoort te blijven
werken. Dat weegt zwaarder dan een adres dat altijd precies de huidige titel
spiegelt, en het scheelt een omleidingstabel.

Twee randgevallen zijn afgevangen:

- **Botsing**: een oplopend achtervoegsel (`-2`, `-3`, ...) in een lus.
- **Een titel zonder bruikbare letters** -- alleen leestekens of een
  niet-latijns schrift -- valt terug op `project`. Zonder die terugval zou
  het adres leeg zijn en klaagt de unieke index over iets wat de eigenaar
  niet kan zien.

De eigenaar kan het adres niet bewerken. Eén ding minder om te beheren, en
geen vraag over wat er met oude links gebeurt.

### De weg terug

De detailpagina is de enige op de site met **twee** bovenliggende plekken:
de voorpagina en `/projecten`. Eén vaste terugknop zou dus in de helft van
de gevallen naar een pagina wijzen waar de bezoeker nooit is geweest.

De kaarten en het uitgelichte blok op de voorpagina sturen daarom
`?van=start` mee. De controller leest dat, toetst het aan een witte lijst
(`HERKOMST`) en stuurt het als prop `van` door; de knop onderaan wordt
_Terug naar de voorpagina_ of _Terug naar alle projecten_.

**Via het adres en niet via `document.referrer`.** De site wisselt van
pagina zonder de browser te laten navigeren, dus die verwijzer klopt hier
niet. Omdat het in het adres staat werkt het bovendien na verversen en al op
de server -- de knop staat meteen goed in plaats van na een flikkering. Wie
de link deelt deelt meestal het adres zonder `?van=`, en dan is de lijst
precies goed.

## De routes

| Route                             | Naam                      | Wat              |
| --------------------------------- | ------------------------- | ---------------- |
| `GET projecten`                   | `projecten`               | Het overzicht    |
| `GET projecten/{project:slug}`    | `project`                 | Eén project      |
| `GET website/projecten`           | `website.projecten.index` | Het beheerscherm |
| `POST website/projecten`          | `.store`                  |                  |
| `PUT website/projecten/kop`       | `.kop`                    |                  |
| `PUT website/projecten/volgorde`  | `.volgorde`               |                  |
| `PUT website/projecten/weergave`  | `.weergave`               |                  |
| `PUT website/projecten/{project}` | `.update`                 |                  |
| `PATCH .../{project}/online`      | `.online`                 |                  |
| `PATCH .../{project}/uitlichten`  | `.uitlichten`             |                  |
| `DELETE .../{project}`            | `.destroy`                |                  |

Het overzicht staat vóór het losse project: allebei beginnen ze met
`projecten`, en de eerste die past wint -- andersom zou `/projecten` als een
slug worden gelezen. In de beheergroep staan `kop`, `volgorde`,
`weergave` en `uitlichten` vóór `{project}`, om dezelfde reden.

Allebei de publieke routes hebben `TelBezoek`: het zijn echte pagina's van
de website.

## Autorisatie en beveiliging

`can:manage portal` staat op de hele groep in
[`routes/website.php`](../../../routes/website.php); `ProjectRequest::authorize()`
geeft simpelweg `true`. Geen tweede recht, geen `2fa.confirm` -- het gaat om
inhoud, met een dubbele bevestiging in het scherm en een regel in het
activiteitenlogboek.

**Een bezoeker kan een verborgen project niet via het adres bekijken.** De
detailroute heeft twee grendels: het project moet online staan, én het hele
onderdeel moet aanstaan op de indelingspagina. Zonder die tweede blijft elk
project bereikbaar voor wie het adres kent, ook nadat de eigenaar de hele
module van zijn site heeft gehaald.

## De plek in de rij

`PageSectionKey::Projecten` staat tussen `Werkwijze` en `Ervaring`, op
`standaardPositie() => 4`. De etalage komt vlak na de werkwijze: eerst wát
hij doet en hóe, dan het bewijs dat hij het heeft gedaan. Vóór de tijdlijn,
want een bezoeker die een project herkent is daarna pas nieuwsgierig naar
waar hij in dienst was.

> **Dat getal staat niet los.** `PageSectionSeeder::plekVoor()` geeft
> `standaardPositie()` alleen terug zolang de tabel leeg is; vanaf de tweede
> `case` wordt het `max(position) + 1` in de volgorde van de `case`-regels.
> De volgorde van de cases, de getallen en de lijst in
> `PageSectionSeederTest::$eigen` moeten dus met elkaar kloppen -- anders
> valt die test om voor élk onderdeel, niet alleen voor dit. Ervaring tot en
> met LinkedIn zijn daarom mee opgeschoven naar 5..10.

De teller in `AppServiceProvider::configurePageSections()` telt
`online()->count()`. Zonder die teller geldt het onderdeel altijd als
gevuld, en dan vallen de exacte lijsten in `LandingSectionsTest` om.

De kop staat in `section_headings`, dus Projecten staat in
`SectionHeadingSeeder::MET_KOP` **én** in de privékopie
`SectionHeadingTest::MET_KOP`. Die twee horen bij elkaar.

## Het beheerscherm

Het vaste patroon: knoppenrij, kaarttabel, bewerkvenster, sleepvenster.

**Het sterretje staat per regel en niet als keuzelijst bovenaan.** Je kiest
het project op de plek waar je het ziet staan, en je ziet in één oogopslag
welke het zijn.

**De bevestiging zegt wat er met de website gebeurt, niet wat er met het
vinkje gebeurt** -- en dat verschilt per geval. Het laatste sterretje
uitzetten haalt het hele blok van de voorpagina; één van de drie uitzetten
haalt alleen een dia weg. Eén vaste zin zou in de helft van de gevallen
onwaar zijn.

**Het bewerkvenster gaat in twee stappen met één opslag aan het eind**,
zoals bij Ervaring: stap 1 het Nederlands plus alles wat geen taal heeft
(soort, organisatie, periode, beeld), stap 2 het Engels met de vertaalknop.
Zou stap 1 al wegschrijven, dan staat er een half project live zodra iemand
het venster wegklikt. Bij een validatiefout springt het venster naar de stap
waar de fout staat.

## Tests

| Bestand                 | Wat het bewaakt                                                                                                                                                                                                                                           |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `ProjectCrudTest`       | Rechten per route, aanmaken, wijzigen, verwijderen, de slug (ook bij een titel zonder letters en bij een botsing), het eigen type en het wissen daarvan, de periode met "loopt nog" en de omgekeerde volgorde, de lengtegrenzen, beide talen, het logboek |
| `ProjectPublishingTest` | Het blok verdwijnt én komt terug, hoogstens drie naast het uitgelichte, de volgorde, online/offline, de terugval per veld, de teller op het indelingsscherm                                                                                               |
| `ProjectUitgelichtTest` | Meerdere uitlichten, de voorpagina kiest de bovenste, het overzicht splitst slideshow en lijst, uitzetten, de volgorde blijft, een offline uitgelicht project blijft van de site, een project zonder sterretje valt nooit in, verwijderen                 |
| `ProjectImageTest`      | De zes vaste uploadgevallen plus de ondergrens van 240, en dat een project zonder beeld blijft werken                                                                                                                                                     |
| `ProjectWeergaveTest`   | De weergave van de projectenpagina: de terugval op een database zonder rij, omzetten en terugzetten, dat de publieke pagina volgt, een verzonnen waarde, de rechten, en het activiteitenlogboek                                                           |
| `ProjectDetailTest`     | Het overzicht, de detailpagina, de twee grendels (offline project en uitgezet onderdeel), een onbekend adres, de weg terug (`?van=start`, geen waarde, een verzonnen waarde) en dat allebei de pagina's hun bezoek tellen                                 |

Bijgewerkt: `PageSectionSeederTest`, `SectionHeadingTest`,
`SiteNavigatieTest` en `VoorbeeldDataTest`. `AppSidebarTest` en
`DocumentationTest` passen zichzelf aan zolang de zijbalkregel en de
handleidingkaart er staan.

## Wat er bewust niet in zit

- **Geen filters op het overzicht.** Pas bouwen als er genoeg projecten zijn
  om ze nodig te hebben.
- **Geen expertise- of technologielijst.** Zie het datamodel; dat is een
  eigen tabel waard of niets.
- **Geen structuurdata voor Google.** De vragenlijst heeft dat wel
  (`FAQPage`), en een project zou `CreativeWork` kunnen krijgen. Dat is een
  tweede `withViewData`-sleutel plus een `@isset` in `app.blade.php`, en het
  is pas zinvol zodra de sitemap er is -- zie `docs/openstaand.md`.
- **Geen nakijkpagina in het portaal**, zoals `ErvaringDetail`. Het
  bewerkvenster toont beide talen naast elkaar in twee stappen; een derde
  scherm zou dat herhalen.
