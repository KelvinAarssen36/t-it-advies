# De statistieken

Waar de eigenaar goed in is, in cijfers: vaardigheden met een niveau en
kengetallen waar een bezoeker op afgaat. Per cijfer kiest hij zelf de
vorm -- een balk, een ring of een groot getal dat oploopt.

> **Dit is de module met het meeste werk aan de kant van de bezoeker.**
> Qua opzet is hij de eenvoudigste van de vijf: één tabel, één lijst,
> geen bijlagen. Wat hem bijzonder maakt is de animatie; zie
> [De animatie](#de-animatie-een-golf-die-één-keer-door-het-blok-rolt).

| Onderdeel           | Waar de klant het beheert              | Waar het vandaan komt                     |
| ------------------- | -------------------------------------- | ----------------------------------------- |
| De **statistieken** | Website → Statistieken                 | `statistics`                              |
| De **groepen**      | Knop "Indeling": een vak per groep     | `statistics.group_nl`                     |
| De **kop** erboven  | Knop "Kop erboven" op datzelfde scherm | `section_headings`, de rij `statistieken` |
| De **volgorde**     | Knop "Indeling", slepen in een venster | `statistics.position`                     |

Dit is de vijfde module. Van [Diensten](diensten.md) komt de lijst met
een eigen volgorde; van [Certificaten](certificaten.md) de opzet met
groepen en bundels. De kop draait op de gedeelde tabel; zie
[kopteksten](../kopteksten.md).

## Wat er in een statistiek staat

| Veld                    | Verplicht     | Lengte | Waarvoor                                           |
| ----------------------- | ------------- | ------ | -------------------------------------------------- |
| `display`               | ja            | enum   | Balk, ring of teller                               |
| `label_nl` / `label_en` | nl ja, en nee | 60     | "Microsoft 365", "Tickets opgelost"                |
| `value`                 | ja            | int    | 0–100 bij balk en ring, 0–9.999.999 bij een teller |
| `prefix`                | nee           | 8      | "€", voor een teller                               |
| `suffix`                | nee           | 8      | "+", "%", "/7"                                     |
| `note_nl` / `note_en`   | nee           | 120    | Een regeltje onder het label                       |
| `group_nl` / `group_en` | nee           | 60     | De kop waaronder hij valt                          |
| `published`             | —             | bool   | Online of offline                                  |
| `position`              | —             | int    | De volgorde                                        |

### De grens hangt van de weergave af

Dit is de enige validatieregel in dit project die van een **andere
waarde** afhangt. Een balk of ring tekent een deel van een geheel en komt
nooit boven de honderd; een teller heeft geen geheel.

Die grens staat op de enum -- `StatisticDisplay::maximum()` -- en niet in
de migratie, want de kolom moet allebei aankunnen.
`StatisticRequest::rules()` dwingt hem af, en de enum levert hem ook aan
het beheerscherm zodat het invoerveld zijn eigen `max` kent.

**De terugval is de strengste van de twee.** Komt er onzin binnen als
weargave, dan geldt honderd. Dat de weergave zelf dan óók wordt
geweigerd maakt het niet overbodig: zou de terugval de ruime grens zijn,
dan hangt het van de volgorde van de regels af of een balk van 340 er
doorheen glipt. `StatisticDisplayTest` legt dat vast.

### Alleen hele getallen

De duizendtalscheiding komt er in de browser bij -- `1.250` in het
Nederlands, `1,250` in het Engels. Decimalen niet: wie "99,9% uptime"
wil, zet 99 neer met achtervoegsel `,9%`. Komt dat vaak voor, dan is een
decimaal later één kolomwijziging.

### De terugval tussen de talen

| Veld    | Engels leeg                                                           |
| ------- | --------------------------------------------------------------------- |
| Label   | Terugvallen -- een cijfer zonder naam is een cijfer zonder betekenis. |
| Notitie | Weglaten -- de tegel ziet er zonder ook goed uit.                     |
| Groep   | **Terugvallen**, anders dan de meeste optionele velden.               |

Die laatste is de uitzondering, en met reden: een groep zonder kop is een
streep zonder uitleg.

## De groep is een sleutel én een label

`group_nl` doet twee dingen. Het is de **sleutel** waarop gegroepeerd
wordt, en het Nederlandse kopje. `group_en` is alleen het Engelse kopje.

**Groeperen gebeurt altijd op het Nederlandse veld**, ook voor een
Engelse bezoeker. Zou je op de vertaalde waarde groeperen, dan valt een
groep in het Engels uit elkaar zodra één item zijn Engelse groepsnaam
mist -- en dan staat dezelfde site in twee talen anders ingedeeld. Dat is
precies het soort fout dat niemand ziet tot een bezoeker de taal omzet.
`StatisticGroupTest` heeft er een test voor met die naam.

**Er zijn twee plekken waar de klant een groep zet, en dat is met reden.**

| Waar                                     | Wat het is                                                                                                                                                                                           |
| ---------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Het **bewerkvenster** van één statistiek | Vrije tekst met suggesties: een `datalist` met de groepen die er al staan, uit `Statistic::bestaandeGroepen()`. Zonder die suggesties belanden "netwerk" en "Netwerk" naast elkaar als twee groepen. |
| Het **indelingsvenster**                 | Een vak per groep. Daar kies je een bestaande groep en typ je geen naam, dus die vergissing kan er niet eens. Zie [hieronder](#het-indelingsvenster-elke-groep-is-een-vak).                          |

Een lege groep betekent: **los bovenaan**, boven het eerste kopje. Daar
horen de losse cijfers; ze zijn niet minder belangrijk, ze horen alleen
nergens bij.

**De volgorde van de groepen volgt hun eerste item.** Er staat dus geen
kolom "groepsvolgorde" in de tabel: wie het eerste cijfer van een groep
naar voren haalt, haalt de hele groep naar voren. In het
indelingsvenster zijn dat de pijltjes in de kop van een vak -- die
verplaatsen alle cijfers van dat vak in één keer, zodat dat vanzelf
klopt. Dat het sorteren de bestaande volgorde bewaart is geen toeval maar
een garantie: sinds PHP 8.0 zijn de sorteringen stabiel.

## Hoe het op de landingspagina komt te staan

De sectiekop zoals overal, daarna per groep een kopje en daaronder de
cijfers.

### Binnen een groep geldt de sleepvolgorde, met een band per vorm

De items staan in de volgorde die de klant heeft gesleept. Er begint een
**nieuwe band** zodra de vorm verandert: zet hij ring, ring, balk, ring
neer, dan krijgt hij een rij met twee ringen, daaronder de balk, en
daaronder weer een ring.

Twee vormen komen dus nooit op één rij -- een ring naast een balk naast
een teller ziet er kapot uit, drie verschillende hoogtes in één raster --
en zijn volgorde wordt wél gevolgd.

> **Dit was eerst anders, en dat was een fout.** Hier stonden drie vaste
> bakken: eerst alle ringen, dan alle tellers, dan alle balken. Dat zag
> er altijd netjes uit, maar het maakte de sleepgreep onbegrijpelijk.
> Sleepte de klant een balk boven een ring, dan gebeurde er op zijn site
> niets zichtbaars; hij zag het in de tabel veranderen en op de pagina
> niet. Hij meldde dat met "lijkt me dan niet helemaal goed te zijn
> toch", en dat was het ook: een sleepgreep die niets doet is een knop
> die liegt, en een regel uitleg op het scherm repareert dat niet.
>
> `StatisticDisplayTest::test_dragging_a_bar_above_a_ring_is_visible_on_the_website`
> houdt het nu vast.

De groepen zijn de tweede helft van dezelfde verwarring, en die is op
dezelfde manier opgelost; zie
[Het indelingsvenster](#het-indelingsvenster-elke-groep-is-een-vak).

Die indeling **komt van de server**, in
`HomeController::statistiekGroepen()`. Groeperen en in banden verdelen
zijn inhoudelijke beslissingen en geen opmaak; het component tekent
alleen nog wat het krijgt.

### Het raster

| Band    | Op een telefoon | Vanaf 40rem | Vanaf 50rem |
| ------- | --------------- | ----------- | ----------- |
| Ringen  | 2 kolommen      | 4 kolommen  | 4 kolommen  |
| Tellers | 2 kolommen      | 4 kolommen  | 4 kolommen  |
| Balken  | 1 kolom         | 1 kolom     | 2 kolommen  |

Balken blijven lang één kolom, en dat is een keuze: bij drie kolommen
worden ze zo kort dat het verschil tussen 70 en 85 procent niet meer te
zien is -- en dan is de balk zinloos geworden.

## De animatie: een golf die één keer door het blok rolt

Zodra het blok in beeld komt vult alles zich: de ringen tekenen zich, de
balken lopen vol, de getallen tellen op. Elk item begint iets later dan
het vorige, dus het rolt van boven naar beneden door het blok in plaats
van dat alles tegelijk beweegt. Daarna staat het stil.

Zie `vulStatistieken()` in [`motion.ts`](../../../resources/js/lib/motion.ts).

### Dit liep eerst mee met de scroll, en dat moest eruit

De eerste versie hing de hele animatie aan de scrollpositie (`scrub`) en
zette het blok daarbij vast aan het scherm (`pin`). Naar beneden
scrollend zag dat goed uit: je stuurde het zelf aan.

**Het leverde een misverstand op dat erger was dan het effect goed was.**
Scroll je weer naar boven, dan lopen alle percentages terug. Je ziet dan
getallen veranderen die niets met je bezoek te maken hebben, en het blok
voelt veel langer dan het is -- je bent er al voorbij en het beweegt nog.
Het vastzetten maakte dat erger: dat eet een hele schermhoogte scroll op
voordat de pagina weer verdergaat.

Nu speelt het **één keer af** (`once: true`, geen `scrub`, geen `pin`).
Dat is rustiger, het is wat de tijdlijn en de dienstkaarten ook doen, en
het laat geen twijfel over wat een getal betekent.

Twee dingen die daarmee verdwenen en die je dus niet hoeft terug te
zoeken: de keuze of het blok op één schermhoogte paste, en de
`gsap.matchMedia` eromheen. Zonder pin is er niets meer te beslissen.

### Wat er een golf van maakt en geen schakelaar

1. **Elk item begint iets later.** Hoogstens anderhalve seconde voor het
   hele blok, hoe veel items er ook staan -- bij twintig stuks zou een
   vaste tussenpoos een halve minuut duren.
2. **De vulling schiet een fractie voorbij en veert terug** (`back.out`
   met een kleine uitslag). Een balk die op 85 procent hard stopt oogt
   als een laadbalk; eentje die terugveert oogt als iets dat op zijn plek
   valt. De uitslag blijft klein met reden: hoger en een ring van 95
   procent gaat even over de honderd heen, en dan tekent de boog zichzelf
   dubbel over het begin. De **waarde** wordt daarom afgekapt op honderd
   procent -- de beweging mag doorschieten, het getal niet.
3. **Een lichtpuntje loopt mee met de kop van de balk**, en dooft zodra
   hij vol is. Dat is wat je ziet vollopen in plaats van vol staan. Het
   zit op de baan en niet op de vulling: die laatste wordt met `scaleX`
   uitgerekt, dus een rond puntje zou daarin een steeds plattere ovaal
   worden.

Staat het blok al in beeld bij het laden -- een navigatie binnen de site,
of een korte pagina -- dan vuurt een scroll-trigger nooit af en speelt het
meteen. Zie `alInBeeld`; dezelfde valkuil als bij alle andere reveals
hier.

### En daarna blijft het lichtpunt rondgaan

Zodra de golf klaar is staat het blok stil, en dat was precies wat de
eigenaar eraan miste: hij vroeg om "een kleine subtiele animatie voor als
alles al gebeurd is", voor elk van de drie vormen.

Dat is er gekomen als **één idee in drie vormen: het lichtpunt dat met het
vullen meeliep, blijft rondgaan.**

| Vorm       | Wat er beweegt                                     |
| ---------- | -------------------------------------------------- |
| **Balk**   | Een glans schuift over het gevulde stuk.           |
| **Ring**   | Een kort streepje loopt een rondje over de boog.   |
| **Teller** | Een lichtje zakt langs zijn randlijn naar beneden. |

Een teller heeft geen baan en geen boog, dus daar is die randlijn links het
enige wat er al staat om iets over te laten lopen -- en dat is precies de
lijn die hem zijn vorm geeft. Zo hoeft er voor de rustanimatie nergens iets
bij dat er anders niet zou zijn.

Het draait op twee variabelen, net als het vullen zelf: `--glans` (waar het
punt staat, 0 tot 1) en `--kracht` (hoe sterk, 0 tot 1). De helper
`rustOp()` in [`motion.ts`](../../../resources/js/lib/motion.ts) zet ze; de
CSS per vorm bepaalt alleen wát er mee beweegt. Beide staan als terugval op
**0**: een lichtpunt dat stil blijft staan omdat er niets beweegt, leest als
een vlek.

Drie keuzes houden het rustig in plaats van druk:

1. **Eén lichtpunt tegelijk.** De items zijn gelijkmatig over de ronde
   verdeeld, dus het loopt als een vuurtoren het blok rond. Bij meer items
   wordt de ronde langer (zes tot veertien seconden) in plaats van de
   tussenpozen korter -- anders knippert het hele blok.
2. **`--kracht` vaart in en uit**, met een sinus over de reis. Een lichtpunt
   dat op volle sterkte begint en eindigt, duikt op en klapt weg.
3. **Het staat stil zolang je het niet ziet.** Een ScrollTrigger zet de
   tijdlijn op pauze als het blok uit beeld is; eeuwig doorlopen op iets dat
   drie schermen hoger staat kost accu en levert niets op.

Bij de ring loopt het streepje tot `--doel` min vijf eenheden. Zonder die
aftrek steekt het aan het eind voorbij de boog uit, en dan lijkt de ring even
verder te lopen dan zijn percentage. Omdat het binnen de boog blijft, maakt
een ring van tien procent een sprongetje en een van negentig bijna een hele
ronde -- en zegt de beweging dus iets over het cijfer.

Bij `prefers-reduced-motion` gebeurt er niets van dit alles: `vulStatistieken`
keert dan meteen terug en de rustanimatie begint nooit.

### Op een telefoon een stuk compacter

Dit blok wordt met elke statistiek langer, en dat is op een telefoon het
snelst een probleem: bij vijftien stuks staan er zo'n tien rijen onder
elkaar. Alle maten zijn daarom **vanaf de telefoon geschreven**, met de
ruimere waarden terug in een `@media (min-width: 40rem)`:

| Wat                      | Telefoon | Vanaf 40rem |
| ------------------------ | -------- | ----------- |
| Ring                     | 6,5rem   | 8,5rem      |
| Getal in de ring         | 1,125rem | 1,75rem     |
| Getal van een teller     | 1,5rem   | 2,25rem     |
| Ruimte tussen de bundels | 1,5rem   | 2rem        |
| Ruimte tussen de groepen | 1,75rem  | 2,25rem     |

Twee ringen van 8,5rem naast elkaar vullen op een telefoon bijna het hele
scherm; met vier ringen was je halve blok één rij cijfers.

### De balk en de ring zijn CSS, niet JavaScript

**Dit is de belangrijkste beslissing in dit blok**, en hij is een
verbetering op de eerste opzet.

Die eerste versie tekende de ring met DrawSVG. Dat werkt, maar dan hangt
de ring aan JavaScript -- en dat bleek meteen in het voorbeeldvenster van
het beheerscherm: daar scrollt niets, dus daar stond de ring altijd
helemaal vol.

Nu staat op de cirkel `pathLength="100"`, wat de omtrek omrekent naar
honderd eenheden, wat die omtrek in werkelijkheid ook is. De boog is
daarmee een `stroke-dashoffset` die uit twee variabelen komt:

- `--doel` -- het percentage van déze statistiek, als inline stijl op het
  vak;
- `--vulling` -- hoe ver de animatie is, 0 tot 1.

De balk doet hetzelfde met `scaleX`. En `--vulling` valt in `app.css`
terug op **1**, dus zonder JavaScript staat alles meteen goed, met het
juiste getal erbij. Het enige wat JavaScript doet is het wégnemen en dan
opbouwen.

Dat is de veilige kant op: een statistiek die niets laat zien is erger
dan een statistiek die niet beweegt.

`vulStatistieken` zet dus één variabele en schrijft één getal. Verder
niets.

Bij `prefers-reduced-motion` doet hij helemaal niets: de componenten
staan al op hun eindwaarde.

## Het beheerscherm

`website/Statistieken`, met een eigen regel **Statistieken** in de
zijbalk onder Website, na Certificaten.

Vier knoppen in de kop: _Bekijk het resultaat_, _Kop erboven_,
_Indeling_, _Nieuwe statistiek_. Daaronder de kaarttabel met per regel de
naam en de groep, de weergave, de waarde, het online-schuifje en de
knoppen bewerken/verwijderen.

_Indeling_ staat er **vanaf één statistiek** en niet vanaf twee, anders
dan bij de andere modules. Met één cijfer valt er niets te herschikken,
maar wel een groep voor te maken.

### De tabel staat gegroepeerd, net als de website

Niet alfabetisch en niet op aanmaakdatum, maar in precies de volgorde
waarin de bezoeker het ziet: groep na groep, met boven de eerste regel
van elke groep een kopje (`brand-tabel-groepkop`, een rij met
`colspan` en `aria-hidden`, want voor een schermlezer staat de groep al
in de kolom ernaast).

Dat kopje is er omdat de tabel anders een platte lijst is terwijl de
website gegroepeerd is. De klant kon daardoor niet zien waar een groep
ophoudt, en dus ook niet waarom slepen soms "niets" leek te doen.

Boven de lijst staat de uitleg erbij, maar alleen zodra er groepen zijn
-- bij vier losse cijfers is hij ruis.

### Het indelingsvenster: elke groep is een vak

De knop heet **Indeling** en niet _Volgorde_, want hij doet meer dan dat.
Achter die knop zit
[`StatistiekIndelingDialoog`](../../../resources/js/components/website/StatistiekIndelingDialoog.vue),
en daar staat **per groep een vak** met zijn eigen lijst.

| Wat de klant doet                | Hoe                                                   |
| -------------------------------- | ----------------------------------------------------- |
| Een cijfer naar een andere groep | Slepen naar dat vak, of de keuzelijst achter de regel |
| De volgorde binnen een groep     | Slepen, of de pijltjes naast de regel                 |
| De volgorde van de groepen       | De pijltjes in de kop van een vak                     |
| Een groep hernoemen              | In de twee naamvelden in de kop van dat vak typen     |
| Een groep erbij                  | De knop _Nieuwe groep_ onderaan                       |
| Een groep opheffen               | Het prullenbakje; de cijfers gaan naar "zonder groep" |

> **Dit scherm is drie keer anders geweest, en de eerste twee versies
> waren fout. Dat is het onthouden waard, want allebei leken ze bij het
> bouwen logisch.**
>
> 1. **Eén platte lijst.** De pagina was gegroepeerd, de lijst niet. De
>    klant sleepte iets en zag op zijn site iets anders gebeuren dan hij
>    verwachtte -- of niets.
> 2. **Een gegroepeerde lijst met een keuzelijst per regel.** Eerlijker,
>    en toch nog mis. Je kon een regel langs een kopje slepen, hij belandde
>    onder dat andere kopje, en zijn groep ging niet mee. Zijn melding:
>    sleep je hem naar een andere groep, dan "moet je eerst opslaan en weer
>    terugkomen voordat die daadwerkelijk in die groep staat". Dat was ook
>    precies wat er gebeurde.
>
> Hier zat één denkfout onder, en die is de les: **de groep was een veld
> dat náást de lijst bestond.** Zolang dat zo is, kan wat je ziet iets
> anders betekenen dan wat er staat, en helpt geen enkele regel uitleg.

**Het vak lost dat op door de twee samen te laten vallen: het vak _is_ de
groep.** Ligt een cijfer in dit vak, dan hoort het bij deze groep -- er is
geen tweede plek waar dat ook nog staat. Daarmee kan slepen niet meer iets
anders betekenen dan wat je ziet, en is er geen "eerst opslaan" meer.

Vier dingen die daarbij horen:

- **De naam van een groep staat altijd in een invoerveld**, zonder
  bewerkmodus. Hernoemen is dus typen, en een nieuwe groep is een leeg vak.
  Twee velden, Nederlands en Engels, zoals alle tekst in dit project.
- **Alle cijfers in een vak krijgen bij het opslaan de namen van dat vak.**
  Een groep kan daarmee geen twee Engelse koppen meer hebben -- wat eerder
  kon, omdat elk cijfer zijn eigen groepsnaam bewaarde.
- **De keuzelijst achter een regel blijft bestaan.** Slepen tussen twee
  vakken werkt niet met een toetsenbord, en de pijltjes blijven binnen hun
  eigen vak. Zonder die lijst kan wie niet sleept helemaal geen groep
  wisselen. Hij staat in een `<span @click.stop>`, want de regel is een
  sleepgreep.
- **Een vak licht op zodra je er iets boven houdt**
  (`dropZoneParentClass` en `synthDropZoneParentClass`, want de
  bibliotheek houdt muis en vinger apart), en een leeg vak houdt hoogte
  (`.brand-sorteer-vak`). Zonder die twee is een stapel vakken niet te
  onderscheiden en kun je niets in een lege groep leggen -- en dan lijkt
  slepen weer niets te doen.

De klassen die daarbij horen staan in `app.css`: `.brand-groepvak` (het
vak, met `[data-naamloos]` voor dat van de losse cijfers),
`.brand-groepvak-kop`, `.brand-sorteer-vak` (de lijst erin),
`.brand-sorteer-vak-doel` (het vak waar je nu in laat vallen),
`.brand-sorteer-kaart` (een regel, die eruit moet zien als iets wat je
kunt oppakken) en `.brand-vertaalknopje`.

#### Het vertaalknopje, en waarom het niet altijd staat

Naast het Engelse naamveld staat een heel klein knopje dat die ene naam
laat vertalen. Het verschijnt **alleen als het Engels niet meer bij de
Nederlandse naam kan kloppen**: het Engelse veld is leeg, of de
Nederlandse naam is sinds het openen van het venster veranderd. Zie
`magVertalen()`.

Die voorwaarde is niet alleen netheid. Hij doet twee dingen tegelijk:

1. **Het venster blijft rustig.** Bij zes groepen die allemaal al een
   Engels kopje hebben staan er geen zes knopjes te wachten op een klik
   die niets hoeft te doen.
2. **Er valt niets te overschrijven wat nog klopt.** Daarom is hier geen
   "weet je het zeker" nodig zoals in het bewerkvenster, dat wél een
   handgeschreven Engelse tekst kan wissen.

Het gaat naar dezelfde route als de grote vertaalknop
(`website.vertalen` met alleen `groep_nl`), want een eigen route ernaast
zou dezelfde begrenzing en foutafhandeling moeten herhalen. Welk vak het
vroeg weet de server niet; het venster onthoudt dat zelf.

> **Dat onthouden gebeurt in een gewone variabele en niet in een `ref`,
> en dat is met opzet.** De `onFinish` van het verzoek en de
> flash-gebeurtenis met het antwoord komen in een orde die we niet in de
> hand hebben. Zouden het molentje en de bestemming dezelfde waarde zijn,
> dan kan het antwoord aankomen nadat die al is leeggemaakt -- en dan
> verdwijnt de vertaling zonder spoor.

#### Wat het venster weigert

Twee dingen kunnen niet, en bij allebei zou de website iets anders laten
zien dan wat er in het venster staat. De knop _Opslaan_ gaat dan op slot
met de reden eronder:

1. **Een groep met cijfers maar zonder naam.** De naam is de sleutel waarop
   gegroepeerd wordt; zonder naam zijn het losse cijfers geworden.
2. **Twee groepen met dezelfde naam.** Die worden er op de website één.

Een leeg vak zonder naam is géén fout: dat is een groep die is aangemaakt
en nog niet gebruikt, en die verdwijnt gewoon -- groepen volgen uit de
cijfers, dus een groep zonder cijfers bestaat niet. Het venster zegt dat
erbij in het lege vak.

#### En wat de route ermee doet

`StatisticController::volgorde()` krijgt per regel een `id`, een `groep` en
een `groep_en`.

Die laatste is **`sometimes`** en niet verplicht, en dat is met opzet: een
verzoek met alleen de volgorde en de groep moet blijven werken, en zoekt de
Engelse naam er dan zelf bij met `engelseGroep()`. Komt hij wél mee, dan is
dat de waarheid.

Dat onderscheid is niet academisch. **Zonder het meegestuurde veld zou
hernoemen het Engelse kopje weggooien:** de nieuwe naam bestaat nog nergens,
dus `engelseGroep()` vindt er niets bij en maakt het veld leeg. Verder geldt:
geen groep betekent ook geen Engelse groepsnaam, anders sleept een los cijfer
een kopje met zich mee dat nergens te zien is tot het weer in een groep
belandt.

Acht tests in
[`StatisticPublishingTest`](../../../tests/Feature/Website/StatisticPublishingTest.php)
houden dit alles vast.

### Eén venster en geen twee stappen

Anders dan bij een dienst of een certificaat. Daar zijn vijf tot acht
velden te vertalen en is een tweede stap rust; hier zijn het er drie, en
dan kost een stap meer klikken dan hij scheelt. Het Engels staat onder
een streep, precies zoals bij `KoptekstDialoog`.

### Met een voorbeeld ernaast

Het venster laat **live zien wat je krijgt**: kies je "ring" en vul je 85
in, dan staat er een ring van 85 procent naast het formulier -- met
dezelfde componenten als op de website, dus het is geen nabootsing maar
het echte ding. Dat werkt alleen doordat de balk en de ring CSS zijn; zie
hierboven.

Dat voorbeeld is ook waarom er **geen schuifbalk** voor de waarde is: een
getalveld met een voorbeeld ernaast is nauwkeuriger dan slepen, en het
scheelt een inheems element dat we toch helemaal zouden moeten
overstijlen.

**Een waarde boven de grens wordt afgekapt zodra de weergave verandert.**
Zet je een teller van 5000 om naar een balk, dan is 5000 ineens geen
geldige waarde meer. Het stil laten staan en de server erover laten
klagen kan ook, maar dan is het formulier al fout terwijl je er nog in
zit -- en dat leest als een fout van jou in plaats van als een gevolg van
je keuze.

## Wat waar staat

| Bestand                                                                                                   | Wat het doet                                                     |
| --------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| [`Statistic`](../../../app/Models/Statistic.php)                                                          | De rij, de terugval per veld, de groepssleutel, het logboek.     |
| [`StatisticDisplay`](../../../app/Enums/StatisticDisplay.php)                                             | De drie weergaven en hun grenzen.                                |
| [`StatisticController`](../../../app/Http/Controllers/Website/StatisticController.php)                    | Het scherm, de opslag, de volgorde en de kop.                    |
| [`StatisticRequest`](../../../app/Http/Requests/Website/StatisticRequest.php)                             | De validatie, inclusief de grens die van de weergave afhangt.    |
| [`HomeController`](../../../app/Http/Controllers/HomeController.php)                                      | De indeling in groepen en de bundeling op weergave.              |
| [`vulStatistieken()`](../../../resources/js/lib/motion.ts)                                                | De golf die één keer rolt, en het lichtpunt dat daarna rondgaat. |
| [`StatistiekenSection.vue`](../../../resources/js/components/site/sections/StatistiekenSection.vue)       | Wat de bezoeker ziet.                                            |
| `StatistiekBalk.vue`, `StatistiekRing.vue`, `StatistiekTeller.vue`                                        | De drie vormen, ook gebruikt voor het voorbeeld in het portaal.  |
| [`Statistieken.vue`](../../../resources/js/pages/website/Statistieken.vue)                                | Het beheerscherm.                                                |
| [`StatistiekIndelingDialoog.vue`](../../../resources/js/components/website/StatistiekIndelingDialoog.vue) | De groepen en de volgorde: een vak per groep.                    |
| [`StatistiekGroepVak.vue`](../../../resources/js/components/website/StatistiekGroepVak.vue)               | Eén zo'n vak: twee naamvelden en zijn lijst.                     |
| [`StatisticCrudTest`](../../../tests/Feature/Website/StatisticCrudTest.php)                               | Rechten per route, beide talen, de suggesties, het logboek.      |
| [`StatisticDisplayTest`](../../../tests/Feature/Website/StatisticDisplayTest.php)                         | De drie weergaven en de grens die van de weergave afhangt.       |
| [`StatisticGroupTest`](../../../tests/Feature/Website/StatisticGroupTest.php)                             | Dat de indeling in beide talen dezelfde is.                      |
| [`StatisticPublishingTest`](../../../tests/Feature/Website/StatisticPublishingTest.php)                   | Online/offline, de volgorde, en het leeglopen van het blok.      |

## Seeddata

**Alleen de koptekst.** Geen statistieken: zonder inhoud staat het
onderdeel vanzelf niet op de website, met een uitroepteken op het
indelingsscherm zodat de eigenaar ziet waarom.

## Wat er bewust niet in zit

- **Geen stippen-weergave** ("4 van de 5"). Afgevallen in het overleg; het
  is later één `case` in de enum en één component.
- **Geen automatisch berekende cijfers.** De drie boven de tijdlijn
  blijven de enige die zichzelf tellen, want die kunnen dat ook -- ze
  komen uit de tijdlijn. Hier vult de klant zelf in.
- **Geen decimalen.** Zie hierboven.
- **Geen insertielijn tussen twee vakken.** Waar iets belandt is te zien
  aan het vak dat oplicht en aan de regel die oplicht; een derde
  aanwijzing erbij maakt het niet duidelijker en kost een plug-in.
