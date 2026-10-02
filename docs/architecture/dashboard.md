# Het dashboard

Het beginscherm van het portaal. De indeling van het standaard
Laravel-dashboard blijft staan -- drie kleine vlakken bovenin, één groot
eronder -- en wordt van binnenuit gevuld.

| Onderdeel              | Waar de klant het beheert | Waar het vandaan komt      |
| ---------------------- | ------------------------- | -------------------------- |
| De **klok**            | Instellingen → Dashboard  | `users.dashboard_timezone` |
| Het **bezoekblok**     | Niets; het telt zichzelf  | `site_day_totals`          |
| De twee andere vlakken | Nog niet                  | `LeegVak.vue`              |

> **Dit document gaat over het portaal en niet over de website.** Alles wat
> hier staat verandert niets aan wat een bezoeker ziet. Dat is ook de reden
> dat de instelling bij de instellingen staat en niet onder Website.

## De klok

In het middelste van de drie kleine vlakken staat een digitale klok met de
datum eronder:
[`DigitaleKlok.vue`](../../resources/js/components/dashboard/DigitaleKlok.vue).

### De browser maakt het op, niet wij

`Intl.DateTimeFormat` krijgt de taal van het portaal en de gekozen tijdzone
en levert de tijd en de datum zoals ze in die regio horen te staan:
_donderdag 1 oktober 2026_ in het Nederlands, met de dag vooraan.

Zelf datums in elkaar zetten met een lijst maandnamen is precies hoe je een
klok krijgt die in het Engels "1 October" zegt waar "October 1" hoort. De
taalcode is `nl-NL` of `en-GB` -- en bewust niet `en-US`, want daar staat de
maand vooraan en de klok op twaalf uur, en dit blijft een Nederlands bedrijf
ook als de eigenaar het portaal in het Engels zet.

### De tijd komt per seconde, maar niet met een interval

Een vaste `setInterval(1000)` loopt langzaam uit de pas met de echte klok:
hij start op een willekeurig moment binnen de seconde en de browser mag hem
laten wachten. Dan ziet de eigenaar een seconde twee keer of helemaal niet.
Daarom rekent de klok elke keer uit hoe lang het nog duurt tot de volgende
hele seconde en zet dan één `setTimeout`.

### De animatie is CSS, en start zichzelf opnieuw

De rest van het project animeert met GSAP, maar dat zit alleen in
[`motion.ts`](../../resources/js/lib/motion.ts) en daarmee in de bundel van
de publieke site. Het voor een klok naar het portaal halen kost 150 kB voor
iets wat CSS prima doet.

Het trucje waarmee dat werkt zit in de `key`: elk cijfer heeft zijn eigen
karakter in zijn `key`, dus zodra het verandert vervangt Vue dat ene element
en loopt de CSS-animatie opnieuw. Een cijfer dat gelijk blijft, beweegt
niet -- dus bij 14:05 verspringt alleen de seconde.

| Wat                    | Hoe                                                      |
| ---------------------- | -------------------------------------------------------- |
| Een cijfer dat wisselt | Rolt van boven in beeld (`brand-klok-rol`)               |
| De dubbele punt        | Pulseert zacht, één seconde per keer (`brand-klok-puls`) |
| Het streepje eronder   | Loopt vol met de minuut (`--minuut`, `scaleX`)           |
| Erachter               | Een gloed in de accentkleur, alleen op een breed scherm  |

De dubbele punt **pulseert** en knippert niet. Knipperen is wat een goedkope
wekker doet; een zachte puls leest als iets dat leeft. Hij doet niet mee met
het rollen omdat zijn karakter nooit verandert, dus Vue vervangt hem nooit
en die animatie loopt gewoon door.

Bij `prefers-reduced-motion` blijft de klok lópen -- dat is informatie en
geen versiering -- maar het rollen, het pulseren en het schuiven gaan eruit.

### Op een telefoon is het een strook

Dit was een uitdrukkelijke eis: op een telefoon staat de klok **bovenaan**
en moet het vlak "echt heel klein" zijn, geen vierkant van een halve
schermhoogte. Daarom is `brand-klok` geschreven vanaf de telefoon en wordt
het pas vanaf 48rem het vlak dat naast de twee andere blokken past.

|        | Telefoon                                  | Vanaf 48rem                       |
| ------ | ----------------------------------------- | --------------------------------- |
| Vorm   | Eén regel: tijd links, korte datum rechts | Gestapeld en gecentreerd          |
| Hoogte | Wat de inhoud nodig heeft                 | `aspect-video`, zoals de buren    |
| Datum  | Kort ("do 1 okt")                         | Lang ("donderdag 1 oktober 2026") |
| Gloed  | Uit                                       | Aan                               |

Dat het vlak bovenaan komt is **één klasse op dat vlak**:
`order-first md:order-none`. De andere twee kleine vlakken en het grote
blok zijn niet aangeraakt -- ze hoeven niet aangepast te worden om de klok
vooraan te krijgen, en wat je niet aanraakt kan ook niet stuk.

## Het bezoekblok

Links van de klok staat wat de website doet:
[`BezoekBlok.vue`](../../resources/js/components/dashboard/BezoekBlok.vue).

**Het grote getal is het totaal van altijd en niet van deze maand.** Dat is
een keuze met een reden: een dashboardblok dat elke maand op nul begint
voelt als iets dat je kwijtraakt. En het hóeft ook niet terug te lopen --
niets ruimt `site_day_totals` op, want daar staan aantallen per dag in die
over niemand in het bijzonder gaan. Zo staat het ook in de
[privacyverklaring](bezoekcijfers.md#de-privacyverklaring): de bezoekcijfers
zelf bewaren we onbeperkt.

> `DashboardVisitBlockTest::test_the_total_is_not_limited_to_a_period` houdt
> dat vast. Komt er ooit een opruimtaak op die tabel, dan valt die test om --
> en dan hoort de tekst in de privacyverklaring in dezelfde wijziging mee te
> veranderen.

Daaronder staat wat er **vandaag** gebeurt. Zonder dat is het een monument
en geen dashboard.

| Wat                         | Waarom                                                         |
| --------------------------- | -------------------------------------------------------------- |
| Het totaal aantal weergaven | Wat de site in zijn hele bestaan heeft gedaan                  |
| De bezoekers van vandaag    | Of er nú iets gebeurt                                          |
| Veertien staafjes           | De vorm van de laatste twee weken, zonder cijfers en zonder as |

### Elk cijfer staat onder zijn eigen opschrift

Boven het grote getal staat **TOTAAL SINDS DE START** en boven de regel
eronder **VANDAAG**, met een streepje ertussen.

Die twee woordjes zijn het verschil tussen een cijfer en een cijfer dat je
begrijpt. Zonder opschrift is "1.284" een getal zonder tijdvak, en dan leest
iemand het als "deze maand" -- precies wat het niet is. Twee cijfers onder
elkaar in hetzelfde vlak zijn bovendien makkelijk te verwisselen, en dan
denkt de eigenaar dat er vandaag duizend mensen langskwamen.

De regel van vandaag noemt de bezoekers én de weergaven ("7 bezoekers · 12
weergaven"), want dat zijn twee verschillende dingen: één bezoeker die drie
pagina's opent is drie weergaven. Het aantal bezoekers staat dik, omdat dat
het cijfer is waar iemand naar zoekt.

**De hele kaart is een link** naar Beheer → Bezoekers, en niet een vlak met
een knop in een hoek: je kijkt ernaar en denkt "hoeveel waren het er deze
week", en dan hoort de kaart je daarheen te brengen. Hij reageert daarom ook
zichtbaar bij het aanwijzen -- een kaart die niets doet als je eroverheen
gaat, lijkt geen link.

**Nog niets gemeten geeft geen nul maar een zin.** Een nul leest als "er komt
niemand", en dat is iets anders dan "we zijn pas begonnen met kijken".

### De opmaak

| Wat                     | Hoe                                                              |
| ----------------------- | ---------------------------------------------------------------- |
| Het totaal              | In de merkgradient, met "weergaven" er gedempt achter            |
| De gloed in de hoek     | `background-image` op de kaart, niet een `::before`              |
| Het aanwijzen           | Rand in de accentkleur, een zachte schaduw en één pixel omhoog   |
| De staafjes             | Groeien bij het openen één voor één omhoog, van oud naar vandaag |
| Het staafje van vandaag | Staat vol; de rest staat op 55% dekking                          |

De gloed zit in `background-image` en niet in een pseudo-element omdat een
absoluut geplaatst `::before` over de tekst heen schildert. Dat is dezelfde
val als bij de balkanimatie op de website, en de oplossing is hier
eenvoudiger: een radiale gradient op de kaart zelf heeft geen laagjes nodig.

Het getal staat in de gradient via een **eigen span** en niet via de hele
regel: `background-clip: text` maakt elke letter in het element doorzichtig,
dus zou "weergaven" mee de kleur in gaan en net zo hard roepen als het
cijfer.

Bij `prefers-reduced-motion` vervallen het groeien van de staafjes en het
omhoogschuiven bij het aanwijzen; de kleuren blijven.

## De lege vlakken

Het derde kleine vlak en het grote blok eronder zijn nog leeg. Daar staat
[`LeegVak.vue`](../../resources/js/components/dashboard/LeegVak.vue): een
gestreepte rand, een gedempt tekentje en één regel.

**Dit verving het diagonale streepjespatroon van de Laravel-starter.** Dat
patroon is een bouwsteiger -- het zegt "hier is de ontwikkelaar nog bezig",
en dat is precies wat de eigenaar niet hoort te zien op het eerste scherm
dat hij elke dag opent. Wat er nu staat leest als ruimte die bewaard is.

Het grote blok heeft ook geen `min-h-[100vh]` meer maar `min-h-56` op een
telefoon. Een leeg vlak van een hele schermhoogte betekende dat je langs
niets moest scrollen om bij de onderkant van je eigen dashboard te komen.

## De instellingen van het dashboard

Een eigen gedeelte onder Instellingen:
[`settings/Dashboard.vue`](../../resources/js/pages/settings/Dashboard.vue),
achter `settings/dashboard`.

> **Het is er met opzet al terwijl er één instelling op staat.** De eigenaar
> vroeg erom in die vorm: een aparte plek voor de instellingen van zijn
> dashboard, omdat er meer bij komt. Zou de tijdzone nu bij "Weergave"
> staan, dan moet alles wat erbij komt daar ook heen -- en dan gaat dat
> scherm over twee dingen.

Daaronder staat de **echte klok** in de keuze die op dat moment in het veld
staat, dus ook voordat je opslaat. Zelfde aanpak als het voorbeeld in het
statistiekvenster: een lijst met tijdzonenamen zegt wat er in de database
staat, een lopende klok zegt hoe laat het daar is.

**Geen bevestigingsvraag.** De vaste bevestigingsstroom van dit project
hangt aan "dit staat direct live op de website"; dit verandert niets aan wat
een bezoeker ziet. Het is een persoonlijke voorkeur, net als licht of
donker.

### Een vaste lijst tijdzones

[`DashboardTimezone`](../../app/Enums/DashboardTimezone.php) heeft vijf
cases, met Nederland als standaard. Een keuzelijst met alle vierhonderd
IANA-zones is onbruikbaar: dan scrol je langs `America/Argentina/Catamarca`
op zoek naar Amsterdam.

**De waarde is de IANA-naam en geen offset in uren.** Dat is geen detail:
`Europe/Amsterdam` weet zelf wanneer de zomertijd ingaat, `+1` niet. Een
klok die een half jaar per jaar een uur mis staat is erger dan geen klok.
`DashboardSettingsTest` controleert daarom van elke case dat
`DateTimeZone` hem kent -- een tikfout als `Asia/Tokio` levert anders een
lijst op die er goed uitziet en een klok die omvalt.

### Waarom het op `users` staat en niet in een eigen tabel

Dit is een persoonlijke voorkeur, net als `locale` en het thema: welke tijd
jíj op jouw dashboard wil zien. Een tabel met sleutel-waarderijen zou
flexibeler zijn, maar levert ongetypeerde tekst op waar de applicatie elke
keer opnieuw over moet nadenken. Komt er een tweede dashboardinstelling, dan
is dat een kolom erbij.

De kolom is `nullable` en heeft **geen standaardwaarde in het schema**. Die
staat in `DashboardTimezone::STANDAARD` en wordt gelezen via
`User::dashboardTijdzone()`. Eén plek, en geen database die zelf
`Europe/Amsterdam` invult en daarmee een tweede bron wordt die stil uit de
pas kan gaan lopen.

## Wat waar staat

| Bestand                                                                                              | Wat het doet                                             |
| ---------------------------------------------------------------------------------------------------- | -------------------------------------------------------- |
| [`DigitaleKlok.vue`](../../resources/js/components/dashboard/DigitaleKlok.vue)                       | De klok zelf: de tijd, de datum en de animatie.          |
| [`BezoekBlok.vue`](../../resources/js/components/dashboard/BezoekBlok.vue)                           | Wat de website doet, met de weg naar het hele overzicht. |
| [`LeegVak.vue`](../../resources/js/components/dashboard/LeegVak.vue)                                 | Een vlak waar nog niets in zit.                          |
| [`Dashboard.vue`](../../resources/js/pages/Dashboard.vue)                                            | Het beginscherm, met de klok in het middelste vlak.      |
| [`DashboardController`](../../app/Http/Controllers/DashboardController.php)                          | Stuurt de gekozen zone en de bezoekcijfers mee.          |
| [`DashboardVisitBlockTest`](../../tests/Feature/DashboardVisitBlockTest.php)                         | Dat het totaal nooit terugloopt en vandaag apart staat.  |
| [`DashboardTimezone`](../../app/Enums/DashboardTimezone.php)                                         | De vijf zones en hun labels.                             |
| [`DashboardSettingsController`](../../app/Http/Controllers/Settings/DashboardSettingsController.php) | Het instellingenscherm en de opslag.                     |
| [`settings/Dashboard.vue`](../../resources/js/pages/settings/Dashboard.vue)                          | Dat scherm, met het voorbeeld eronder.                   |
| [`DashboardSettingsTest`](../../tests/Feature/Settings/DashboardSettingsTest.php)                    | De standaardzone, de validatie en dat elke zone bestaat. |

## Wat er bewust niet in zit

- **Geen tweede klok naast de eerste.** De zone is één keuze en geen lijst;
  wil de eigenaar twee tijden naast elkaar, dan is dat een vlak erbij en
  geen tweede veld in dezelfde instelling.
- **Geen analoge klok.** Afgesproken was een digitale.
- **Geen vrije invoer van een tijdzone.** Zie hierboven: de korte lijst is
  het hele idee.
- **De andere twee vlakken zijn nog leeg.** Dat blijft zo tot er iets is
  afgesproken om erin te zetten; een vlak vullen met een getal dat niemand
  heeft gevraagd maakt het dashboard niet nuttiger.
