# Module FAQ

De veelgestelde vragen: één lijst met vragen die een bezoeker openklapt.
Qua opzet de eenvoudigste module van het project -- geen groepen, geen
bijlagen, geen tweede tabel -- en daarom ook de kortste beschrijving. Wat
hier staat zijn de keuzes die niet voor zich spreken.

## De gegevens

Eén tabel, `faq_items`:

| Kolom                   | Wat                                       |
| ----------------------- | ----------------------------------------- |
| `question_nl` / `_en`   | `string(160)` -- moet op één regel passen |
| `answer_nl` / `_en`     | `text` -- mag witregels hebben            |
| `published`             | Of de vraag op de website staat           |
| `position`              | De volgorde die de eigenaar sleept        |
| `machine_translated_at` | Of het Engels van de vertaaldienst kwam   |

**De twee lengtes liggen ver uit elkaar, en dat is de regel zelf.** Een
vraag die niet op één regel past is geen vraag meer maar het begin van het
antwoord. Het bewerkvenster dwingt dat af met een `Input` in plaats van een
`Textarea`, zodat je het merkt terwijl je typt en niet achteraf.

Een antwoord mag 2000 tekens, en dat is ruim drie flinke alinea's. Daarboven
is het geen antwoord meer maar een pagina; wie meer kwijt wil heeft een
dienst nodig of een stuk op zijn "Over mij"-pagina. Dat getal staat in
[`FaqItemRequest::ANTWOORD_MAX`](../../../app/Http/Requests/Website/FaqItemRequest.php)
en gaat als prop naar het venster -- stond het daar apart, dan krijgt de
eigenaar een foutmelding op iets wat het scherm net nog goedkeurde. Er is
een test die dat vasthoudt.

### Geen groepen

Een uitdrukkelijke keuze van de eigenaar: bij een handvol vragen voegt een
kopje niets toe en wordt het beheerscherm er alleen drukker van. Komt er
ooit een tweede onderwerp bij -- "over tarieven", "over samenwerken" -- dan
is dat een kolom erbij en een vak in het indelingsvenster, precies zoals bij
de [statistieken](statistieken.md).

### Het Engels valt terug, allebei de velden

| Veld     | Engels leeg                                                                |
| -------- | -------------------------------------------------------------------------- |
| Vraag    | Terugvallen -- een antwoord zonder vraag is onleesbaar.                    |
| Antwoord | Terugvallen -- een vraag die opengaat en leeg is, is erger dan geen vraag. |

**Dat is met opzet anders dan bij een expertisepunt onder een dienst**, waar
een onvertaald label juist wordt wéggelaten. Het verschil zit in de
verhouding: daar is het één label in een rijtje van acht en valt er niets
op, hier is het de helft van het enige dat er staat.

De terugval is per veld. Vertaalt de eigenaar alleen de vraag, dan krijgt
een Engelse bezoeker die Engelse vraag met het Nederlandse antwoord eronder.
Dat is niet mooi, en het venster waarschuwt ervoor -- maar de helft weggooien
is geen verbetering. `FaqPublishingTest` legt alle drie de gevallen vast.

## Het blok op de website

### Twee kolommen: de kop blijft staan

Vanaf 64rem staat het blok in twee kolommen. Links de kop van de klant, die
**sticky** blijft staan terwijl je door de vragen gaat, met daaronder één
link naar het contactblok; rechts de vragen, groot gezet (tot 1,375rem),
met tussen elke vraag een haarlijn en bij de vraag die open staat een
cyaan lichtlijn die van links aangroeit. Op een telefoon staat het onder
elkaar.

**De link naar contact staat er alleen als het contactblok er ook staat**
(het menu van de server, via `useSectieLink()`). Hij glijdt met
`scrollNaar` naar het blok en zet het anker met `replaceState`, net als het
menu; Ctrl-klik en middelklik gaan gewoon door. Er staat bewust geen zin
voor ("staat je vraag er niet bij?"): de inleiding die de klant boven de
vragen zet zegt dat vaak al, en dan stond het er twee keer.

Het teken achter een vraag is een plus in een rondje van 44px, dat bij
openen een kwartslag draait tot een kruisje. Het rondje is er voor het
aanraakvlak op een telefoon.

### Bewust rust

Dit is het onderdeel waar gelezen wordt, en het enige zonder eigen
scrollmoment: de kop komt op zoals elke kop, en verder beweegt alleen het
openklappen. Dat is een CSS-overgang en dus onderbreekbaar: klik je snel
twee keer, dan keert hij halverwege om in plaats van eerst af te maken.
De vragen staan op `--site-maat-kop`, het antwoord op `--site-maat-tekst`;
de ronde knop met het plusje is 2,25rem, maar de hele regel is de knop,
dus het tikvlak is ruim.

### De accordeon komt uit reka-ui

Niet zelf gebouwd, en dat scheelt drie dingen die je met een eigen `v-if`
alle drie fout doet: de pijltjestoetsen tussen de vragen, `aria-expanded`
op de knop met `aria-controls` naar het antwoord, en de toestand op elk
onderdeel (`data-state`). Een vragenlijst die je alleen met de muis kunt
openen is geen vragenlijst.

`type="single"` met `collapsible`: er staat er **één open tegelijk** en je
kunt hem ook weer dichtklikken. Zo groeit dit blok nooit met meer dan één
antwoord, en blijft de pagina eronder staan -- dezelfde zorg als bij de
[diensten](diensten.md) en de [tijdlijn](ervaring.md), die daarom bladeren.

### Er wordt gebladerd per zes

Zes per bladzijde, ook op een telefoon. Anders dan bij de diensten, waar een
kaart op een smal scherm veel hoger is: een dichtgeklapte vraag is overal
één regel.

Van bladzijde wisselen sluit wat er open stond. Anders staat er een antwoord
open dat je niet meer ziet, en klapt het weer in beeld zodra je terugbladert
-- terwijl je inmiddels iets anders aan het lezen was.

### Maar álle vragen staan in de pagina

**Dit is het belangrijkste stuk van deze module, en het is de correctie op
een eerste opzet.** De diensten en de tijdlijn bouwen alleen de huidige
bladzijde op (`slice`). Voor een FAQ kan dat niet: wat niet in de DOM staat
ziet een zoekmachine niet -- en bij een vragenlijst is dat precies de
inhoud waarop gezocht wordt ("wat kost interim IT-management").

Daarom stuurt
[`HomeController`](../../../app/Http/Controllers/HomeController.php) álle
online vragen mee, en krijgen de vragen buiten de huidige bladzijde in
[`FaqSection`](../../../resources/js/components/site/sections/FaqSection.vue)
het attribuut `hidden` in plaats van weggelaten te worden. Dat is ook juist
voor een voorleesprogramma: wat niet in beeld staat hoort niet meegelezen te
worden. Google behandelt tekst achter een tabblad of uitklapper als normale
inhoud, dus dit is geen trucje.

Zo werkt het bovendien **of de DOM nu door de SSR-server of door de browser
wordt opgebouwd**. Dat is geen detail: `ssr.enabled` staat wel op `true` in
de config, maar `npm run build` bouwt de SSR-bundel niet en de deploy start
er geen proces voor. Zie
[openstaande punten](../../openstaand.md). Het briefje hieronder staat
daar helemaal los van -- dat komt uit Blade en is er ook zonder JavaScript.

`FaqPublishingTest::test_every_question_reaches_the_page` houdt het vast met
veertien vragen.

### En een briefje voor de zoekmachines

In de `<head>` van de landingspagina staat de lijst ook als
`FAQPage`-structuurdata (JSON-LD); `vragenBriefje()` in
[HomeController](../../../app/Http/Controllers/HomeController.php) maakt
hem, en [app.blade.php](../../../resources/views/app.blade.php) zet hem in
de pagina. Het zegt expliciet
wát die tekst is -- een vragenlijst met per vraag het bijbehorende antwoord
-- zodat een zoekmachine dat niet uit de opmaak hoeft te raden.

**Eerlijk over wat het niet meer doet.** Google liet zulke vragen vroeger
uitklapbaar onder je zoekresultaat zien; dat is in 2023 beperkt tot
overheids- en gezondheidssites. Reken er dus geen mooier zoekresultaat op.
Het echte werk doet het punt hierboven: de tekst staat in de pagina.

Het komt uit dezelfde bron als het blok, dus het kan niet uit de pas lopen
met wat er zichtbaar staat -- en dat is ook de regel van Google zelf. Zijn er
geen vragen, dan staat er niets in de `<head>`; een lege lijst opgeven is
erger dan hem weglaten.

### Openklappen met `grid-template-rows`

Het antwoord staat **altijd in de pagina** (`force-mount` op
`AccordionContent`). Dicht is het een rij van `0fr`, open een rij van
`1fr`, en daar gaat de browser zelf vloeiend tussen over -- zonder een
hoogte te meten en zonder keyframes. De tekst erin komt een fractie later
op en zakt een halve rem op zijn plek.

Een dicht antwoord is daarbij `visibility: hidden`, met een vertraging zo
lang als het dichtklappen (0,5 seconde). Zo blijft het tijdens het
dichtgaan zichtbaar, en slaat een schermlezer het over zodra het dicht is
-- net als toen het er helemaal niet stond.

Bijkomend voordeel: de antwoorden staan nu ook in de HTML, niet alleen de
vragen.

### De glans (vervallen)

Hieronder staat waarom de oude veeg (`brand-faq-knop`) een eigen veeg had
en geen `brand-glans`. **In het nieuwe ontwerp is er geen veeg meer**: de
open vraag krijgt een lichtlijn langs de onderrand. De les eronder blijft
gelden.

De FAQ heeft zijn **eigen** veeg in `brand-faq-knop`, en níet die van
`brand-glans`. Dat was de eerste opzet, en die is teruggedraaid nadat de
eigenaar hem beschreef als "een wit vlak dat voorbij raast". Dat was het
ook, om twee redenen tegelijk:

1. **De maten van die utility horen bij een knop.** Hij is 35% van de
   breedte aan 55% wit. Op een knop van 200 pixels is dat een glinstering
   van 70; op een regel van de volle sectiebreedte is het een witte baan
   van ruim 200.
2. **Overrulen lukte niet.** `brand-glans` staat buiten
   `@layer components` en de regels van deze module staan erin, en in CSS
   wint een ongelaagde regel van een gelaagde -- ongeacht volgorde of
   specificiteit. De `animation: none` die de lus moest uitzetten deed dus
   niets, en die lus van zeven seconden liep door op elke zichtbare regel.

Wat er nu staat is een glimp: een **vaste** maat van 7rem die niet meegroeit
met de breedte, **6% wit** in het midden met een zachte uitloop naar beide
kanten, en **één veeg bij het openklappen** met een piek in het midden in
plaats van een plateau -- een veeg die op volle sterkte blijft staan leest
als een voorwerp dat langsschuift.

> **De les is algemener dan deze module.** Een utility hergebruiken is goed
> zolang het dezelfde zaak is. `brand-glans` is niet "een veeg" maar "de
> veeg van de actieknop", met maten die daarbij horen. Zoiets op een element
> van een heel ander formaat zetten is geen hergebruik maar een gok -- en
> als het dan in een andere cascadelaag staat, kun je het niet eens
> bijstellen.

Bij `prefers-reduced-motion` springt een antwoord open in plaats van te
groeien, groeit de lichtlijn niet aan en draait het plusje niet. Alles
blijft werken.

### Niet `brand-blad-tekst` voor het antwoord

Die klasse doet het alineawerk ook, maar hij is gemaakt voor een venster: hij
heeft een streep erboven en op een breed scherm een eigen schuifgebied van
46svh. Een vak dat binnen een opengeklapt antwoord zelf gaat schuiven is
precies wat je hier niet wil. Wat ervan overblijft is één eigenschap --
`white-space: pre-line` -- en die staat in `brand-site-faq-tekst`
([`faq.css`](../../../resources/css/site/faq.css)).

## Het beheerscherm

Website → Vragen, met de vaste knoppenrij: _Bekijk het resultaat_, _Kop
erboven_, _Volgorde_, _Nieuwe vraag_. Daaronder de vragen als kaarttabel met
het online-schuifje en de vaste bevestigingsvensters.

Twee dingen die afwijken van de modules ernaast:

- **De tabel toont de vraag én het begin van het antwoord.** Bij een
  statistiek is de naam genoeg om een regel terug te vinden, maar twee vragen
  beginnen vaak met dezelfde woorden ("wat kost ...", "wat doe je als ...").
  Zonder dat stukje antwoord zit je te klikken om te zien welke je te pakken
  hebt.
- **De kolom Engels zegt of het er staat, niet wát er staat.** De tekst zelf
  staat in het venster; wat je in een overzicht wil weten is of er nog werk
  ligt.

Het volgordevenster zet er per regel bij op welke bladzijde hij komt. Dat is
hier meer dan een detail: de eerste bladzijde is de enige die de meeste
bezoekers zien.

### De kop boven het blok

De inleiding doet hier werk dat de vragen zelf niet kunnen doen: hij zegt wat
er moet gebeuren als het antwoord er **niet** tussen staat. Zonder die regel
is een vragenlijst een doodlopende weg voor precies de bezoeker met de vraag
die de eigenaar nog niet had bedacht -- en dat is vaak degene die hij wil
spreken. De standaardtekst in
[`SectionHeading::standaard()`](../../../app/Models/SectionHeading.php) zegt
dat, en de uitleg in het kopvenster herhaalt het.

## Waar het blok staat

Standaard vlak vóór het contactformulier. Dat is geen willekeur: een
vragenlijst neemt de laatste twijfel weg, en de knop om te mailen hoort
meteen daarna te komen. Erboven zou hij de twijfel wegnemen die nog niet
bestond.

De eigenaar kan het daarna zelf anders slepen onder Website → Indeling. Op
een database die al gevuld is komt een nieuw onderdeel achteraan te staan;
zie [pagina-indeling](../pagina-indeling.md).

Het onderdeel verdwijnt van de site zolang er geen enkele vraag online
staat. Dat is hier precies goed -- een kopje met niets eronder is slordiger
dan geen kopje -- en anders dan bij Contact, waar de teller met opzet
ontbreekt.

## Voorbeeldvragen om mee te kijken

    php artisan voorbeeld:zaaien

Zet acht vragen neer, en dat getal is gekozen: het blok bladert per zes en
een van de acht staat offline, dus er komen er zeven op de site -- zes op de
eerste bladzijde en een op de tweede. Zo zie je het bladeren echt gebeuren.

Er zit bovendien een bijzonder geval in elk van de dingen die je anders moet
uitproberen: een vraag zonder Engels (die valt helemaal terug op het
Nederlands), een antwoord met een witregel (dat worden twee alinea's), en een
vraag die offline staat (die hoort nergens op de site te staan).

Weghalen met `php artisan voorbeeld:opruimen`. Dat gaat op de Nederlandse
tekst van de vraag -- er is bij een vraag geen adres om een `@voorbeeld.test`
in te zetten -- dus het commando waarschuwt dat een eigen vraag met dezelfde
tekst meegaat. Zie
[VoorbeeldDataSeeder](../../../database/seeders/VoorbeeldDataSeeder.php).

## Tests

| Bestand             | Wat het bewaakt                                                                                                                  |
| ------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| `FaqCrudTest`       | Rechten per route, beide talen, de twee lengtegrenzen, dat witregels blijven staan, en het activiteitenlogboek                   |
| `FaqPublishingTest` | Online en offline, de volgorde, dat het blok verdwijnt en terugkomt, de terugval per veld, en dat álle vragen de pagina bereiken |

## Wat er bewust niet in zit

- **Geen zoekveld.** Bij een handvol vragen is dat een leeg vak; bij veel
  vragen is het bladeren plus zoeken, en dan kies je liever één van de twee.
- **Geen "was dit nuttig?" bij een antwoord.** Dat is een tweede logboek met
  een tweede bewaartermijn, voor een cijfer waar niemand iets mee doet.
- **Geen eigen pagina voor de vragen.** De vragen horen bij de voorpagina,
  op de plek waar iemand nog twijfelt.
- **Geen opmaakbalkje in het antwoord.** Witregels worden alinea's, en dat is
  genoeg. Zo kan er ook niets scheef staan.
