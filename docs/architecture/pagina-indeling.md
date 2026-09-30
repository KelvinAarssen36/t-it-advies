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

## Eén opslag, één bevestiging

Volgorde en schuifjes gaan samen in één `PUT`. Dat is met opzet: alles wat
hier verandert staat direct live, en zou elk schuifje op zichzelf opslaan,
dan krijgt de eigenaar bij elke klik de dubbele bevestiging over iets dat
live gaat. Dan klikt hij ze weg zonder te lezen, en is de bevestiging niets
meer waard. Nu verzamelt hij eerst en bevestigt hij één keer.

Is er niets veranderd, dan gebeurt er ook niets: geen vraag, geen verzoek,
geen melding. De server weigert een lege opslag ook, maar dan heeft de
eigenaar al twee keer "ja" gezegd tegen niets.

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

**De inhoud van de diensten en de werkwijze is nog niet beheerbaar.** Die
teksten staan nog in hun Vue-component; daar staat op het scherm nog "Nog
niet te beheren". De [ervaring](modules/ervaring.md) was het eerste
onderdeel met een eigen beheerscherm en is het voorbeeld voor een module
met een lijst; de [kop](modules/kop.md) is het voorbeeld voor een
onderdeel dat alleen uit wat vaste tekst bestaat.

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
