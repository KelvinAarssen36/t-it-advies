# De kop van de landingspagina

Het eerste dat een bezoeker leest: het opschrift, de grote titel eronder
en de zin daar weer onder. Drie teksten, en meer is het niet.

> **Waarom dit een module is.** Ze stonden hardgecodeerd in
> `HeroSection.vue`, en daarmee was het enige stuk van de voorpagina dat
> iedereen ziet ook het enige dat de klant niet kon aanpassen. Dat is
> precies omgekeerd aan het uitgangspunt van dit project: inhoud is data,
> geen code.

| Onderdeel                        | Waar de klant het beheert | Waar het vandaan komt    |
| -------------------------------- | ------------------------- | ------------------------ |
| Het **opschrift** boven de titel | Website → Kop             | `hero_headings`, één rij |
| De **titel**                     | Diezelfde knop            | Dezelfde rij             |
| De **zin** eronder               | Diezelfde knop            | Dezelfde rij             |

De knoppen ("Neem contact op", "Bekijk de diensten") en de foto horen er
bewust níet bij; zie [Wat er bewust niet in zit](#wat-er-bewust-niet-in-zit).

## Het kleinste beheerscherm, en dat is de bedoeling

Er is één landingspagina en dus één kop. Er valt niets aan te maken en
niets te verwijderen, dus er is geen tabel, geen zoekveld en geen
detailpagina. Alleen een overzicht van wat er nu staat, en één knop die
het bewerkvenster opent.

Toch gelden dezelfde afspraken als bij de [tijdlijn](ervaring.md), en met
opzet:

- **Bewerken gebeurt in een venster**, niet op de pagina zelf. Alles wat
  de klant hier aanpast staat direct live, dus het verschil tussen "ik
  kijk" en "ik wijzig" hoort zichtbaar te zijn.
- **Twee bevestigingen**, want dit is een bestaand item wijzigen.
- **Allebei de talen los**, met de vertaalknop ernaast.
- **Het activiteitenlogboek** loopt mee via `HeroHeading`.

Één opslag voor alle drie de teksten, want op de website is het één blok.
Ze los kunnen bewerken zou betekenen dat de eigenaar drie keer bevestigt
voor één zichtbare verandering.

## Het overzicht toont een voorbeeld, geen veldenlijst

Het scherm zet de twee talen náást elkaar, elk als een nagebootst blok:
het opschrift klein en in hoofdletters, de titel groot eronder, de zin
daar weer onder.

Naast elkaar en niet onder elkaar met een tabje ertussen, want de vraag
die je op dit scherm hebt is bijna altijd "loopt het Engels achter op het
Nederlands". Met een tabje moet je daarvoor heen en weer klikken en
onthouden wat er in het andere vak stond.

Ontbreekt er Engels, dan staat in het rechtervak **schuingedrukt het
Nederlands** dat de bezoeker dan te zien krijgt -- geen leeg vak, want dat
suggereert dat er niets komt te staan terwijl er wel degelijk iets staat.

## De terugval verschilt per veld

| Veld            | Engels leeg?                 | Waarom                                                                |
| --------------- | ---------------------------- | --------------------------------------------------------------------- |
| **Opschrift**   | valt terug op het Nederlands | Op een telefoon is dit de functie op het visitekaartje; leeg is stuk. |
| **Titel**       | valt terug op het Nederlands | Een pagina zonder kop is stuk.                                        |
| **Zin eronder** | valt **niet** terug          | Half Nederlands op een Engelse pagina is slordiger dan geen zin.      |

Dat is dezelfde verdeling als bij `ExperienceHeading` en bij `Experience`,
en de regel erachter is telkens: **een verplicht veld valt terug, een
optioneel veld wordt weggelaten.** Zie
[vertalingen](../vertalingen.md).

De keuze tussen de talen wordt op de server gemaakt, in
`App\Models\HeroHeading`. De Vue-component krijgt drie kant-en-klare
teksten en beslist niets meer. Zou de site dezelfde vorm krijgen als het
portaal, dan moet elk component zelf bedenken welk veld terugvalt -- en
dan staat er vroeg of laat half Nederlands op een Engelse pagina.

## Mobiel volgt vanzelf

Het opschrift staat op twee plekken op de pagina, en welke je ziet hangt
af van de breedte:

- **Vanaf een tablet** staat het klein in hoofdletters boven de titel.
- **Op een telefoon** staat het op het visitekaartje, als functie onder de
  naam. Dat opschrift boven de titel is daar verborgen, anders zou het
  hetzelfde twee keer zeggen.

Allebei lezen ze hetzelfde veld, dus de klant past het op één plek aan en
het klopt overal. Er is hier niets aparts voor mobiel te beheren, en dat
is met opzet: een tweede veld "en dit op een telefoon" is een tweede tekst
die kan achterlopen.

De zin eronder is optioneel en verdwijnt met `v-if` als hij leeg is --
geen lege alinea die de knoppen omlaag duwt.

## Eén rij, en dat is geen tabel die groeit

`hero_headings` heeft één rij en geen `key`-kolom. Dezelfde afweging als
bij `experience_headings`: een sleutelkolom zou suggereren dat er meer bij
kunnen komen, en een tabel die liegt over wat hij bevat is erger dan een
tabel met één rij.

Gebruik `HeroHeading::huidige()` en niet `find(1)`. Die eerste geeft ook
op een verse database iets bruikbaars terug, zodat een vergeten seeder
geen lege voorpagina oplevert. Hij **slaat niets op** als de rij er nog
niet is: dit wordt ook aangeroepen bij het tonen van de publieke site, en
een GET hoort niets weg te schrijven.

De tekst staat daardoor op twee plekken: in `huidige()` als vangnet, en in
`HeroHeadingSeeder` als de rij die er echt hoort te staan. Dat is bewust
dubbel.

## De seeddata is een startpunt

Anders dan bij de tijdlijn, waar de seeder de **echte loopbaan** van de
eigenaar bevat, staat hier tekst die er vooral is zodat de voorpagina niet
leeg is. Het is de tekst die tot nu toe in het Vue-component stond. De
klant schrijft hem om zodra hij weet wat er moet staan; dat is precies
waarvoor dit scherm bestaat.

De seeder is **aanvullend en niet leidend**: staat er al een rij, dan
blijft die staan. Zou hij zijn eigen tekst terugzetten, dan gooit elke
deploy weg wat de klant had geschreven -- en dat ziet hij op zijn eigen
voorpagina.

## De vertaalknop is verhuisd

Deze module was de aanleiding om `POST website/ervaring/vertalen` te
verplaatsen naar **`POST website/vertalen`**, met een eigen
`TranslateController`.

De knop hoort niet bij de tijdlijn maar bij elk beheerscherm van de
website. Zou de kop naar `website/ervaring/vertalen` posten, dan is dat
een adres dat liegt over waar je bent. Een tweede route ernaast is het
alternatief, en dat is erger: dan staan dezelfde begrenzing, dezelfde
foutafhandeling en dezelfde vertaling van sleutelnamen op twee plekken.

Eén adres dus, met een veld per soort tekst dat een module kan sturen; de
kop voegde daar `eyebrow_nl` aan toe. Zie
[automatisch vertalen](../automatisch-vertalen.md).

## Wat er bewust niet in zit

- **De twee knoppen** ("Neem contact op", "Bekijk de diensten"). Die
  verwijzen naar onderdelen die de klant aan en uit kan zetten, en ze
  verdwijnen al vanzelf als het doel er niet is. Hun tekst aanpasbaar
  maken levert een knop op die "Neem contact op" heet en naar de diensten
  gaat.
- **De foto en het beeldmerk.** Dat is beeld en geen tekst; een
  uploadscherm daarvoor is een eigen module met eigen afwegingen over
  formaat en bijsnijden. Zie het logo bij een ervaring voor hoe dat
  eruitziet als we het doen.
- **De naam op het visitekaartje.** Die komt uit `config/security.php`,
  waar hij als het account van de eigenaar al vastligt. Hem hier ook
  kunnen wijzigen betekent dat dezelfde persoon op twee plekken anders
  kan heten.
- **Een aparte tekst voor mobiel.** Zie hierboven.

## Wat waar staat

| Bestand                                                                                | Wat het doet                                             |
| -------------------------------------------------------------------------------------- | -------------------------------------------------------- |
| [`HeroHeading`](../../../app/Models/HeroHeading.php)                                   | De rij, de terugval tussen de talen, het logboek.        |
| [`HeroController`](../../../app/Http/Controllers/Website/HeroController.php)           | Het scherm en de opslag.                                 |
| [`TranslateController`](../../../app/Http/Controllers/Website/TranslateController.php) | De vertaalknop, voor alle modules.                       |
| [`HeroHeadingSeeder`](../../../database/seeders/HeroHeadingSeeder.php)                 | De starttekst.                                           |
| [`Kop.vue`](../../../resources/js/pages/website/Kop.vue)                               | Het overzicht met het voorbeeld in twee talen.           |
| [`KopDialoog.vue`](../../../resources/js/components/website/KopDialoog.vue)            | Het bewerkvenster.                                       |
| [`HeroSection.vue`](../../../resources/js/components/site/sections/HeroSection.vue)    | Wat de bezoeker ziet.                                    |
| [`HeroHeadingTest`](../../../tests/Feature/Website/HeroHeadingTest.php)                | De rechten, de terugval per veld, de lengtes, de seeder. |
