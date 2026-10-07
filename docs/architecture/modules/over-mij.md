# Module Over mij

Een kort stuk over de eigenaar op de voorpagina, en als hij dat wil een
uitgebreidere pagina erachter. Qua opzet de eerste module die **één
onderwerp over twee plekken verdeelt**, en dat is waar bijna alle keuzes
hier over gaan.

## De vraag die deze module moet oplossen

> "Bedenk wel even goed dat het wel overzichtelijk blijft van wat nou op het
> Over mij-gedeelte komt en wat dan voor de extra page is bij het maken van
> de module."

Dat is de zorg van de eigenaar, en hij is terecht: twee bestemmingen in één
formulier is de makkelijkste manier om een scherm onbegrijpelijk te maken.
Het antwoord zit op drie plekken tegelijk -- in de database, op het scherm
en in de validatie -- en die drie zeggen hetzelfde.

### In de database: twee groepen kolommen

| Kolom                                     | Waar het staat                    |
| ----------------------------------------- | --------------------------------- |
| `summary_nl` / `summary_en`               | Het blok, altijd op de voorpagina |
| `page_title_*`, `page_intro_*`, `story_*` | Alleen op `/over-mij`             |
| `page_enabled`                            | Of die pagina bestaat             |
| `photo_path`                              | **Allebei**                       |

Die laatste regel is de reden dat de foto een eigen rij heeft in deze tabel
en een eigen venster op het scherm. Zie het stuk over de ene foto verderop.

De punten staan in een **eigen tabel** `about_points`, want ze moeten
versleepbaar zijn en per regel te valideren. Dezelfde afweging als bij de
expertisepunten van een dienst; met een JSON-kolom kun je geen foutmelding
bij de juiste regel zetten.

### Op het scherm: een overzicht met een schakelaar

Website → Over mij is **een overzicht en geen formulier**, en dat is een
correctie op de eerste opzet. Die was één lange invulpagina met alle velden
open, en de eigenaar wees twee dingen aan die daar mis mee zijn:

1. **Je kon meteen in alles typen.** Overal elders in dit portaal gebeurt
   bewerken in een eigen venster, met een bevestiging erachter. Een pagina
   waarin je per ongeluk in een veld typt en wegklikt was de enige plek waar
   dat anders was.
2. **Je zag niet waar iets terechtkwam.** Twee blokken onder elkaar lezen als
   één formulier.

Nu staat er een [`SegmentToggle`](../../../resources/js/components/SegmentToggle.vue)
boven: **"Op je website"** óf **"Op je aparte pagina"**, nooit allebei
tegelijk. Per versie zie je wat er staat -- leesbaar, met de foto erbij en
een paar feiten eronder (lengte, of het Engels er staat, waar de pagina te
vinden is) -- en één knop **Bewerken** die het venster van díe versie opent.

**De foto staat er als beeld en niet als leeg uploadvak.** Standaard is dat
het portret uit de kop, met een regeltje eronder dat zegt wélke foto het is.
Een leeg vak zou suggereren dat er nog niets is, terwijl er altijd een foto
op de site staat. In het bewerkvenster krijgt de kiezer datzelfde beeld als
`bestaand`, dus hij staat ingevuld. Hij staat bij **beide** versies in het
overzicht, want hij staat ook op beide pagina's; zie het stuk over de ene
foto verderop.

**Het schuifje van de pagina staat op het overzicht en niet in een venster.**
Eén waarde omzetten hoort niet het hele formulier langs de validatie te
sturen -- zelfde afspraak als het online-schuifje bij de andere modules. Er
zit wel een bevestiging achter, en de melding zegt het als de pagina nog
leeg is: aanzetten zonder verhaal levert geen pagina op.

### Een eindpunt per venster, en dat is geen netheid

| Eindpunt                      | Wat het bewaart                        |
| ----------------------------- | -------------------------------------- |
| `PUT website/over-mij/blok`   | De samenvatting, in twee talen         |
| `PUT website/over-mij/pagina` | De titel, de inleiding en het verhaal  |
| `PUT website/over-mij/foto`   | Het portret, dat op allebei staat      |
| `PATCH .../pagina-aan`        | Het schuifje, zonder iets te valideren |

Hier zat eerst één `PUT` voor alles, omdat het scherm één formulier was.
Sinds het twee vensters zijn is dat **een fout met een stille uitkomst**:
bewaart het ene venster, dan gaan de velden die het niet kent als leeg mee en
wordt de andere helft gewist. Je merkt het pas als je verhaal weg is. Elk
eindpunt heeft daarom zijn eigen `FormRequest` met zijn eigen velden, en
`OverMijTest::test_saving_one_half_leaves_the_other_alone` houdt het vast.

### Eén taal tegelijk in het venster van de pagina

Het venster van de pagina had zes velden onder elkaar: twee titels, twee
inleidingen en twee verhalen van zesduizend tekens. Dat is te veel voor één
venster, en bij een lange tekst in twee talen naast elkaar weet je na drie
regels niet meer welke kolom welke is.

Daar staat nu dezelfde taalschakelaar boven als op de publieke site --
[`SegmentToggle`](../../../resources/js/components/SegmentToggle.vue) met de
vlaggen, zoals [`LocaleToggle`](../../../resources/js/components/site/LocaleToggle.vue)
hem al gebruikt. De namen van de talen komen uit `page.props.locales`, dus
uit dezelfde bron als die schakelaar; een taalnaam staat in zijn eigen taal
en gaat niet door `__()`.

Drie dingen die daarbij horen, want een verborgen veld is een val:

- **Bij een afgekeurde opslag springt de schakelaar naar de taal waar de
  fout staat.** Zonder dat krijg je een rode rand op een veld dat niet in
  beeld is, en lijkt het venster zomaar niet te willen opslaan. Staat de
  fout in de taal die al open is, dan blijft hij staan -- wegspringen van
  het veld waar je net in typte is hinderlijker.
- **De vertaling zet de schakelaar op Engels.** Daar landt hij, dus daar
  hoort hij zichtbaar te zijn.
- **Zonder Nederlands verhaal valt er niets te vertalen**, en de knop staat
  er dan niet. Dat moet je gezegd worden, want het Nederlands zit nu achter
  de schakelaar: er staat één regel die zegt dat je dat eerst invult.

De velden zelf worden niet opnieuw opgebouwd bij een wissel -- alleen de
kolom waar ze in schrijven verandert. Dat is niet alleen zuinig: zou het
blok opnieuw monteren, dan pakt `v-focus` de titel af van de knop die je
net had aangeklikt, en dan kun je met het toetsenbord niet terug naar de
schakelaar.

**Het venster van het blok houdt zijn twee talen naast elkaar.** Daar staan
twee korte samenvattingen van hoogstens vierhonderd tekens, en die zijn
juist makkelijker te vergelijken als ze naast elkaar staan. Sinds de foto
een eigen venster heeft is dat ook het enige wat er in staat, en dan is een
schakelaar erboven meer bediening dan het oplevert.

### Er is één foto, en die hoort bij geen van de twee

Hij staat op de voorpagina én op de aparte pagina. Dat is de hele reden dat
hij apart staat, en daar zijn twee pogingen aan voorafgegaan:

1. **Hij zat bij het korte blok**, want daar werd hij gekozen. Dan moet je
   onthouden in welk venster hij ook alweer zat, en vanuit de aparte pagina
   kijk je naar een foto die je daar niet kunt aanpassen.
2. **Toen kreeg de aparte pagina een knop die het blokvenster opende.** Dat
   loste het zoeken op maar niet de oorzaak: de foto stond nog steeds in het
   venster van één van de twee versies, en dat venster stuurde dus bij elke
   opslag van de samenvatting ook de fotovelden mee.

Nu heeft hij zijn eigen venster, zijn eigen `FormRequest`
([`AboutFotoRequest`](../../../app/Http/Requests/Website/AboutFotoRequest.php)),
zijn eigen eindpunt en een eigen knop **Foto aanpassen** in de kop van
**allebei** de versies. Hij staat ernaast in plaats van erin, en dat is
precies wat hij in de database ook is.

| Waar                     | Wat er staat                                                                      |
| ------------------------ | --------------------------------------------------------------------------------- |
| Overzicht, beide versies | De foto als beeld, met eronder wélke foto het is, en **Foto aanpassen** in de kop |
| Venster van de pagina    | Eén regel onderaan: de foto staat hier niet bij, en waar hij wel staat            |
| Het fotovenster zelf     | In de inleiding: één foto voor allebei, en daarom staat hij hier apart            |

**Drie plekken die dezelfde kolom schrijven zou erger zijn dan één plek om
hem te zoeken.** Dan kies je in het ene venster een foto en in het andere
een andere, en wint degene die het laatst opslaat -- zonder dat iets zegt
dat er een keuze is overschreven. `AboutBlokRequest` en `AboutPaginaRequest`
kennen de fotovelden daarom allebei niet, en
`OverMijTest::test_only_the_photo_endpoint_touches_the_photo` stuurt een
bestand naar allebei die eindpunten en controleert dat er niets gebeurt.
Beide kanten, want een regel die er bij één van de twee insluipt valt anders
niet op.

Twee dingen aan die knop zijn een correctie op de tweede poging, en ze gaan
allebei over hoe het eruitziet:

- **Hij staat in de kop bij de andere knoppen en niet onder de foto.** Daar
  stond hij eerst, en die kolom is precies zo breed als het portret: zeven
  rem. Een knop met tekst erin is breder, dus hij liep eroverheen -- over de
  feitenlijst ernaast. Een knop onder een smalle kolom hangen werkt alleen
  met een tekentje zonder tekst, en dat zegt hier niets.
- **De schakelaar blijft staan waar hij staat.** Eerst sleepte de knop je
  mee naar "Op je website", en dan kom je na het sluiten van het venster
  ergens anders uit dan waar je op klikte. Nu is dat vanzelf opgelost: het
  fotovenster hoort bij geen van de twee versies, dus er is niets om naartoe
  te springen.

Het naamplaatje onder de foto heeft daarbij zijn tekentje verloren. Een
plaatjesicoontje van 0.875rem in een `inline-flex` ziet er bij een label van
één regel nog uit, maar zodra de tekst over twee regels loopt staat het op
de middelste hoogte van dat blok, links buiten de tekst, en ligt het los van
alles. Een icoontje onder een foto zegt ook niets wat de foto zelf niet al
zegt.

> **Eén merkje voor twee teksten.** `machine_translated_at` is één kolom, en
> beide vensters schrijven erin. Bewaar je het ene venster met de hand
> ingevulde Engelse tekst, dan verdwijnt het merkje ook voor het andere. Het
> gevolg is één bevestiging te veel bij het opnieuw vertalen -- geen verlies
> van tekst. Bewust zo gelaten: twee kolommen voor een merkje dat alleen een
> vraagvenster aanstuurt is meer boekhouding dan het waard is.

### In de validatie: de samenvatting is begrensd

Vierhonderd tekens, met een teller die meeloopt. **Zonder die grens is de
aparte pagina er voor niets**: dan wordt het korte blok het hele levensverhaal
midden op de voorpagina. De foutmelding zegt dat ook met zoveel woorden in
plaats van alleen "mag niet meer dan 400 tekens bevatten".

Het getal staat op het model (`AboutSetting::SAMENVATTING_MAX`) en gaat als
prop naar het scherm -- stond het daar apart, dan krijgt de eigenaar een
foutmelding op iets wat het scherm net nog goedkeurde. Er is een test die
beide grenzen vasthoudt.

## De aparte pagina bestaat onder twee voorwaarden

`paginaStaatKlaar()` is `page_enabled` **én** een gevuld verhaal. Dat zijn
twee voorwaarden en niet één, en het verschil doet ertoe: zet de eigenaar het
schuifje om maar vult hij niets in, dan zou er een pagina met alleen een kop
komen te staan -- met een knop op zijn voorpagina die daarheen wijst. Een 404
is dan eerlijker dan een lege pagina.

Die ene methode is de bron voor drie dingen: of de route bestaat, of de knop
onder het blok staat, en of het beheerscherm de knop "Bekijk je pagina"
toont. Eén bron, dus ze kunnen niet uiteen lopen.

Staat het hele onderdeel uit op de indelingspagina, dan bestaat de pagina ook
niet. Dat gaat via `sectieStaatAan()` op het model, hetzelfde patroon als
`Contactformulier::staatAan()`, inclusief de terugval voor een database
waarin nog nooit is geseed.

### De pagina heeft zijn eigen titel

Niet de kop uit `section_headings` -- die hoort bij het blok op de
voorpagina. Een pagina met dezelfde titel als het blok waar je vandaan klikt
leest alsof je niet bent verdergegaan.

Vult de eigenaar geen titel in, dan valt de pagina terug op de titel van het
blok. Beter een kop die dubbelt dan een pagina zonder kop.

## De talen

| Veld         | Engels leeg                            |
| ------------ | -------------------------------------- |
| Samenvatting | **Blok valt weg** op de Engelse site   |
| Verhaal      | **Pagina valt weg** op de Engelse site |
| Punt         | **Dat punt** valt weg; de rest blijft  |

**Geen terugval op het Nederlands, nergens in deze module.** Dat is anders
dan bij de naam van een dienst of een vraag in de FAQ, en met reden: dit is
de plek waar een bezoeker vertrouwen moet opbouwen, en een Nederlandse
alinea over de eigenaar zegt een Engelse bezoeker niets. Weglaten is dan
eerlijker dan iets tonen dat hij niet kan lezen.

Daarom rekent de teller in
[`AppServiceProvider`](../../../app/Providers/AppServiceProvider.php) **met
de taal mee**: hij kijkt naar `samenvatting()` en niet naar de kolom, zodat
het blok op de Engelse site ook echt van de pagina valt.

Bij de punten is het een andere afweging, en die valt andersom uit dan bij de
FAQ: een rijtje met drie Engelse en twee Nederlandse punten is slordiger dan
een rijtje van drie. Bij een vraag is de vertaling de helft van het enige dat
er staat; bij een punt is het één van tien.

## De foto

**Het medaillon is de standaard en niet een terugval bij een fout.** Dat is
het portret dat al in de hero staat: het oog-embleem uit de huisstijl met het
uitgeknipte portret erop, in vier maten. Eén bron, meteen in de huisstijl, en
niets om te uploaden.

Zet de eigenaar er een eigen foto in, dan gaat die voor. Haalt hij hem weer
weg, dan staat het medaillon er weer -- **hij kan dus niet in een toestand
komen zonder beeld**, en dat is precies waarom het medaillon de standaard is
en geen "als er niets is".

### Hoe die foto wordt opgeslagen

Door dezelfde gedeelde [`Logo`](../../../app/Support/Media/Logo.php) als een
certificaatlogo, met hetzelfde uitsnijvenster in de browser
(`LogoKiezer.vue`) -- dat component is volledig generiek en kent geen
veldnamen. Twee dingen zijn anders:

- **640 pixels in plaats van 256.** Dat is dezelfde maat als het medaillon,
  en dat is de hele reden voor dat getal: de eigen foto komt op precies
  dezelfde plek, dus een andere maat zou de pagina laten verspringen zodra
  hij er een inzet. `Logo::bewaar()` heeft daarvoor een maat als parameter
  gekregen; de rekensom daar is verhoudingsgewijs, dus het voorbeeld in de
  browser hoeft die maat niet te kennen.
- **Eigen grenzen**, in `media.portret`. Een logo van 48 pixels is nog
  bruikbaar in een belletje op de tijdlijn; een portret van 48 op een pagina
  is een vlek. Vandaar 240 als kortste zijde.

De velden heten `foto_*` en niet `logo_*`. Dat kon doordat
[`LogoVelden`](../../../app/Http/Requests/Website/Concerns/LogoVelden.php)
nu een overschrijfbare veldnaam en configuratiesleutel heeft -- zo staat de
validatie nog steeds op één plek. De publieke methoden daar heten nog
`logo()` en `wilLogoWeg()`; dat is de prijs van niet overal namen omgooien.

### De drie gevallen bij een opslag

Hetzelfde als bij een certificaatlogo, en het middelste is het
belangrijkste: **er kwam niets mee en er is niets gevraagd, dus we laten het
staan.** Zou het veld er altijd in zitten, dan raakt de eigenaar zijn foto
kwijt zodra hij een woord in zijn verhaal verbetert.

De oude foto wordt pas weggegooid nadat de nieuwe staat. Zou dat eerder
gebeuren en de opslag daarna mislukken, dan is het bestand weg en verwijst de
rij nog ernaar.

## Waar het blok staat

Standaard vlak na de kop: "wie ben ik" komt voor "wat doe ik". Een bezoeker
die net heeft gelezen wát je aanbiedt weet nog niet van wie hij het koopt, en
bij een eenmanszaak is dat precies de vraag die eerst komt.

De eigenaar kan het daarna zelf anders slepen onder Website → Indeling. Op
een database die al gevuld is komt een nieuw onderdeel achteraan te staan;
zie [pagina-indeling](../pagina-indeling.md).

Het onderdeel verdwijnt van de site zolang er geen samenvatting is -- in de
taal van de bezoeker. **De teller kijkt met opzet niet naar de punten**: die
staan alleen op de aparte pagina, en zou hij daarop afgaan, dan valt het hele
"Over mij" van de voorpagina tot de eigenaar zijn derde bulletje heeft
getypt.

## Het ontwerp van de twee publieke onderdelen

**Het blok op de voorpagina**: de foto en één alinea naast elkaar vanaf de
tabletgrens, eronder op een telefoon. De foto heeft een vaste maat en de
alinea vult de rest -- omgekeerd zou een korte samenvatting een enorme foto
naast zich krijgen. Het beeld is rond, ook een eigen foto, omdat het
medaillon rond is; anders verspringt de pagina zodra de eigenaar er een
inzet.

**De aparte pagina** volgt de aparte contactpagina als voorbeeld, inclusief
de drie dingen die daar eerst misgingen: een kop die echt wordt getekend (en
dus de veldnamen van de server gebruikt), een omhulsel met ruimte boven en
onder in plaats van tekst van rand tot rand, en iets dat van de pagina meer
maakt dan een lap tekst -- hier de foto, de naam met het adres, en de punten.

Daar kwamen drie dingen bij, nadat de eigenaar de pagina voor het eerst zag:

- **De contactgegevens zijn kaartjes geworden** (`brand-overmijpagina-kaartje`):
  het mailadres en, als die is ingesteld, LinkedIn. Allebei met hun tekentje
  en met het pijltje naar buiten bij de link die het portaal verlaat.
- **Het mailtekentje stond náást de tekst in plaats van ervoor.** Dat was
  geen opmaakfout in dit component maar `svg { display: block }` uit
  Tailwinds preflight, die een tekentje in een `<a>` op een eigen regel zet.
  `.brand-sitemail` is daarom `inline-flex` -- en dat repareerde ook de
  aparte contactpagina, waar het net zo stond.
- **Achter de foto staat beweging**: twee ringen die in zeven en veertien
  seconden ademen en draaien (`brand-portret-adem`, `brand-portret-draai`).
  Langzaam genoeg om niet op te vallen zolang je leest, en onder
  `prefers-reduced-motion` staan ze stil.

Het verhaal staat in één kolom van hoogstens 68 tekens. Een alinea over de
volle breedte van een scherm raak je kwijt als je naar het begin van de
volgende regel terugkijkt, en bij een persoonlijk verhaal is dat hinderlijker
dan bij een opsomming.

**En het staat op `--foreground` en niet op `--muted-foreground`**, anders dan
alle andere leestekst op de site. Dit is het enige stuk dat een bezoeker echt
gaat zitten lezen, en op donker leest Steel Silver dan te zwak. Het maakt de
pagina bovendien samenhangend: de punten eronder hebben geen eigen kleur en
erven die lichte tekstkleur al, dus met het verhaal op gedempt stond het
belangrijkste stuk van de pagina zwakker dan de opsomming erna. Witregels worden alinea's met `white-space: pre-line`
-- dezelfde afspraak als bij een dienst en bij een antwoord in de
vragenlijst, dus geen opmaakbalkje en niets dat scheef kan staan.

## Tests

| Bestand       | Wat het bewaakt                                                                                                                                                                                                                                                                     |
| ------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `OverMijTest` | Rechten per route, beide talen, de grens op de samenvatting, dat de pagina twee voorwaarden heeft, de punten met hun maximum en volgorde, de vier gevallen rond de foto, dat het bewaren van de ene helft de andere met rust laat, en dat alleen het foto-eindpunt de foto aanraakt |

## Wat er bewust niet in zit

- **Geen eigen blokken die de eigenaar zelf sleept op de aparte pagina.** Hij
  koos daar niet voor, en terecht: dat is een paginabouwer, en een leeg blok
  is een gat op de website.
- **Geen opmaakbalkje in het verhaal.** Witregels worden alinea's, en dat is
  genoeg. Zo kan er ook niets scheef staan.
- **Geen tweede foto.** Eén beeld per pagina; een galerij is een ander
  onderwerp met zijn eigen opslag en zijn eigen ontwerp.
- **Geen terugval op het Nederlands.** Zie het stuk over de talen: dat is
  hier een beslissing en geen vergetelheid.
