# Module Voettekst

De afsluiting onderaan elke pagina: de merknaam met een zin eronder, het
rijtje met de onderdelen van de site, de contactgegevens, en onder een
streep het zakelijke -- copyright, KvK, btw, de privacylink en de credit
van de maker.

## Een vastgelegd besluit dat is omgedraaid

In `PageSectionKey::teBeheren()` stond tot deze module:

> Bij de kop en de voettekst staat de tekst in de code [...] Daar valt
> niets te beheren, **nu niet en later niet**.

Dat was waar zolang er in de voet niets stond dat alleen de eigenaar kan
weten. Sinds er een telefoonnummer, een werkgebied en een KvK-nummer in
staan is het dat niet meer: dat zijn zíjn gegevens, geen ontwerpkeuzes van
ons.

Het tegenwicht stond trouwens al in `config/site.php`, dat dit moment
voorspelde: _"Komt er later een scherm voor -- en dat kan, zodra er meer
van dit soort links bijkomen -- dan verhuist dit naar een eigen tabel met
een eigen module."_

De voettekst blijft wél `vast()`: hij staat onderaan en is niet te
verslepen of uit te zetten.

## Wat de eigenaar beheert, en wat niet

Het uitgangspunt: **het is een voet, niet alles hoort aanpasbaar te zijn.**

| Beheerbaar                    | Vast                                 |
| ----------------------------- | ------------------------------------ |
| De zin onder het merk (nl/en) | De indeling, de kolommen, de kleuren |
| Telefoonnummer                | Het e-mailadres                      |
| Plaats of werkgebied (nl/en)  | De sitemap -- die volgt de indeling  |
| KvK-nummer                    | Het jaartal -- dat telt zichzelf     |
| Btw-nummer                    | De privacylink en de LinkedIn-link   |
|                               | De credit                            |

Het beheerscherm toont dat tweede lijstje ook, in een blok zonder knop.
Zonder die opsomming gaat de eigenaar zoeken naar een plek om zijn
jaartal bij te werken.

### Waarom het e-mailadres vastligt

Het staat in `config/site.php`, en `config/mail.php` valt erop terug voor
de ontvanger van het contactformulier. Beheerbaar maken betekent dat de
eigenaar het adres kan wijzigen waar zijn eigen formulier heen mailt
zonder dat hij dat doorheeft -- en dan verdwijnen er aanvragen zonder dat
iemand het merkt. `EmailAdressenTest` bewaakt de splitsing tussen het
publieke `info@` en het `aarssen@` waarmee wordt ingelogd.

Het staat wél in het scherm, met een slotje ernaast en de reden erbij.

### Waarom het KvK-nummer niet uit de kerngegevens komt

De [kerngegevens](kerngegevens.md) hebben een soort "Bedrijfsgegevens",
en daar kan hetzelfde nummer in staan. Toch leest de voet dat niet uit:
een kerngegeven kan de eigenaar verwijderen, of hij kan de hele strook
uitzetten, en dan zou zijn KvK stilletjes uit de voet verdwijnen zonder
dat hij daarom vroeg. Een nummer dat hij één keer invult op de plek waar
hij het verwacht is voorspelbaarder dan een nummer dat ergens anders
vandaan komt.

De prijs is dat hij het twee keer kan typen als hij het op allebei wil.
Dat is één handeling, één keer, voor een nummer dat nooit verandert.

## De gegevens

Eén tabel **`footer_settings`**, één rij -- hetzelfde patroon als
`site_settings`, `about_settings`, `contact_settings` en
`project_settings`. `FooterSetting::huidige()` geeft de rij, of een
niet-opgeslagen exemplaar zolang die er niet is, zodat een GET niets
wegschrijft.

| Kolom                       | Max | Waarvoor                         |
| --------------------------- | --- | -------------------------------- |
| `tagline_nl` / `tagline_en` | 120 | De zin onder het merk            |
| `phone`                     | 32  | "06 12 34 56 78" of "+31 6 ..."  |
| `region_nl` / `region_en`   | 60  | "Noord-Brabant en omstreken"     |
| `kvk`                       | 20  | Acht cijfers, spaties toegestaan |
| `vat`                       | 20  | Btw-nummer                       |
| `machine_translated_at`     |     | Voor de vertaalknop              |

**Alles mag leeg, en wat leeg is verdwijnt.** `voorDeSite()` geeft alleen
terug wat gevuld is, dus de browser hoeft nergens te controleren óf er
iets staat. Een leeg veld wordt `null` en niet `""`: de site beslist op
`filled()`, en een lege string is gevuld -- dan krijg je een kopje boven
niets.

### De terugval verschilt per veld

| Veld    | Engels leeg                                                                                                                                                                  |
| ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Tagline | **Weglaten.** Deze zin staat direct onder een Engels menu op een Engelse pagina; één Nederlandse regel daartussen leest als een fout                                         |
| Plaats  | **Terugvallen.** Een plaatsnaam is geen zin -- "Noord-Brabant" zegt een Engelse bezoeker net zoveel, en hem die regel onthouden betekent dat hij niet weet waar deze man zit |
| Nummers | Geen vertaling; een KvK-nummer is taalloos                                                                                                                                   |

### Het telefoonnummer, twee vormen

`voorDeSite()` geeft `tekst` en `link` apart terug. De tekst houdt zijn
spaties -- hoe het nummer eruitziet is aan de eigenaar -- en de link houdt
alleen cijfers en een eventueel plusteken over, want een telefoon belt
geen spaties.

## Validatie

- **`kvk` wordt geteld, niet alleen gemeten.** Acht cijfers na het
  strippen van alles wat geen cijfer is. Staan er zeven, dan klopt het
  niet, en dan staat er straks een onwaarheid onder het kopje "KvK" --
  precies het soort bewering die niemand controleert omdat het er
  officieel uitziet. De melding zegt hoeveel cijfers er nú staan; "dit
  veld is ongeldig" laat iemand drie keer tellen.
- **`vat` juist niet.** Een btw-nummer heeft per land een andere vorm, en
  een regel die alleen Nederlandse nummers toelaat is een belofte die we
  niet kunnen waarmaken zodra hij over de grens factureert.
- **`phone`** laat cijfers, spaties, `+`, `-` en haakjes toe. Letters
  niet: dan staat er een woord achter een `tel:`-link en belt er niets.

Het getal 8 staat op het model als `KVK_CIJFERS` en gaat als prop mee naar
het scherm, zodat de teller onder het veld niet kan goedkeuren wat de
server afkeurt.

## Het beheerscherm

`Website → Voettekst`, met drie vensters -- één per blok dat de bezoeker
ziet:

| Venster             | Velden                             | Vertaalknop |
| ------------------- | ---------------------------------- | ----------- |
| Tekst onder je logo | `tagline_nl` / `tagline_en`        | ja          |
| Contactgegevens     | `phone`, `region_nl` / `region_en` | ja          |
| Bedrijfsgegevens    | `kvk`, `vat`                       | nee         |

**Drie vensters betekent drie eindpunten, en geen gedeelde `update()`.**
Dat is de les die bij [Over mij](over-mij.md) is opgeschreven: daar zat
eerst één `update()` achter twee vensters, en bewaarde je het ene dan
gingen de velden van het andere als leeg mee en werden ze gewist -- zonder
melding. `FooterCrudTest::test_one_dialog_does_not_wipe_the_fields_of_another`
bewijst dat dat hier niet kan.

**Geen "Kop erboven".** Elke andere module heeft die knop, want elke
andere module is een sectie met een titel erboven. Een voet heeft geen
kop; `BewaartKoptekst` wordt hier niet gebruikt en `SectionHeading`
krijgt geen arm voor dit onderdeel.

## Hoe de gegevens op elke pagina komen

De voet staat in `PublicLayout.vue` en dus op alle zeven publieke
pagina's. De gegevens gaan **per controller mee**, naast de bestaande
regel `'navigation' => ...`.

Niet als gedeelde prop in `HandleInertiaRequests`: dan betaalt elk
portaalverzoek voor een query die alleen de publieke site nodig heeft.
Dezelfde afweging die `App\Support\Page\Navigatie` al maakte.

Zeven plekken is zeven kansen om het te vergeten, dus daar staat
`FooterPublishingTest::test_every_public_controller_passes_the_footer`
tegenover. Die leest de bestanden als tekst -- zoals `AppSidebarTest` en
`PrivacyPageTest` ook doen -- en valt om zodra een achtste publieke
pagina zijn eerste regel tekent.

**Het e-mailadres gaat wél gedeeld mee**, naast `eigenaar` en `linkedin`.
Het is een configwaarde, dus het kost geen query. Het stond eerst alleen
op de drie pagina's die het tonen; sinds het ook in de voet staat is dat
elke pagina, en dan is per-pagina doorgeven drie plekken om het te
vergeten in plaats van een besparing.

## De vorm op de pagina

Drie kolommen vanaf `48rem`, daaronder onder elkaar, en een balk onder een
streep voor het zakelijke.

**De middelste kolom is de breedste, en de lijst erin loopt in twee
rijtjes.** Dat is geen opsmuk maar een maatprobleem: de sitemap volgt de
indeling van de eigenaar, en bij elf onderdelen werd die kolom elf regels
lang naast een contactkolom van drie. Dan is de voet bijna twaalf regels
hoog met twee kolommen die na een kwart ophouden.

Het gebeurt met kolomstroom (`columns: 7rem 2`) en niet met een raster, en
dat is de reden dat het zonder onderhoud werkt: hoeveel links er staan
weten wij niet. Bij elf wordt het zes en vijf, bij zeven vier en drie, en
bij drie blijft het één kolom -- zonder dat er ergens een getal door
tweeën wordt gedeeld. De `7rem` is de ondergrens per kolom: past dat er
niet twee keer in, dan maakt de browser er vanzelf één van in plaats van
twee kolommen waarin "Certificaten" doormidden breekt.

**Het merkteken staat erbij**, via hetzelfde `AppLogoIcon` als de kop --
iets kleiner (1,5rem tegen 1,75rem) en zonder de zweef-animatie die
`brand-merk-teken` wel heeft. Daar is het merk een link naar de
voorpagina en hoort het te reageren; hier staat het er alleen om te zeggen
van wie deze site is.

> Het pad naar het bestand staat in `AppLogoIcon.vue` en niet in de voet,
> dus `LandingBeeldTest` krijgt er geen `/images/...` bij om te
> controleren. Dat houden we zo.

## De credit

> Gemaakt door Kelvin Aarssen

Klein, gedempt, geen link, rechts in de onderste balk. **Hardgecodeerd in
`SiteFooter.vue`** en niet in `config/` of in `footer_settings`: een
configwaarde is een env-waarde en een instelling is een schuifje, en
allebei maken er iets van dat uitgezet kan worden -- en dan is het geen
credit meer.

**Let op de naamsverwarring.** `page.props.eigenaar` bevat _Erik_ Aarssen,
de eigenaar van deze site. _Kelvin_ Aarssen heeft hem gebouwd. Dat scheelt
één letter en het is niet dezelfde persoon; die prop mag hier dus niet
worden gebruikt. Alleen het label "Gemaakt door" gaat door `$t()` -- een
naam vertaal je niet.

## De animatie

Twee dingen, allebei zuinig:

- **De kolommen komen op met `data-reveal`**, de gedeelde reveal uit
  `PublicLayout`. Geen nieuwe code, dezelfde beweging als de rest van de
  site.
- **De accentlijn bovenin groeit vanuit het midden naar buiten** zodra de
  voet in beeld komt. Dat leest als een afsluiting -- een streep die onder
  het verhaal wordt gezet -- en niet als nóg een blok dat komt opzetten.

De lijn heeft een eigen klasse `.brand-footer-lijn` en gebruikt niet de
bestaande `brand-rule`, hoewel hij er hetzelfde uitziet: die utility
wordt ook door `Error.vue` en `WelcomeDialog.vue` gebruikt, en op een
foutpagina hoort niets op te leven.

`groeiLijn()` in `motion.ts` doet alleen `scaleX` -- compositor-only,
want dit is een element over de volle paginabreedte. Waar de lijn vandaan
groeit (`transform-origin`) staat in de CSS en niet in de animatie: dat is
opmaak.

## Twee tests die niet mogen omvallen

- **`PrivacyPageTest::test_the_footer_links_to_it`** leest `SiteFooter.vue`
  als tekst en eist de letterlijke `privacy()` en `from '@/routes'`. Die
  twee moeten dus precies zo blijven staan.
- **`LandingBeeldTest`** controleert elk `/images/...`-pad in
  `SiteFooter.vue`. Het merk komt via `AppLogoIcon.vue`, dus er staat geen
  pad in de voet zelf -- dat houden we zo.

## Bewust niet gedaan

- **Geen tweede juridische pagina.** Er is alleen `/privacy`, en er is
  bewust geen cookiemelding: de site zet er twee en die zijn strikt
  noodzakelijk. Een "Voorwaarden"-link naar een pagina die niet bestaat is
  erger dan geen link.
- **Geen tweede sociaal netwerk.** LinkedIn is het enige dat bestaat;
  `LinkedinMerk.vue` wordt hergebruikt.
- **Geen volledig adres.** Een plaats of werkgebied past bij iemand die op
  locatie werkt; een straat en huisnummer suggereren een winkel.
- **Geen nieuwsbrief en geen "terug naar boven".** Die horen bij een
  andere soort site.

## Zie ook

- [Pagina-indeling](../pagina-indeling.md)
- [Automatisch vertalen](../automatisch-vertalen.md)
- [Module Kerngegevens](kerngegevens.md)
- [Module LinkedIn](linkedin.md)
