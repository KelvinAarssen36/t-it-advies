# Het LinkedIn-blok

De uitnodiging om verder te kijken op LinkedIn: een kaart met het
merkteken, een korte tekst en een knop naar het profiel. Daarnaast staat
er een klein merkteken bovenaan de pagina, naast de twee knoppen in de
kop.

> **Dit is geen module.** Er is geen beheerscherm en geen tabel. Wat de
> klant ermee kan is hem verslepen en uitzetten, net als elk ander
> onderdeel van de pagina.

## Waarom een eigen onderdeel en geen pictogram in de voet

Op een zakelijke site is LinkedIn waar het gesprek verdergaat. Iemand die
de pagina heeft gelezen en nog niet wil mailen, wil je wel volgen — en een
pictogrammetje onderaan haalt dat niet. Een blok dat er expliciet om
vraagt wel.

Het staat daarom standaard **ná het contactformulier**: eerst de vraag om
contact, dan de zachtere uitnodiging. De klant kan het verslepen, dus dat
is een startpositie en geen wet.

## Het adres ligt vast

In [`config/site.php`](../../../config/site.php), met een `SITE_LINKEDIN`
in de omgeving als overschrijving. **Bewust niet aanpasbaar door de
klant**: het is één adres dat niet verandert, en een tikfout in een link
naar buiten is een dode knop op de voorpagina. Dat risico weegt niet op
tegen het gemak.

Het wordt **gedeeld via Inertia** en niet per pagina meegegeven, samen met
de naam van de eigenaar. Er komen meer plekken die ernaar wijzen — de
voettekst hoort bij de layout en niet bij één pagina — en één bron
betekent dat die knoppen niet uiteen kunnen gaan lopen. Zie
`HandleInertiaRequests`.

Er is geen validatie die meekijkt, want er is geen formulier. De enige
bescherming tegen een adres dat nergens heen gaat is
[`LinkedinSectionTest`](../../../tests/Feature/Website/LinkedinSectionTest.php),
die controleert dat het een `https`-link naar linkedin.com is.

Komt er later toch een scherm voor — en dat kan, zodra er meer van dit
soort links bijkomen — dan verhuist dit naar een eigen tabel met een eigen
module.

## "Niets in te vullen" is niet "nog niet te beheren"

Op het indelingsscherm stond bij elk onderdeel zonder beheerscherm "Nog
niet te beheren". Dat is een belofte dat het er ooit komt, en bij dit
blok is die belofte onwaar: er is één link en die ligt vast.

Vandaar `PageSectionKey::teBeheren()`, naast het bestaande
`beheerRoute()`. De eerste zegt of er iets te beheren _valt_, de tweede of
er al een scherm _is_. Het indelingsscherm kent daarmee drie toestanden in
plaats van twee, en de voettekst valt in dezelfde categorie.

## De animatie zit op het merkteken en niet erachter

Hier stond eerst een netwerkje van lijnen dat zichzelf tekende achter de
kaart: vier lijnen naar buiten, met knooppunten die oppopten zodra hun
lijn er was. Op papier was dat precies waar LinkedIn over gaat. Op het
scherm was het niets — het lag áchter de kaart, de lijnen waren haarfijn
en de helft stond buiten beeld. Een animatie die je moet aanwijzen
voordat iemand hem ziet, is geen animatie.

Wat er nu staat vertrekt vanaf het merkteken zelf, want daar kijk je toch
al. `zendSignaal()` in
[`motion.ts`](../../../resources/js/lib/motion.ts) doet twee dingen:

1. **De inhoud komt van onderen op**, in leesvolgorde: het merkteken, het
   opschrift, de titel, de tekst, de knop. Eén tijdlijn met een `stagger`
   van 0,09 seconde, dus de kaart bouwt zich op zoals je hem leest.
2. **Een ring gaat uit vanaf het merkteken** terwijl dat nog aan het
   opkomen is. Erná laten vertrekken is netter geordend en een stuk
   doder: dan is het een los trucje achteraf in plaats van één beweging.

Daarna blijven de twee ringen om beurten uitgaan, met vier seconden
ertussen: vaker wordt het een knipperlicht, minder vaak en je hebt het
nooit gezien. Dat doorgaande deel staat achter `(min-width: 50rem)`, om
dezelfde reden als de ring om het portret — een animatie die nooit
ophoudt houdt zijn laag eeuwig in beweging, en dat kost op een telefoon
scherpte en accu. De eerste ring bij binnenkomst zie je daar wél.

Staat `prefers-reduced-motion` aan, dan staat de inhoud er meteen en
blijven de ringen onzichtbaar.

## En erachter draait een radar

De keuze tussen een radarveeg, een stippenraster dat meelicht, deeltjes
die naar binnen drijven en twee trage kleurvlekken is op de veeg
uitgekomen, en de reden is dat hij hetzelfde zegt als de ringen ervoor:
daar gaat een signaal uit, hier wordt gekeken. De andere drie zeggen
allemaal iets anders, en dan staan er twee losse ideeën in één blok.

Het is CSS met één regel JavaScript eromheen. De bundel is een
`conic-gradient`, de twee cirkels zijn randen, en `draaiRadar()` in
[`motion.ts`](../../../resources/js/lib/motion.ts) laat de laag opkomen
en rondgaan — achttien seconden voor een hele slag, zo traag dat je de
beweging niet betrapt en alleen merkt dat het licht verschoven is.

Twee dingen die je bij het aanpassen vast weer moet uitzoeken:

- **De volgorde van de kleurstops staat op zijn kop**, met de volle
  kleur bij 360 graden. De laag draait met de klok mee, dus de hoogste
  hoek loopt voorop; daar hoort de harde rand en niet de uitgedoofde
  staart. Zet je het om, dan draait hij zichtbaar achteruit.
- **Er ligt een `mask-image` met een gat in het midden.** Zonder dat gat
  loopt alle kleur samen tot een fel puntje achter de kaart, en zonder
  het uitdoven aan de buitenkant houdt de wig een harde cirkelrand.

Hij staat **vanaf 50rem**, dezelfde grens als de ringpuls. Op een
telefoon is er geen ruimte naast de kaart, dus er zou toch niets van te
zien zijn, en een laag die eeuwig beweegt kost daar scherpte en accu.
Hij is daar dubbel dichtgezet: `display: none` in de CSS én een
`matchMedia` in `draaiRadar`, zodat een smal venster hem ook echt stilzet
en niet alleen verbergt. Bij `prefers-reduced-motion` blijft de bundel op
nul en zie je alleen de twee cirkels.

## Op een telefoon is het blok een stuk kleiner

Het blok was daar te groot: een kaart van bijna een halve schermhoogte
voor één knop, met een gloed van 26rem die op een toestel van 375 pixels
breder is dan het scherm — dan is het geen gloed achter de kaart meer
maar een waas over het hele blok.

Alle maten van dit blok zijn daarom **vanaf de telefoon geschreven**, met
de oude, ruimere waarden terug in een `@media (min-width: 40rem)`. Dat is
dezelfde grens die de titel al had. Op desktop en tablet verandert er dus
niets; het is een aanpassing die alleen de telefoon raakt.

De knop is wel smaller geworden maar niet lager: 2,75rem is wat een
vinger nodig heeft en daar gaat hij niet onder.

De kaart zelf kantelt mee met de cursor en heeft een gloed die de muis
volgt: dezelfde twee effecten als op de hero en de dienstkaarten, zodat
het als hetzelfde huis voelt. De kanteling is hier zachter (2,5 graden in
plaats van 4), want dit is één groot vlak en daarop voelt vier graden als
scheefzakken.

## Het merkteken in de kop

Naast "Neem contact op" en "Bekijk de diensten" staat een derde knop: het
merkteken in een rondje, zonder tekst. Bewust klein en stil — een derde
knop met een woord erin zou de twee ernaast verzwakken, en de vraag om
contact is belangrijker dan de vraag om te volgen.

Bij aanwijzen draait het teken één hele slag rond (`rotate: 360deg`, een
halve seconde). Dat is de enige beloning die erin zit, en hij komt ook
bij toetsenbordfocus. Achter `prefers-reduced-motion` staat hij stil.

Het rondje is 2,75rem in het vierkant, ruim boven de 44 pixels die een
vinger nodig heeft. Op een telefoon staat hij naast de twee knoppen in
plaats van eronder: hij is smal genoeg om mee te passen, en een derde
volle regel onder de kop zou de pagina omlaag duwen.

Zelfde bron, zelfde teken: het adres komt uit dezelfde gedeelde prop en
de tekening uit
[`LinkedinMerk.vue`](../../../resources/js/components/site/LinkedinMerk.vue).
Een merkteken dat op twee plekken net anders is getekend, zijn twee
merktekens.

## Eén nieuw tabblad, en waarom dat hier wél mag

In het portaal openen links die je eruit brengen **nooit** een nieuw
tabblad; ze krijgen alleen een pijltje. Deze knop doet het wel.

Het verschil is de bestemming. Daar ga je naar je eigen website en kom je
zo weer terug; hier ga je naar een ander domein. Een bezoeker die
doorklikt hoort zijn plek op deze pagina niet kwijt te raken — en die
komt niet terug met de terugknop als hij eenmaal op LinkedIn aan het
scrollen is. Het pijltje zegt het vooraf, en `rel="noopener noreferrer"`
hoort erbij zodra je `target="_blank"` gebruikt.

## Wat waar staat

| Bestand                                                                                     | Wat het doet                                                                    |
| ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| [`config/site.php`](../../../config/site.php)                                               | Het adres.                                                                      |
| [`PageSectionKey`](../../../app/Enums/PageSectionKey.php)                                   | De `case`, de plek in de rij en `teBeheren()`.                                  |
| [`LinkedinSection.vue`](../../../resources/js/components/site/sections/LinkedinSection.vue) | Wat de bezoeker ziet.                                                           |
| [`LinkedinMerk.vue`](../../../resources/js/components/site/LinkedinMerk.vue)                | Het merkteken, gedeeld door de kop en het blok.                                 |
| [`HeroSection.vue`](../../../resources/js/components/site/sections/HeroSection.vue)         | De kleine knop naast "Neem contact op".                                         |
| [`zendSignaal()`](../../../resources/js/lib/motion.ts)                                      | Het opkomen van de inhoud en de ringen om het merkteken.                        |
| [`draaiRadar()`](../../../resources/js/lib/motion.ts)                                       | De veeg die achter de kaart rondgaat, vanaf 50rem.                              |
| [`LinkedinSectionTest`](../../../tests/Feature/Website/LinkedinSectionTest.php)             | Dat het adres aankomt, klopt, en dat het blok te verslepen en uit te zetten is. |
