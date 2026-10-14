# Het LinkedIn-blok

De uitnodiging om verder te kijken op LinkedIn: een plaat met het
merkteken, een korte tekst en een knop naar het profiel. Daarnaast staat
er een klein merkteken bovenaan de pagina, naast de knoppen in de kop.

> **Eerst was dit geen module.** Er was geen beheerscherm en geen tabel;
> de tekst stond in `LinkedinSection.vue`. Nu beheert de eigenaar onder
> **Website → LinkedIn** het opschrift, de titel, de zin en de knoptekst,
> in beide talen. **De link zelf ligt nog steeds vast**; zie hieronder.

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

Er is geen validatie die meekijkt, want het adres heeft geen formulier.
De enige bescherming tegen een adres dat nergens heen gaat is
[`LinkedinSectionTest`](../../../tests/Feature/Website/LinkedinSectionTest.php),
die controleert dat het een `https`-link naar linkedin.com is. Het
beheerscherm toont het adres wel, met een slotje en een kopieerknop, zodat
de eigenaar ziet waar de knop heen gaat zonder te zoeken naar een veld dat
er bewust niet is. Een `url` die toch in een verzoek wordt meegestuurd,
verandert niets (`LinkedinCrudTest::test_a_sent_url_is_ignored`).

## Wat de eigenaar beheert

| Wat                 | Waar het staat                                   | Eindpunt                    | Leeg in het Engels                      |
| ------------------- | ------------------------------------------------ | --------------------------- | --------------------------------------- |
| Opschrift           | `section_headings` (`section = linkedin`)        | `PUT website/linkedin/kop`  | Valt terug op het Nederlands            |
| Titel               | idem                                             | idem                        | Valt terug op het Nederlands            |
| Zin eronder         | idem                                             | idem                        | Valt weg                                |
| Knoptekst (40 tkn.) | `linkedin_settings` (één rij, `LinkedinSetting`) | `PUT website/linkedin/knop` | De **Engelse standaard**: "View [naam]" |

**De kop is de gedeelde kop.** Dezelfde tabel, hetzelfde
`KoptekstDialoog`-venster en dezelfde trait (`BewaartKoptekst`) als bij
elk ander onderdeel, met de standaardtekst in `SectionHeading::standaard()`:
precies wat er in de component stond, met de Engelse vertaling die er toen
al in `lang/en.json` voor stond. Een bestaande site ziet na de update dus
niets veranderen. Zie [kopteksten](../kopteksten.md).

**De knop heeft een eigen tabel** omdat de gedeelde kop er geen plek voor
heeft. Beide kolommen zijn `nullable` en de migratie vult niets in: leeg
betekent de standaard, "Bekijk [naam]" in de taal van de bezoeker
(`LinkedinSetting::knop()`). De terugval gaat bewust niet naar het
Nederlands: een Nederlands woord op een Engelse knop leest als een fout,
en "View [naam]" is altijd waar.

**Twee vensters, twee eindpunten**, net als bij de voettekst: het ene
venster kan zo nooit de velden van het andere wissen
(`test_one_dialog_does_not_wipe_the_other`). Allebei hebben ze de
vertaalknop; de route kent daarvoor `button_nl` naast de velden van de
kop. Zie [automatisch vertalen](../automatisch-vertalen.md).

Rechten, validatie, logboek en meldingen gaan zoals overal:
`can:manage portal` op de groep in `routes/website.php`, een
`LinkedinKnopRequest`, `LogsActivity` op beide modellen, en `Toast` met
"Er was niets veranderd." als er niets veranderde. Een lege eerste opslag
van de knop maakt geen lege rij.

## In de back-up

Beide tabellen gaan mee: de kop als rij in `section_headings`, de knop in
`linkedin_settings`, en de plek en zichtbaarheid van het blok in
`page_sections`. Het adres niet; dat staat niet in de database.

Een back-up van **vóór** dit beheer heeft voor LinkedIn niets. Terugzetten
laat dan de huidige teksten staan, ook de kop -- terwijl die in een
gedeelde tabel staat die wél wordt teruggezet. Hoe dat werkt staat in
[back-ups](../../operations/back-ups.md#een-kop-van-een-onderdeel-dat-later-te-beheren-werd);
de gevallen staan in `BackupLinkedinTest`.

## Het indelingsscherm

Op het indelingsscherm stond bij dit blok eerst geen link maar de
mededeling dat er niets te beheren viel, via `PageSectionKey::teBeheren()`.
Dat is nu `true` en `beheerRoute()` wijst naar `website.linkedin.index`;
het blok staat ook in de zijbalk, tussen Contact en Voettekst. Het blijft
gewoon te verslepen en uit te zetten, en er is één onderdeel en geen
tweede module.

Sinds LinkedIn erbij staat heeft elk onderdeel een scherm, en heeft
`beheerRoute()` geen `default => null` meer: een nieuw onderdeel dwingt
daar een keuze af.

## Het rustmoment van de pagina

Na de volle blokken eromheen is dit één brede glazen plaat met weinig
erin: links de uitnodiging (opschrift, titel, één zin, de knop en het
adres klein eronder), rechts het merkteken in een baan van twee vaste
cirkels, met twee ringen die er langzaam vandaan gaan. Op een telefoon
staat het merkteken klein boven de tekst, zonder baan.

Het opschrift is het gewone sectielabel (`.brand-site-opschrift`): klein,
halfvet, cyaan, zonder lijntje. Het adres onder de knop staat in de
dataletter, zonder `https://www.`: zo zie je vóór het klikken waar je
heen gaat, en het is echte informatie en geen versiering.

De knop volgt de bewegingstaal van de site: hover alleen met een muis
(`@media (hover: hover) and (pointer: fine)`), het pijltje schuift een
stapje mee, indrukken geeft mee in 120 ms, en de focus is de cyaan rand
van twee pixels. Bij toetsenbordfocus gebeurt hetzelfde als bij hover.

De plaat krijgt een spotlicht langs de rand dat de cursor volgt
(`spotlicht()` in motion.ts, alleen met een muis). De opmaak staat in
[`linkedin.css`](../../../resources/css/site/linkedin.css).

### Het ene dat aan de scroll hangt

Vanaf 50rem draait er een stip over de buitenste cirkel mee met de
scroll: van -60° tot +60° terwijl het blok van onder naar boven door het
beeld gaat, en de baan als geheel zakt 8% van zijn hoogte trager dan de
pagina. Allebei `scrub: 0.6`, dus terugscrollen draait het terug. Het
staat op een eigen laag (`data-linkedin-baan`, `data-linkedin-draai`), los van de entree
van het merkteken, zodat de twee animaties niet op dezelfde `transform`
vechten. De tekst staat stil: die moet je kunnen lezen. Op een telefoon is
er geen baan; onder reduced motion staat de stip stil bovenaan de cirkel.

## De animatie zit op het merkteken en niet erachter

Hier stond ooit een netwerkje van lijnen dat zichzelf tekende achter de
kaart. Op het scherm was dat niets: het lag erachter, de lijnen waren
haarfijn en de helft stond buiten beeld. Een animatie die je moet
aanwijzen voordat iemand hem ziet, is geen animatie.

Wat er staat vertrekt vanaf het merkteken zelf, want daar kijk je toch
al. `zend()` in
[`LinkedinSection.vue`](../../../resources/js/components/site/sections/LinkedinSection.vue)
doet twee dingen (het stond eerst in `zendSignaal()` in motion.ts; zie
hieronder waarom het nu in het component staat):

1. **De inhoud komt van onderen op**, in leesvolgorde. Eén tijdlijn met een
   `stagger` van 0,09 seconde, dus het blok bouwt zich op zoals je het
   leest.
2. **Een ring gaat uit vanaf het merkteken** terwijl dat nog aan het
   opkomen is. Erná laten vertrekken is netter geordend en een stuk
   doder: dan is het een los trucje achteraf in plaats van één beweging.

Daarna blijven de twee ringen om beurten uitgaan. Dat doorgaande deel
staat achter `(min-width: 50rem)`: een animatie die nooit ophoudt houdt
zijn laag eeuwig in beweging, en dat kost op een telefoon scherpte en accu.

**Twee fouten die hier zaten, en die je niet zag maar wel betaalde.** De
herhalende ringen begonnen twee seconden na het laden van de pagina, of
het blok nu in beeld was of tien schermen verderop, en liepen daarna
eindeloos door. Nu:

- **begint de entree op het merkteken** (de eerste ring vertrekt op het
  moment dat je het teken ziet), op dezelfde grens als de andere reveals:
  85% van het scherm, op een telefoon 115%;
- **staat de herhaling stil buiten beeld**: een `ScrollTrigger` met
  `onToggle` pauzeert de tijdlijn zodra het blok uit beeld scrolt en hervat
  hem als het terugkomt, en hij begint pas na de entree.

Staat `prefers-reduced-motion` aan, dan staat de inhoud er meteen en
blijven de ringen onzichtbaar.

## De radar is weg

Achter de kaart draaide eerst een radarveeg (`draaiRadar()`), als
tegenhanger van de ringen: daar gaat een signaal uit, hier wordt gekeken.
In het nieuwe ontwerp is hij eruit, want hij deed hetzelfde als de ringen,
alleen drukker -- en dit blok is juist het rustmoment. De kaart kantelt
ook niet meer met de cursor; een spotlicht langs de rand is genoeg.

## Op een telefoon

Het blok is daar een plaat met een kleiner merkteken (3,75rem) boven de
tekst, zonder cirkels en zonder baan die met de scroll meedraait. De knop
is 2,75rem hoog: wat een vinger nodig heeft; het adres valt eronder als de
regel te smal is. Een lange titel of een lang woord breekt af in plaats van
de plaat breder te maken (`overflow-wrap: anywhere`).

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

| Bestand                                                                                                 | Wat het doet                                                                                                          |
| ------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| [`config/site.php`](../../../config/site.php)                                                           | Het adres.                                                                                                            |
| [`LinkedinController`](../../../app/Http/Controllers/Website/LinkedinController.php)                    | Het beheerscherm: de kop (`kop()`) en de knop (`knop()`), elk een eigen eindpunt.                                     |
| [`LinkedinSetting`](../../../app/Models/LinkedinSetting.php)                                            | De knoptekst en zijn terugval per taal.                                                                               |
| [`SectionHeading::standaard()`](../../../app/Models/SectionHeading.php)                                 | De standaardtekst van de kop, in beide talen.                                                                         |
| [`Linkedin.vue`](../../../resources/js/pages/website/Linkedin.vue)                                      | Het scherm in het portaal, met `KoptekstDialoog` en `LinkedinKnopDialoog`.                                            |
| [`Inhoudsregister`](../../../app/Support/Backup/Inhoudsregister.php)                                    | `linkedin_settings` in de back-up, en `KOP_BIJ_TABEL` voor oude back-ups.                                             |
| [`PageSectionKey`](../../../app/Enums/PageSectionKey.php)                                               | De `case`, de plek in de rij en `beheerRoute()`.                                                                      |
| [`LinkedinSection.vue`](../../../resources/js/components/site/sections/LinkedinSection.vue)             | Wat de bezoeker ziet.                                                                                                 |
| [`LinkedinMerk.vue`](../../../resources/js/components/site/LinkedinMerk.vue)                            | Het merkteken, gedeeld door de kop en het blok.                                                                       |
| [`HeroSection.vue`](../../../resources/js/components/site/sections/HeroSection.vue)                     | De kleine knop naast "Neem contact op".                                                                               |
| `zend()` in [`LinkedinSection.vue`](../../../resources/js/components/site/sections/LinkedinSection.vue) | Het opkomen van de inhoud, de ringen om het merkteken (pauzeren buiten beeld) en de baan die met de scroll meedraait. |
| [`spotlicht()`](../../../resources/js/lib/motion.ts)                                                    | Het licht langs de rand van de plaat dat de cursor volgt.                                                             |
| [`linkedin.css`](../../../resources/css/site/linkedin.css)                                              | De opmaak van het blok, `brand-site-linkedin-*`.                                                                      |
| [`LinkedinSectionTest`](../../../tests/Feature/Website/LinkedinSectionTest.php)                         | Dat het adres aankomt, klopt, en dat het blok te verslepen en uit te zetten is.                                       |
| [`LinkedinCrudTest`](../../../tests/Feature/Website/LinkedinCrudTest.php)                               | Rechten, opslaan, validatie, terugval, vertalen, logboek en wat de bezoeker ziet.                                     |
| [`BackupLinkedinTest`](../../../tests/Feature/Admin/BackupLinkedinTest.php)                             | Mee in de back-up, terug na herstel, en een oude back-up die alles laat staan.                                        |
