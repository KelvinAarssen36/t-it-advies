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
kaart vult die plek met de eerste letter van de organisatie, in Geist Mono
-- klein in het tegeltje, groot in het lichtvlak van de grote kaart. Op het
uitgelichte blok krijgt de letter een smallere kolom (`.brand-site-casus-letter`,
3/9 in plaats van 4/8) en gaat hij open zoals een beeld dat zou doen.
**Dat was eerst anders, en dat is teruggedraaid.** In ronde één verviel de
beeldkolom dan helemaal en liep de tekst door; met de opbouw tijdens het
scrollen (zie hieronder) stond er dan een casus zonder beweging en zonder
beeld, en dat las als een vergeten afbeelding in plaats van een keuze.

**Het vierkant bepaalt de kaders.** Op de site staat het beeld nooit in een
brede banner: de casus toont het vierkant op een breed scherm en liggend
(16:10) op een telefoon, de grote kaart in 2:1. Elk van die verhoudingen
knipt maar een strook van het vierkant af; een banner van 21:9 zou een
opgerekt plaatje van een paar honderd pixels hoog worden. De casus stond
eerst staand (4:5); dat maakte het blok op een laptop bijna een heel scherm
hoog, en dat was een van de redenen dat de sectie in ronde twee van 2153
naar ongeveer 1480 pixels is teruggebracht.

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

**De casus.** Eén uitgelicht project is een filmisch blok op een glazen
plaat (`ProjectUitgelicht`): het beeld links, de tekst rechts, met de
organisatie en de rol op één regel en de periode en duur eronder in Geist
Mono. De titel staat op `--site-maat-titel`; een titel van meer dan zeventig
tekens krijgt `--site-maat-titel-lang` (`data-lengte="lang"`), want de
eigenaar typt hem zelf.

**De casus bouwt zich op terwijl je scrolt** -- het scrollmoment van deze
module. Het beeldkader begint als een kleiner, ronder venster midden in zijn
vlak (`clip-path: inset(10% 16% round 2.5rem)`) en gaat open tot het volle
kader, gekoppeld aan de scrollpositie (`scrub`, van `top 92%` tot `top 32%`
van de plaat). Het beeld erin zakt ondertussen van 1,2 naar 1,06, en op een
breed scherm schuift het binnen zijn kader een paar procent mee tot het
kader het scherm uit is. Scrol je terug, dan sluit het venster weer: het is
een positie en geen waarde (zie de les over `scrub` in
[frontend en animatie](../frontend-en-animatie.md)). Op een telefoon een
kleinere opening en geen meeschuivend beeld; onder reduced motion staat het
beeld gewoon open. De tekst komt op met de gewone reveals.

**Beeld en tekst komen uit twee richtingen samen.** Op een breed scherm
schuift het kader op dezelfde scroll van links naar zijn plek en het
tekstblok van rechts (12% van de breedte van de casus, hoogstens 170
pixels; de tekst iets minder). Ze staan stil zodra het beeld open is, dus
de titel en de knop bewegen niet meer als je gaat lezen of klikken.
Omhoogscrollen haalt ze weer uiteen. De beweging zit op het kader en het
tekstblok zelf: het beeld erin heeft al zijn eigen schaal en diepte, en de
regels van de kop hun eigen opkomst. In de slideshow doet `ProjectSlideshow`
hetzelfde op de kaders en tekstblokken van alle dia's.

**In de slideshow gaan alleen de beelden open, niet het venster.** De eerste
opzet opende het hele venster, en dan knipte de opening halverwege ook de
titel af: je las "migratie v" en dacht dat er iets stuk was.
`ProjectSlideshow` krijgt daarom op de voorpagina de prop `opbouw`, en zet
dezelfde opening op het beeldkader van elke dia. Dat botst niet met de veeg
tussen de dia's: die werkt met `clip-path` op het blad en een `transform` op
het beeld, de opening met `clip-path` op het kader daartussen. Op
`/projecten` staat `opbouw` uit, want daar ben je er al.

**De kaarten voegen zich naar het aantal.** `ProjectenSection` zet
`data-aantal` op het raster en de CSS kiest de compositie:

| Kaarten | Op een breed scherm                                     |
| ------- | ------------------------------------------------------- |
| 1       | Eén brede rij                                           |
| 2       | Twee naast elkaar                                       |
| 3       | Eén grote kaart (`groot`) links, twee gestapeld ernaast |

Op een telefoon staan ze altijd onder elkaar, met het beeld bovenaan.
Tussen 50 en 64rem komt de grote kaart over de volle breedte met de twee
andere eronder naast elkaar. Naast twee gestapelde kaarten vult het
letterveld van de grote kaart precies hun hoogte (`flex: 1`), in plaats van
met een vaste verhouding de rij hoger te maken: dat leverde in ronde één een
letter van bijna een half scherm op.

**De kaarten schuiven van rechts naar hun plek, met de scroll mee.** Op een
breed scherm komt de rij als één beweging binnen: elke kaart begint verder
naar rechts dan de vorige (afstand × 1, × 1,45, × 1,9) en ze komen samen tot
stilstand als de rij ruim een derde van het scherm in is (`top 96%` tot
`top 62%`, `scrub`). Omhoogscrollen schuift ze weer uit. Het is de
tegenbeweging van de casus erboven, die van links komt. Daarna is een kaart
een gewone link die niet meer onder je cursor wegschuift.

Geen `stagger` in die scrub: met afstanden die bij een verversing opnieuw
worden berekend, tekende GSAP de beginstand van de tweede en derde kaart
pas als hun beurt kwam, en die stonden dan eerst op hun plek en schoten
daarna weg. De waaier zit daarom in de afstand.

Op een telefoon komen ze één voor één op (`stapelBinnen`), onder reduced
motion staan ze er meteen. `ProjectKaart` draagt op de voorpagina
`binnenkomst="groep"` en geen `data-reveal`; op `/projecten` doet hij het
met de gewone reveal. De rij knipt horizontaal af (`overflow-x: clip` op
`.brand-site-projecten`), zodat de pagina tijdens het schuiven niet breder
wordt dan het scherm.

**Het beeld op een gewone kaart is klein, tot je ernaar wijst.** Een gewone
kaart toont het beeld als tegeltje naast het soort; met een muis zweeft er
bij het aanwijzen een grotere versie mee met de cursor (`gsap.quickTo` op
`x` en `y`, dus alleen een transform). Zo blijft een rij kaarten rustig en
zie je het beeld precies op het moment dat je ernaar kijkt. Alleen met een
muis, vanaf 50rem en niet onder reduced motion; op een telefoon staat het
beeld gewoon bovenaan. De grote kaart toont zijn beeld altijd en heeft
daarom geen zwevende versie. Een spotlicht op de rand (`spotlicht()`) volgt
de cursor.

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
   breedte, met pijlen en een lijn per dia. Elke dia is hetzelfde blok als
   op de voorpagina (`ProjectUitgelicht`), zodat de twee plekken niet
   uiteen kunnen lopen -- alleen zonder de opkomanimatie, want die hoort
   bij scrollen en niet bij wisselen. Is er één uitgelicht project, dan
   verdwijnen de pijlen en de lijnen vanzelf; is er geen enkel, dan begint
   de pagina met de lijst.
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
pagina meteen een stuk langer. Vandaar een beeld van 3,5rem (2,75rem op een
telefoon, bovenaan de regel in plaats van in het midden), de periode naast
het soort in plaats van op een eigen regel, en hoogstens twee regels
samenvatting met `-webkit-line-clamp`. Wie meer wil weten klikt door -- daar
is de projectpagina voor.

#### De slideshow

**De dia's liggen op elkaar en de nieuwe veegt open.** Geen spoor meer dat
opzij schuift, maar een diafragma: de nieuwe dia wordt met een `clip-path`
van de ene rand naar de andere onthuld, over de vorige heen. Het beeld erin
zakt van iets te groot (`scale(1.12)`) naar zijn eigen maat, de regels van
de tekst schuiven het laatste stukje mee (48 pixels, met een kleine
verspringing), en de vorige dia wijkt 64 pixels naar de andere kant uit en
dimt tot 45 procent. Vooruit veegt hij van rechts, terug van links, zodat de
richting klopt met de pijl waar je op drukte. Dat is dezelfde taal als de
rest van de site: dingen komen in beeld, ze schuiven niet langs.

Drie toestanden per dia, op attributen:

| Attribuut    | Wat het is                                   | Zichtbaar |
| ------------ | -------------------------------------------- | --------- |
| `data-aan`   | De huidige, bovenop (`z-index: 2`)           | ja        |
| `data-vorig` | De vorige, ligt eronder tot de veeg klaar is | ja        |
| (geen)       | Niet aan de beurt                            | nee       |

**De veeg is een GSAP-tijdlijn, geen CSS-overgang -- en dat was een echte
fout.** Eerst stond de veeg als `transition` op `clip-path`: een dia die niet
aan de beurt was stond weggeknipt, werd hij de huidige, dan liep de knip naar
nul. Maar de dia die net weg was bleef open onder de nieuwe liggen
(`data-vorig`, `inset(0)`), en met twee uitgelichte projecten is dat precies
de dia die je bij de volgende klik weer nodig hebt. Die ging van "open" naar
"open": geen verschil, dus geen overgang, en hij stond er ineens. **Alleen de
eerste wissel veegde.** Bij drie of meer gebeurde hetzelfde zodra je
terugklikte. Gemeten met Playwright door per beeldje de berekende
`clip-path` van de nieuwe dia te bemonsteren: de eerste wissel doorliep 58
tussenstanden, elke volgende één. Nu zet `speelVeeg()` elke keer zijn eigen
beginstand (`fromTo`), dus wat er daarvoor met die dia gebeurde, telt niet
meer; na afloop ruimt hij zijn inline-waarden op en vervalt `data-vorig`.
Na de reparatie doorloopt elke wissel 53 tot 55 tussenstanden, vooruit,
terug, met de lijnen, de pijltjestoetsen, slepen, vegen op een telefoon en
het doorschuiven vanzelf.

**Een klik tijdens een veeg gaat niet verloren.** `naar()` laat de lopende
veeg eerst naar zijn eind springen (`progress(1)`) en begint dan de nieuwe.
Vijf klikken binnen een tiende seconde zijn vijf wissels; wat je ziet is
de laatste veeg, op de juiste dia, met de lijn en de teller ernaast gelijk.
Geen wachtrij en geen vergrendeling: allebei voelen als een knop die niet
reageert.

**Stand eerst, dan de veeg.** `naar()` zet welke dia aan is en welke net
weg, wacht een `nextTick` zodat Vue de attributen heeft geschreven, en
begint dan de tijdlijn -- nog vóór de browser tekent, dus de nieuwe dia
staat nooit een beeldje open voordat hij openveegt. Is er in die tussentijd
alweer verder geklikt, dan slaat hij de veeg over; de nieuwere is aan zet.

**De veeg raakt de dia, het beeld en de tekstregels, niet het kader en het
tekstblok.** Die twee stuurt de opbouw bij het scrollen op de voorpagina
(`bouwOp()`: `clip-path` en `x`). Twee animaties op dezelfde eigenschap van
hetzelfde element vechten om elk beeldje.

**De dia's hebben een volle achtergrond** (`--site-plaat-vol`), en de casus
erin laat zijn eigen glazen plaat weg (`data-in-dia`). Anders schijnt de
vorige dia door de halfdoorzichtige vulling van de nieuwe heen.

**De dia volgt nog steeds je vinger.** Tijdens het slepen schuift de dia in
beeld mee en dimt hij een beetje. Tijdens het slepen staat de overgang uit
(`data-sleept`), anders loopt de dia achter je vinger aan in plaats van
eronder. Bij het loslaten boven de veegdrempel (56 pixels) veegt de
volgende open, en de weggesleepte dia gaat verder vanaf waar je hem losliet
in plaats van eerst terug te springen. **Een veeg loopt rond, net als de
pijlen.** Eerst was hij aan de uiteinden stroever (een derde van de afstand)
en liep hij niet rond; met twee projecten haalde een veeg op de laatste dia
de drempel dan nooit, en deed hij de helft van de keren niets terwijl de pijl
ernaast gewoon doorging. `touch-action: pan-y` houdt het
verticaal scrollen van de pagina intact.

**Een sleep is geen klik.** De dia's zijn links, dus zonder vanger opent een
veeg het project in plaats van door te bladeren. Na een beweging van meer
dan zes beeldpunten vangt een `click`-luisteraar in de capture-fase de
eerstvolgende klik weg. Dat was een echte fout, geen afwerking.

#### De hoogte

**Alle dia's zijn even hoog, en er wordt niets aan hoogte geanimeerd.** Ze
liggen in één rastercel op elkaar (`grid-area: 1 / 1`), dus het venster is
vanzelf zo hoog als de hoogste dia, en een kortere dia zet zijn inhoud met
`align-content: center` in het midden.

Daar zijn twee pogingen aan voorafgegaan, en die zijn het onthouden waard.
**De eerste** maakte de dia's gelijk zonder iets aan het hoogteverschil te
doen: een dia zonder afbeelding werd uitgerekt tot de hoogte van een dia mét
afbeelding, met een half blok leegte onder de knop. **De tweede** liet het
venster meebewegen naar de hoogte van de dia in beeld, gemeten met een
`ResizeObserver`. Maar `height` dwingt de browser om elke frame de hele
pagina eronder opnieuw door te rekenen, en het resultaat was hakkelen.

**De oorzaak zat in het beeld op een telefoon.** Een vierkant over de volle
breedte werd op een scherm van 370 beeldpunten 370 hoog, tegenover zo'n 300
voor een complete dia zónder beeld. Daarom staat het beeld op een telefoon
liggend (4:3): het verschil is klein genoeg om met centreren op te vangen,
en het verhaal staat niet meer onder de vouw.

**Vastpakken gebeurt pas zodra het écht een sleep is.** Met
`setPointerCapture` meteen bij het neerzetten gaan ook de muisgebeurtenissen
naar het venster, en dan valt `click` op het venster in plaats van op de link
van de dia -- waardoor een gewone klik op _Bekijk project_ niets meer doet.
Door pas te grijpen na een beweging van een paar beeldpunten blijft een klik
een klik, en krijgt een sleep alsnog de muis mee als hij buiten het venster
belandt. Een luisteraar op `window` vangt het geval waarin de sleep onder die
grens blijft en buiten het venster eindigt.

**Het doorschuiven stopt zodra iemand kijkt**: bij de muis erop, bij
toetsenbordfocus in het blok, tijdens het slepen, zodra het tabblad naar
de achtergrond gaat, en zodra het blok uit beeld is gescrold. Tekst die
wegschuift terwijl je hem leest is het ergste wat een slideshow kan doen.
**Elke reden telt apart** (`muis`, `focus`, `slepen`, `tabblad` in een
set). Met één aan/uit zette de muis die het blok verliet het doorschuiven
weer aan terwijl de toetsenbordfocus er nog in stond; en focus die van de
ene pijl naar de andere gaat, blijft binnen het blok.

**Voor een schermlezer** is elke dia een `group` met de rolbeschrijving
"Dia" en het label "1 van 2"; het venster meldt een wissel (`aria-live`
`polite`) alleen als de slideshow stilstaat. Draait hij vanzelf, dan staat
dat op `off`: elke zeven seconden een aankondiging is lawaai. Naast de
pijlen staat de stand ook in cijfers ("1 / 2"), voor wie de lijnen niet
telt; die is `aria-hidden`, want de dia zegt het al.

**De bediening is een lijn per dia**, tussen de twee pijlen. Het aanraakvlak
is de volle hoogte van de knoppen (44 pixels); de lijn zelf is twee pixels.
De lijn van de huidige dia loopt vol met de wachttijd, zodat het
doorschuiven niet uit het niets komt. Twee dingen zitten daar vast aan
elkaar: de wachttijd gaat als CSS-variabele mee vanuit het component (die
twee kunnen dus niet uit elkaar lopen), en de `:key` van de vulling loopt
mee met een teller die bij elke wissel opschuift -- anders loopt de animatie
door waar hij was en staat er een lijn van tachtig procent onder een dia die
net is begonnen. Staat de slideshow stil, dan staat de vulling ook stil.

**Bij `prefers-reduced-motion` veegt en draait er niets.** Geen
doorschuiven, geen vulling, geen zakkend beeld: dan is het een stapel waar
je met de pijlen doorheen klikt.

**Dia's die niet in beeld staan krijgen `inert`** en `aria-hidden`, anders
loopt de tabvolgorde door knoppen die niemand ziet staan.

De veeg zelf is 0,9 s op `power3.inOut`: langzaam vertrekken, vlot door het
midden, zacht tot stilstand. Een diafragma dat lineair opengaat oogt als een
schuifdeur.

**De bediening reageert als de rest van de site** (zie "Eén taal voor alles
wat je kunt aanraken" in `basis.css`): een pijlknop licht op bij hover en
zijn pijl wijst een stap verder in zijn eigen richting (`data-kant`), hij
geeft mee bij indrukken (`scale(0.92)`, `--site-duur-druk`), en een lijn
wordt dikker en lichter onder de muis. Hover alleen met een muis.

#### Kaarten en regels: hover, focus, indrukken

Een projectkaart en een regel in de lijst zijn allebei één link. Ze
reageren op dezelfde manier, en de toetsenbordfocus krijgt hetzelfde als de
muis:

- **Kaart**: de rand wordt cyaan, de kaart komt drie pixels naar je toe
  (`translate`, niet `transform` -- die is op de voorpagina van de
  scroll-beweging), het beeld zakt iets dichterbij, het tegeltje licht op en
  _Bekijk project_ krijgt zijn streep en schuift zijn pijl. Indrukken zet
  hem terug op zijn plek. Focus gaat via `:has(:focus-visible)`.
- **Regel in de lijst**: achter de regel licht een vlak op (een
  pseudo-element, zodat de regel zelf geen maat verandert), de titel wordt
  helderder, het tegeltje licht op en de ronde pijl rechts vult zich cyaan
  en schuift een stap. De regel steekt aan beide kanten een stukje buiten de
  lijst met evenveel binnenruimte terug: de inhoud blijft op de lijn van de
  kop, en de focusrand valt precies om het vlak.
- **Een badge is geen knop**: geen hover, geen cursor.
- Alle hover-regels staan onder `@media (hover: hover) and (pointer: fine)`,
  zodat er op een telefoon na een tik niets blijft hangen. Onder reduced
  motion verandert alleen de kleur.

### `/projecten/{slug}` -- en waarom een pagina en geen venster

Elders op deze site opent "meer over dit item" een overlay zonder route; zie
[`ErvaringVenster`](../../../resources/js/components/site/ErvaringVenster.vue).
Voor een project is dat niet genoeg, om drie redenen:

1. **Je stuurt het iemand toe.** Een project is precies het soort ding dat
   de eigenaar in een mail of op LinkedIn deelt. Een overlay heeft geen
   adres, dus dat kan niet.
2. **De sitemap.** Een sitemap is pas zinvol zodra er pagina's zijn, en
   die komen uit de modules. Dit was de eerste module die er echt meerdere
   opleverde; elke projectpagina staat nu in de
   [sitemap](../sitemap.md).
3. **Dit project koos al twee keer voor vindbaarheid**: de vragenlijst houdt
   alle vragen in de DOM, en levert structuurdata mee. Diezelfde afweging
   geldt hier sterker.

De prijs is de eerste slug en de eerste publieke route met een parameter in
dit project. Dat is één kolom en een unieke index.

#### De opbouw van de pagina

**Eén kolom loopt door de hele pagina** (`--project-kolom`,
`clamp(11rem, 18vw, 14.5rem)`): het beeld staat erin, en verderop de
kopjes van de lange teksten. Zo heeft de pagina één linkerlijn voor beeld en
kopjes en één voor de tekst, en leest hij als een verslag in plaats van als
een stapel blokken. Op een telefoon staat alles onder elkaar en is het beeld
liggend (16:9).

1. **De kop**: het beeld, of zonder beeld de letter van de organisatie in
   een vlak met licht -- net als de casus op de voorpagina, zodat een project
   zonder beeld niet leest als een vergeten beeld. Daarnaast het soort werk,
   de titel en de samenvatting als inleiding.
2. **De feiten** in een strook tussen haarlijnen: Organisatie, Rol,
   Periode, Duur, elk met het label klein erboven. Eerst stonden die als
   twee regels met schuine strepen onder de titel; dan moest je raden dat
   "9 maanden" de duur was. Een leeg feit valt weg. Alle vier in dezelfde
   letter: in de mono werden periode en duur zwaarder dan de naam ernaast.
3. **De lange teksten** (_Wat ik heb gedaan_, _Wat het opleverde_), het
   kopje links in de kolom van het beeld, de tekst rechts op de lijn van de
   titel, gescheiden door een haarlijn. Geen glazen plaat meer: twee
   kaders om twee alinea's maakten van een verslag een formulier.

De feiten en de teksten komen binnen met de gewone reveal (`data-reveal`);
de kop staat er meteen. Een subpagina krijgt geen entree zoals de hero --
wie doorklikt, wil lezen.

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

### Van kaart naar projectpagina: het beeld vliegt mee

Klik je op een project -- een kaart of de casus op de voorpagina, of een
regel op `/projecten` -- dan vliegt het beeld (of de letter) van die kaart
naar de plek van het grote beeld op de projectpagina. Terug gebeurt
hetzelfde omgekeerd: met de terugknop van de browser of een link terug op
de projectpagina vliegt het grote beeld naar zijn kaart, als die na het
terugscrollen in beeld staat. Zie
[`projectOvergang.ts`](../../../resources/js/lib/projectOvergang.ts).

**Hoe het werkt.** Bij de klik komt er een kopie van het beeld op precies
dezelfde plek, vast op het scherm, een fractie opgetild. De oude pagina
vervaagt eronder, de nieuwe komt op, en zodra die staat
(`brand:pagina-gewisseld`) vliegt de kopie in 0,55 s (0,45 op een telefoon)
naar het doel en lost op in het echte beeld. Breedte en hoogte en geen
schaal: de kaart is 2:1, het tegeltje vierkant en de projectpagina 16:9 of
vierkant, en met `object-fit: cover` snijdt het beeld onderweg mee zonder
te vervormen. Het doel vindt hij via `data-project-beeld="<slug>"`. De
links halen de projectpagina al op bij aanwijzen of indrukken
(`prefetch: hover, click`), zodat de kopie niet op het netwerk wacht:
gemeten van klik tot landing 0,85 s op desktop en 0,7 s op een telefoon,
waarvan de vlucht zelf een halve seconde.

**Geen View Transitions, en dat is uitgezocht.** Inertia 3.7.1 kent
`viewTransition` (het roept `document.startViewTransition` aan bij een
bezoek), en de browsers die we bedienen kennen het. Maar tijdens een view
transition bevriest de browser het renderen, ook `requestAnimationFrame` en
CSS-overgangen -- en precies daarop wachten de `out-in`-overgang van
`<main>` en het terugzetten van de scrollpositie. Heen liep dat met een
omweg nog, terug niet: Inertia gebruikt bij de terugknop geen view
transition, en een eigen view transition legt het oude beeld pas een
beeldje later vast, als Inertia de pagina al heeft gewisseld. Een kopie op
het scherm werkt in elke browser, raakt de paginaovergang niet en valt bij
elke twijfel terug op niets bijzonders.

**De titel beweegt niet mee.** Een kaarttitel naar een paginakop vergroten
kan alleen met een schaal, en dan staan de letters halverwege wazig. Het
beeld draagt de beweging; de titel komt op met de pagina.

Wanneer het **niet** speelt, en wat er dan gebeurt:

| Situatie                                                                                 | Gedrag                                              |
| ---------------------------------------------------------------------------------------- | --------------------------------------------------- |
| Reduced motion                                                                           | Geen kopie; de gewone paginawissel                  |
| Ctrl-, Cmd-, Shift- of middelklik                                                        | Nieuw tabblad, zoals elke link; hier niets          |
| Direct een projectpagina geopend                                                         | Niets om vandaan te vliegen                         |
| Doel er niet of buiten beeld (bijv. de dia is niet aan de beurt, of Projecten staat uit) | De kopie lost op waar hij is                        |
| Snel achter elkaar klikken                                                               | De vorige kopie is meteen weg; de laatste klik wint |
| De nieuwe pagina komt niet binnen 4 s                                                    | De kopie lost op                                    |

Tijdens de vlucht schuift de nieuwe pagina niet omhoog bij het opkomen
(`.brand-site[data-overgang]`), anders landt de kopie op een plek die zelf
nog beweegt.

**De voettekst flitste bij terugkomen.** Inertia zet de bewaarde
scrollpositie al terug terwijl de projectpagina er nog staat; die is
korter, dus de browser knipte af op de onderkant en de voettekst schoot een
tel in beeld. Dat gebeurde al bij elke terugweg, maar viel met de kopie
erboven extra op. Nu houdt PublicLayout de hoogte van de site vast vanaf de
`popstate` tot de nieuwe pagina staat (`houdHoogteVast`/`laatHoogteLos`).

### Een beeld dat niet laadt

Laadt een projectbeeld niet, dan blijft het kader staan, in dezelfde maat,
en komt de letter van de organisatie erin -- dezelfde terugval als bij een
project zonder beeld. Een `<img>` meldt zich met `@error` in
[`beelden.ts`](../../../resources/js/lib/beelden.ts); die lijst geldt voor
de hele site, dus ook het tegeltje, het zwevende beeld en de projectpagina
weten het meteen. Het kader houdt zijn maat omdat de verhouding op het beeld
zelf staat; het kapotte beeld wordt onzichtbaar en de letter ligt erover
(`.brand-site-beeld-terugval`).

Aanleiding: op 14 oktober gaf het beeld van "Migratie naar Exchange Online"
een 403 en stond de alt-tekst in een leeg kader. Het bestand was van de
schijf verdwenen: een testproject verwijderen in een losse testdatabase
haalde via `Project::deleting` het gedeelde bestand weg, omdat die
testomgeving dezelfde opslag gebruikte. Het is teruggezet uit de back-up
van 8 oktober (de beelden staan daarin op hun sha256), en een testomgeving
krijgt nu een eigen opslag mee (`LARAVEL_STORAGE_PATH`; zie
[valkuilen](../../development/valkuilen.md)).

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
`standaardPositie() => 5`. De etalage komt vlak na de werkwijze: eerst wát
hij doet en hóe, dan het bewijs dat hij het heeft gedaan. Vóór de tijdlijn,
want een bezoeker die een project herkent is daarna pas nieuwsgierig naar
waar hij in dienst was.

> **Dat getal staat niet los.** `PageSectionSeeder::plekVoor()` geeft
> `standaardPositie()` alleen terug zolang de tabel leeg is; vanaf de tweede
> `case` wordt het `max(position) + 1` in de volgorde van de `case`-regels.
> De volgorde van de cases, de getallen en de lijst in
> `PageSectionSeederTest::$eigen` moeten dus met elkaar kloppen -- anders
> valt die test om voor élk onderdeel, niet alleen voor dit. Ervaring tot en
> met LinkedIn staan daarom op 6..11.

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
  tweede `withViewData`-sleutel plus een `@isset` in `app.blade.php`. De
  sitemap staat er inmiddels wel (zie [sitemap](../sitemap.md)); dit is
  dus een kleine toevoeging zodra iemand erom vraagt.
- **Geen nakijkpagina in het portaal**, zoals `ErvaringDetail`. Het
  bewerkvenster toont beide talen naast elkaar in twee stappen; een derde
  scherm zou dat herhalen.
