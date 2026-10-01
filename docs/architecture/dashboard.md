# Het dashboard

Het beginscherm van het portaal. De indeling van het standaard
Laravel-dashboard blijft staan -- drie kleine vlakken bovenin, één groot
eronder -- en wordt van binnenuit gevuld.

| Onderdeel              | Waar de klant het beheert | Waar het vandaan komt      |
| ---------------------- | ------------------------- | -------------------------- |
| De **klok**            | Instellingen → Dashboard  | `users.dashboard_timezone` |
| De drie andere vlakken | Nog niet                  | `PlaceholderPattern.vue`   |

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
| [`Dashboard.vue`](../../resources/js/pages/Dashboard.vue)                                            | Het beginscherm, met de klok in het middelste vlak.      |
| [`DashboardController`](../../app/Http/Controllers/DashboardController.php)                          | Stuurt de gekozen zone mee.                              |
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
- **De andere drie vlakken zijn nog leeg.** Dat blijft zo tot er iets is
  afgesproken om erin te zetten; een vlak vullen met een getal dat niemand
  heeft gevraagd maakt het dashboard niet nuttiger.
