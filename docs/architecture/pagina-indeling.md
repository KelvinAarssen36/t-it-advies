# De indeling van de landingspagina

De klant bepaalt zelf in welke volgorde de onderdelen van zijn website
staan, en welke hij aan heeft. Dit document beschrijft hoe dat werkt en --
belangrijker -- **hoe je er een nieuw onderdeel bij bouwt**.

## Het idee in één alinea

De landingspagina is geen vaste pagina meer maar een rij onderdelen. Welke
onderdelen er bestáán, staat in code; welke volgorde ze hebben en of ze
aanstaan, staat in de database. De kop staat altijd bovenaan en de
voettekst altijd onderaan. Alles ertussen is van de klant.

## De drie toestanden

Dit is de kern van het scherm, en de reden dat het bestaat. Een onderdeel
dat niet op de website staat, kan daar twee heel verschillende redenen voor
hebben:

| Toestand               | Op de website | Op het indelingsscherm          |
| ---------------------- | ------------- | ------------------------------- |
| Aan en gevuld          | ja            | gewoon, met een vinkje          |
| Door de klant uitgezet | nee           | rustig en gedempt, geen alarm   |
| Aan maar nog leeg      | nee           | uitroepteken en een link erheen |

**Waarom uitgezet er anders uitziet dan leeg.** Iets dat je zelf hebt
uitgezet is een keuze, en daar hoort geen waarschuwing bij -- zet je er wél
een, dan leert de eigenaar waarschuwingen te negeren, en dan werkt hij ook
niet meer op het moment dat het er echt toe doet. Iets dat aanstaat maar
leeg is, is precies dat moment: hij denkt dat het op zijn site staat en het
staat er niet.

**Waarom een leeg onderdeel überhaupt verdwijnt.** Een kopje "Tijdlijn" met
niets eronder is slordiger dan geen tijdlijn. De bezoeker ziet het verschil
tussen "nog niet ingevuld" en "stuk" niet.

## Hoe het scherm eruitziet, en waarom

**Het overzicht staat in een venster met een adresbalk.** Dat is geen
versiering. Het zegt zonder woorden dat je naar een wébsite kijkt en niet
naar een instellingenlijst, en daardoor lezen de regels erin als één pagina
van boven naar beneden -- wat ze op de site ook zijn. Om diezelfde reden
staan de regels tegen elkaar aan met een lijn ertussen, en niet als losse
kaarten.

**Elk onderdeel heeft een schetsje.** Een paar balkjes in de vorm van het
echte ding: de kop een grote titel met twee knoppen, de diensten drie
blokken naast elkaar, de werkwijze vier genummerde stappen. Geen
schermafdruk, want die veroudert zodra de klant iets aanpast, en geen
pictogram, want dat zegt niets over wat er op die plek op zijn pagina staat.
De kop en de voettekst krijgen de merkgradient die ze op de echte site ook
hebben, zodat je ze herkent zonder te lezen.

Het schetsje verandert mee met de toestand: gedempt als het onderdeel uit
staat, gestippeld als het leeg is. De vórm blijft staan -- je moet kunnen
zien wát er leeg is.

**Bewerken gebeurt in een eigen venster**, niet op de pagina zelf. Dat is
het verschil tussen "ik kijk" en "ik ben aan het wijzigen", en dat verschil
hoort zichtbaar te zijn als alles wat je doet direct live staat. In dat
venster is elke regel wél een losse kaart met een greep, want daar pak je er
één op.

## De onderdelen, in vier bestanden

| Waar                                                                                          | Wat het weet                                               |
| --------------------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| [`PageSectionKey`](../../app/Enums/PageSectionKey.php)                                        | Welke onderdelen er bestaan, hun naam en of ze vastzitten. |
| [`page_sections`](../../database/migrations/2026_09_28_090000_create_page_sections_table.php) | De volgorde en of het onderdeel aanstaat.                  |
| [`SectionContent`](../../app/Support/Page/SectionContent.php)                                 | Of er inhoud in zit.                                       |
| [`LayoutController`](../../app/Http/Controllers/Website/LayoutController.php)                 | Het scherm, en de grendel op de vaste onderdelen.          |

### Waarom "vast" in code staat en niet in de database

Dat de kop bovenaan hoort, is geen instelling maar wat dat onderdeel ís. Zou
het een kolom zijn, dan is het een waarde die per ongeluk of met een
zelfgemaakt verzoek kan veranderen. Nu is het een `match` in een enum, en
[de test](../../tests/Feature/Website/PageLayoutTest.php) stuurt een verzoek
dat de kop naar het midden probeert te zetten om te bewijzen dat het niet
lukt.

Het slotje op het scherm is dus opmaak. Wat het écht tegenhoudt staat in
`LayoutController::controleerVolledig()`, en dat accepteert precies de
verplaatsbare onderdelen -- niet meer en niet minder.

### Waarom de kop en de voettekst tóch in de lijst staan

De eigenaar hoort zijn hele pagina te zien en niet alleen het middenstuk.
Maar ze staan buiten de sorteerlijst, niet erin met een blokkade: er bestaat
geen sleepbeweging die ze kan raken. De voettekst zit bovendien in
`PublicLayout` en niet in de pagina, want hij hoort bij elke pagina.

### Waarom een leeg onderdeel standaard als "gevuld" telt

`SectionContent` kent alleen de onderdelen die zich hebben aangemeld met een
teller. Een onderdeel zonder teller is altijd gevuld. Dat is de veilige
kant op: de kop en de voettekst hebben hun tekst in de code staan en zijn
nooit leeg. Zou het andersom zijn, dan verdwijnt een onderdeel van de
website omdat iemand vergat het aan te melden -- en dat merk je pas als de
klant belt.

## Een nieuw onderdeel toevoegen

Zes stappen. Je hoeft het indelingsscherm niet aan te raken.

**1. Een case in de enum.** In
[`PageSectionKey`](../../app/Enums/PageSectionKey.php) een `case` erbij, en
de vier `match`-blokken aanvullen: `label()`, `omschrijving()`,
`standaardPositie()` en -- zodra het beheerscherm er is -- `beheerRoute()`.
PHP dwingt dat af: een vergeten arm is een fout bij het draaien, geen stille
lege waarde.

**2. De rij in de database.** Draai `php artisan db:seed --class=PageSectionSeeder`.
Die vult aan wat ontbreekt en laat staan wat de klant al heeft ingesteld.
Bij een deploy gebeurt dat vanzelf mee met `DatabaseSeeder`.

**3. Het Vue-component.** Een bestand in
[`components/site/sections/`](../../resources/js/components/site/sections/),
met `SectieProps` als props, en opnemen in de kaart in
[`Welcome.vue`](../../resources/js/pages/Welcome.vue). Die kaart is getypt
op `SectieSleutel`, dus een vergeten component loopt stuk in TypeScript en
niet op de website.

**4. De teller.** Eén regel in `configurePageSections()` in
[`AppServiceProvider`](../../app/Providers/AppServiceProvider.php):

```php
$this->app->make(SectionContent::class)->telt(
    PageSectionKey::Timeline,
    fn () => TimelineItem::query()->count(),
);
```

Daarmee verdwijnt het onderdeel vanzelf van de website zolang de klant er
nog niets in heeft gezet, mét het uitroepteken op het indelingsscherm.

**5. De regel in de zijbalk.** Elke module hoort onder Website zijn eigen
ingang te hebben; de indeling is een kaart en geen menu. Zet hem in
`websiteItems` in
[`AppSidebar.vue`](../../resources/js/components/AppSidebar.vue).
[`AppSidebarTest`](../../tests/Feature/Website/AppSidebarTest.php) valt om
als je het vergeet -- want een module die alleen via een omweg te vinden
is, merk je zelf nooit.

**6. De kaart in de handleiding.** Onder Instellingen -> Documentatie, in
het onderdeel Website, komt een
[`UitlegKaart`](../../resources/js/components/settings/UitlegKaart.vue) met
de uitleg voor de eigenaar zelf: wat hij hier beheert, en wat zijn
bezoekers ervan zien. Zie
[de handleiding voor de eigenaar](uitleg-voor-de-eigenaar.md). Sla deze
stap niet over -- de rest van `docs/` leest hij nooit, dus zonder die kaart
weet hij alleen dát er een scherm bij is gekomen.

Vergeet daarnaast niet wat voor elke module geldt: het model krijgt
[`LogsActivity`](../../app/Models/Concerns/LogsActivity.php), de CRUD krijgt
tests, en de teksten worden tweetalig ingevoerd.

**Neem [de ervaring](modules/ervaring.md) als voorbeeld.** Dat is de eerste
module die deze zes stappen doorloopt, en de keuzes die daar zijn gemaakt
-- twee kolommen per vertaalbaar veld, het lege-veldenprobleem, een
formulier in twee stappen -- gelden voor de volgende net zo goed.

## Het menu staat ook op de subpagina's

De lijst uit dit scherm is niet alleen de pagina maar ook het menu, en
sinds kort op **alle** publieke pagina's. Dat is een correctie, en het is
nuttig om te weten waarom, want het legt drie dubbele terugknoppen uit.

`navigation` kwam alleen uit `HomeController`. Op `/privacy`, `/contact` en
`/over-mij` was die lijst dus leeg, en `SiteHeader` deed daar wat op het
eerste gezicht logisch is: is er geen menu, zet er dan "Terug naar de
website". Alleen, omdat die balk daarmee niets nuttigs meer deed, zette
elke subpagina er ook nog een eigen teruglink bij -- en de
privacyverklaring er twee, boven en onder. Drie links naar dezelfde plek,
met twee verschillende woorden ervoor.

Nu staat de lijst in [`Navigatie`](../../app/Support/Page/Navigatie.php) en
sturen alle vier de pagina's hem mee. Daarmee verandert wat een menu-item
_is_:

| Waar          | Wat een item is                                      |
| ------------- | ---------------------------------------------------- |
| De voorpagina | Een anker: `#diensten`, met de streep die meeschuift |
| Een subpagina | Een Inertia-link naar `/#diensten`                   |

**De navigatie is daarmee zelf de weg terug**, en beter dan een terugknop:
je komt bij het onderdeel dat je wilde in plaats van bovenaan. De terugknop
in de kop is weg, en de subpagina's hebben er geen eigen meer.

Drie dingen die daarbij horen:

- **`Welcome.vue` scrollt bij aankomst naar het anker uit het adres.** Zonder
  dat kom je bovenaan uit en moet je zelf zoeken, en dan is zo'n link niets
  beter dan de terugknop die er eerst stond.
- **Het merk in de kop is buiten de voorpagina een link naar huis.** Dat was
  stuk: `#top` met een handler die voor `top` altijd afbreekt en naar de
  bovenkant van de huidige pagina scrollt.
- **Het uitklapmenu op mobiel gaat dicht bij een klik die wegnavigeert.** Op
  de voorpagina deed `gaNaar` dat al; buiten de voorpagina breekt die meteen
  af, en de kop blijft bij een Inertia-bezoek staan. Zie `kiesItem`.

**Niet via de gedeelde props van Inertia.** Dat zou één regel zijn, maar dan
doet elk portaalverzoek deze query plus alle inhoudstellers voor een menu
dat daar niet bestaat.

### De voettekst deed niets, en dat kwam hierdoor boven

De voettekst heeft dezelfde lijst en dus hetzelfde nodig. Daar stond een
anker met `@click.prevent` erbij en een aanroep van
`scrollNaar('#diensten')` -- mét hekje, terwijl die functie
`getElementById` doet. Dus vond het scrollen niets, én mocht de browser de
link niet volgen: **die links deden helemaal niets, ook niet op de
voorpagina.**

Dat is een jaar lang niet opgevallen omdat een link in een voettekst er
hetzelfde uitziet of hij werkt of niet. Het kwam boven toen bleek dat er op
een subpagina nog een `#diensten` in de opmaak stond nadat de kop al was
omgezet.

Daarom staat "wat is een item en waar wijst het heen" nu in
[`sectielink`](../../resources/js/lib/sectielink.ts) en niet in de kop:
twee keer dezelfde logica is twee keer een kans hierop. Wat er bij een klik
verder gebeurt -- het uitklapmenu sluiten, het adres bijwerken -- blijft per
plek verschillen en staat dus bij de aanroeper.

Wat de pagina's zelf nog wél hebben is een kruimelpad boven
([`SiteKruimels`](../../resources/js/components/site/SiteKruimels.vue)) en
één knop onder ([`SiteTerug`](../../resources/js/components/site/SiteTerug.vue)).
Die dubbelen niet met de kop: het kruimelpad zegt waar je **bent**, het menu
waar je **heen** kunt, en de knop onderaan is het einde van de pagina. De
verdediging voor die tweede link op de privacyverklaring -- "onderaan een
lange pagina is een link bovenaan geen link" -- klopte trouwens niet:
`.brand-sitekop` is `position: sticky`, dus de navigatie is daar ook.

## De achtergrond wisselt af op plek, niet op naam

`Welcome.vue` geeft elke sectie een `tone` op basis van zijn index: even is
`base`, oneven is `raised`. Zou elke sectie zijn eigen tint kiezen, dan
staan er na een verplaatsing twee verhoogde vlakken tegen elkaar aan en is
de lijn ertussen weg. De breedte kiest een sectie wél zelf -- het
contactformulier is smal omdat een formulier over de volle breedte slecht
leest, en dat hangt niet af van waar het staat.

## Slepen én pijltjes

[`SortableList`](../../resources/js/components/SortableList.vue) doet
allebei, en dat is geen luxe. Slepen werkt niet met een toetsenbord, is
lastig met een trillende hand, en vecht op een telefoon met het scrollen van
de pagina. De pijltjes zijn de betrouwbare weg, het slepen de snelle.

Het slepen gebruikt `@formkit/drag-and-drop`. Dat kost ongeveer 10 kB
gzip, en alleen op dit scherm: het zit in de chunk van de indelingspagina en
niet in de rest van het portaal.

Twee dingen die makkelijk stukgaan en daarom in de CSS staan met een
toelichting:

- `touch-action: none` op de greep. Zonder die regel begint een veeg op de
  greep de pagina te scrollen en is slepen op een telefoon onmogelijk.
- De verschuifanimatie van Vue gaat **uit** tijdens het slepen, want dan
  animeert de bibliotheek de beweging al. Twee animaties op hetzelfde
  element geeft geschok.

### Met `groep` wisselen lijsten items uit

Staat er een naam in de prop `groep`, dan kunnen alle lijsten met diezelfde
naam items aan elkaar doorgeven. Slepen is dan niet alleen herschikken maar
ook verhuizen, en beide lijsten geven een nieuwe inhoud door.

Dat wordt gebruikt in het indelingsvenster van de
[statistieken](modules/statistieken.md#het-indelingsvenster-elke-groep-is-een-vak),
waar elke groep een eigen vak is. Drie dingen horen daar altijd bij:

1. **Een lege lijst heeft hoogte nodig** (`.brand-sorteer-vak`). Een vak van
   nul pixels is niet te raken, en dat is juist het vak waar je als eerste
   iets in wil leggen.
2. **Het vak waar je boven hangt moet oplichten**
   (`dropZoneParentClass`). Zonder dat is een stapel vakken niet van elkaar
   te onderscheiden en is het gokken waar iets belandt.
3. **De pijltjes blijven binnen hun eigen lijst.** Verhuizen kan er dus niet
   mee, en dat betekent dat er een tweede weg moet zijn die zonder slepen
   werkt -- bij de statistieken een keuzelijst achter elke regel. Anders kan
   wie niet sleept de indeling niet aanpassen.

## Twee wegen naar hetzelfde schuifje

Een onderdeel aan- of uitzetten kan op twee plekken, en ze doen iets
anders:

| Waar                     | Wat er gebeurt                                                                            |
| ------------------------ | ----------------------------------------------------------------------------------------- |
| **Op het overzicht**     | Eén onderdeel, één `PATCH`, één bevestiging. De volgorde blijft precies zoals hij was.    |
| **In het bewerkvenster** | De hele indeling in één `PUT`: de volgorde én alle schuifjes samen, door één bevestiging. |

> **Op het overzicht stond dat schuifje eerst uitgeschakeld, en dat was een
> fout.** De gedachte was dat álles via het bewerkvenster moest, zodat je
> eerst verzamelt en één keer bevestigt. Het gevolg was een schuifje dat je
> aanwees en dat niets deed, zonder een woord uitleg waarom. De eigenaar
> meldde precies dat: "als ik op aan-uitknop hover staat dat ik hem niet kan
> gebruiken (...) ik weet nu niet wat er aan de hand is", en: "het is wel
> raar dat dat bij allemaal zo is".
>
> Hij had op twee manieren gelijk. Een reden die nergens te lezen is, is
> geen reden maar een raadsel. En élke andere lijst in het portaal werkt wél
> zo -- het online-schuifje bij een dienst, een certificaat of een
> statistiek zet er één om met één bevestiging. Dit scherm was de
> uitzondering zonder dat daar iets voor te zeggen was.
>
> **De les:** een knop die er staat hoort te werken. Is er echt een reden om
> hem onbruikbaar te maken, dan hoort die reden op het scherm te staan en
> niet in een commentaarregel in de code.

De reden achter het batchgedrag van het bewerkvenster blijft wél staan:
daar zet je er meerdere tegelijk om, en dan is één bevestiging aan het eind
beter dan vijf onderweg. Dan klikt hij ze weg zonder te lezen, en is de
bevestiging niets meer waard.

Is er niets veranderd, dan gebeurt er ook niets: geen vraag, geen verzoek,
geen melding. De server weigert een lege opslag ook, maar dan heeft de
eigenaar al twee keer "ja" gezegd tegen niets.

De route voor één onderdeel weigert de **vaste** onderdelen, en niet alleen
in het scherm: de kop en de voettekst horen er altijd te staan, ook als
iemand het verzoek zelf opstelt. En hij raakt de posities niet aan -- zou hij
alles hernummeren zoals `update()` doet, dan verspringt de pagina van de
eigenaar omdat hij iets uitzette.

Het bevestigingsvenster zelf staat in
[meldingen](meldingen.md#het-bevestigingsvenster).

### Twee vensters, niet op elkaar

Het bewerkvenster gaat **dicht** voordat de bevestiging verschijnt, en ligt
er dus niet onder. Dat is geen smaakkwestie:

- De dialooglaag zet tijdens het openen de rest van de pagina op
  `pointer-events: none`. Twee vensters die tegelijk openen en sluiten
  kunnen dat op elkaar achterlaten, en dan is de pagina daarna niet meer
  aanklikbaar. Dat is een stille fout die je pas merkt als je erop klikt.
- Twee sluiers over elkaar leest bovendien niet.

Zegt de eigenaar "nee" tegen de bevestiging, dan komt het bewerkvenster
gewoon weer open met alles er nog in: de werkkopie staat op de pagina en
niet in het venster, dus die overleeft het dichtgaan. Tussen de twee zit een
korte pauze van 200 ms, precies zolang als de sluitanimatie duurt.

## Wat dit nog niet doet

**De inhoud van de werkwijze is nog niet beheerbaar.** Die teksten staan
nog in hun Vue-component; daar staat op het scherm nog "Nog niet te
beheren". Dat is sinds de module [contact](modules/contact.md) het enige
onderdeel waarvoor dat nog geldt.

> **Contact is het onderdeel dat het patroon het verst oprekt.** Hij heeft
> geen teller in `SectionContent` -- met opzet, want het formulier staat er
> ook zonder onderwerpen -- en hij kan op twee manieren op de site staan:
> als formulier onderaan de pagina of als knop naar een eigen adres. Dat
> laatste is het eerste geval waarin een onderdeel zijn inhoud buiten de
> landingspagina kan zetten.

Er zijn zeven voorbeelden om uit te kiezen als je de volgende bouwt.

[Projecten](modules/projecten.md) is het voorbeeld voor een module met een
**eigen adres per item**. Hij staat op positie 4, tussen de werkwijze en de
tijdlijn, en hij laat twee dingen zien die nergens anders staan: hoe je
"hoogstens één uitgelicht" afdwingt zonder dat de database dat kan
uitdrukken, en hoe een slug eruitziet in een project waar er verder geen
een is. Let bij het lezen vooral op de waarschuwing over `standaardPositie()`
hieronder -- die module is de eerste die er middenin is geschoven, en dat
betekende álle onderdelen erna omnummeren.

[Over mij](modules/over-mij.md) is het voorbeeld voor een onderdeel dat
**één onderwerp over twee plekken verdeelt**: een kort stuk op de
voorpagina en een uitgebreidere pagina erachter die de eigenaar aan en uit
kan zetten. Bijna alle keuzes daar gaan over hoe je dat overzichtelijk
houdt -- een overzicht met een schakelaar tussen de twee versies, een
bewerkvenster en dus een eigen eindpunt per versie, en een grens op het
korte stuk zodat die tweede pagina ergens voor is. Kijk daar als je nog zo'n
onderdeel bouwt, en lees in het bijzonder waarom die eindpunten gesplitst
zijn: met één verzoek voor allebei wist het ene venster stil de helft van
het andere.

Daarmee is `/over-mij` ook het tweede adres buiten de landingspagina, en
anders dan bij contact bestaat het **onder twee voorwaarden**: aangezet én
gevuld. Een schakelaar alleen levert daar geen pagina op.

En de [FAQ](modules/faq.md) is de kortste van allemaal: één lijst, geen
groepen, geen bijlagen. Wil je zien wat het mínimum is dat een module nodig heeft om
in dit project mee te doen, begin daar. De enige keuze die hem bijzonder
maakt zit niet in de opzet maar in het bladeren: álle items gaan naar de
pagina en alleen de huidige bladzijde is zichtbaar. De rest krijgt `hidden`
in plaats van weggelaten te worden, want wat niet in de DOM staat ziet een
zoekmachine niet.

De
[ervaring](modules/ervaring.md) is het voorbeeld voor een module met een
lijst en een detailpagina; de [kop](modules/kop.md) voor een onderdeel dat
alleen uit wat vaste tekst bestaat; de [diensten](modules/diensten.md)
voor allebei tegelijk -- een lijst met een eigen volgorde, een venster per
item, en een beheerbare kop erboven. Die laatste lijkt het meest op wat de
werkwijze nodig heeft. De [certificaten](modules/certificaten.md) laten
zien hoe je er twee lijsten in één onderdeel kwijt kunt, en de
[statistieken](modules/statistieken.md) hoe je één lijst in meerdere
vormen op de pagina zet, en hoe een indelingsvenster eruitziet waarin
items tussen groepen kunnen verhuizen.

**De kop erboven kost geen werk meer.** Sinds de koptabellen zijn
samengevoegd heeft elk onderdeel er gratis een; je hoeft er geen migratie
en geen venster voor te maken. Zie [kopteksten](kopteksten.md).

**Er is geen waarschuwing als je het bewerkvenster wegklikt met
onopgeslagen wijzigingen.** Escape of naast het venster klikken gooit je
werkkopie weg zonder te vragen. Bewust nog niet gebouwd: het venster is een
duidelijk afgebakende handeling met een zichtbare knop "Annuleren" ernaast,
en een bevestiging om een bevestiging heen maakt het niet veiliger.

## Tests

[`PageLayoutTest`](../../tests/Feature/Website/PageLayoutTest.php) bewaakt de
opslag: het recht, de volgorde, de grendel op de vaste onderdelen, een half
verzoek, en wat er in het logboek komt.
[`LandingSectionsTest`](../../tests/Feature/Website/LandingSectionsTest.php)
bewaakt de andere kant: dat de website die volgorde ook echt toont, en dat
uitgezette en lege onderdelen verdwijnen.

Die twee horen bij elkaar. Een volgorde die wel wordt bewaard maar niet
wordt getoond, is net zo stuk als een die niet wordt bewaard.
