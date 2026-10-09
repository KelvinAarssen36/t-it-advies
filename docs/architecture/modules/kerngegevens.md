# Module Kerngegevens

De website vertelt wie de eigenaar is, wat hij doet, hoe hij werkt en wat
hij heeft gedaan. Wat er niet stond is het praktische: **kun ik met deze
man in zee, en hoe?** Beschikbaarheid, werkgebied, of hij op locatie
werkt, hoe snel hij reageert. Dat zijn de vragen die een bezoeker stelt
vlak voordat hij belt, en die moest hij tot nu toe stellen in plaats van
lezen.

Dit is een strook op de landingspagina met die feiten, door de klant zelf
beheerd, vertaalbaar en versleepbaar.

## De vorm in het kort

|              |                                                                                          |
| ------------ | ---------------------------------------------------------------------------------------- |
| Tabel        | `core_facts`                                                                             |
| Model        | `App\Models\CoreFact` (`MAXIMUM = 8`)                                                    |
| Soorten      | `App\Enums\CoreFactIcon` -- acht stuks                                                   |
| Controller   | `App\Http\Controllers\Website\CoreFactController`                                        |
| Beheerscherm | `website/Kerngegevens.vue` + `KerngegevenDialoog.vue` + `KerngegevenVolgordeDialoog.vue` |
| Op de site   | `site/sections/KerngegevensSection.vue` + `site/KerngegevenIcoon.vue`                    |
| Animatie     | `rolbord()` in `resources/js/lib/motion.ts`                                              |
| Tests        | `CoreFactCrudTest`, `CoreFactPublishingTest`, `CoreFactTranslationTest`                  |

## De grens met Statistieken

Dit is de reden dat het een eigen module is en geen soort statistiek.

|                      | Statistiek                       | Kerngegeven            |
| -------------------- | -------------------------------- | ---------------------- |
| Het antwoord         | Een getal                        | Een zin                |
| Wat het beeld draagt | De meter -- balk, ring of teller | De waarde zelf         |
| "In overleg"         | Past niet                        | Is een geldig antwoord |

Een statistiek is een getal met een meter eromheen; het beeld is de
boodschap, en het label is het bijschrift. Bij een kerngegeven is het
precies andersom: **de waarde staat groot en het label klein**, want
"Vanaf januari" is wat de bezoeker zoekt en "Beschikbaar" zegt alleen waar
hij kijkt.

Een meter om "twee tot drie dagen per week" heen zou bovendien doen alsof
er iets gemeten is. Dat is niet zo.

## De acht soorten

`CoreFactIcon` is meer dan een pictogramkeuze: het is **de inhoudsopgave
van de module**. De klant vroeg ons te bedenken wat erin hoort, en dit is
waar dat antwoord staat.

```
beschikbaarheid  werkgebied  werkvorm  reactietijd
talen            samenwerking  bedrijf  voorwaarden
```

Elk soort heeft een `label()` en een `voorbeeld()`. Dat voorbeeld staat op
het lege beheerscherm en wordt **nooit opgeslagen**; het beheerscherm
gebruikt het label wél als voorstel zodra je een soort kiest.

> **Er wordt niets geseed.** Wij verzinnen de soorten, de eigenaar vult de
> feiten. Zou "KvK 12345678" meekomen in `DatabaseSeeder`, dan staat er
> een onwaarheid op zijn live site tot hij hem toevallig opmerkt.
>
> Verzonnen data om de schermen mee te bekijken staat wél klaar in
> `VoorbeeldDataSeeder`, en die weigert buiten `local` en `testing`.

## De terugval tussen de talen

Beslist op het model en niet in Vue, zoals overal in dit project. Hij
verschilt per veld:

| Veld    | Engels leeg                                               |
| ------- | --------------------------------------------------------- |
| Label   | Terugvallen -- een waarde zonder naam zegt niets          |
| Waarde  | Terugvallen -- een KvK-nummer is in beide talen hetzelfde |
| Notitie | Weglaten -- de strook oogt zonder toelichting net zo goed |

Die laatste is dezelfde afweging als bij de notitie onder een statistiek:
één Nederlandse zin tussen Engelse tekst valt meer op dan een ontbrekende
toelichting.

### De vertaalknop

`TranslateController` kreeg er één veld bij: **`waarde_nl`** (max 80).
`label_nl` en `notitie_nl` stonden er al voor de statistieken en worden
hergebruikt -- dezelfde betekenis, dezelfde lengte.

> **Dit is de plek waar het bij de werkwijze stil misging.** `validate()`
> gooit weg wat niet in die witte lijst staat, dus een vergeten veld wordt
> zonder enige melding niet vertaald. `CoreFactTranslationTest` houdt de
> lijst van velden die het venster meestuurt naast wat er terugkomt; voeg
> je er een veld bij, dan valt die test om.

## De animatie: een rolbord

Komt de strook in beeld, dan **zetten de waarden zich letter voor letter
vast**, door elkaar en met een kleine terugstuit -- als een vertrekbord op
een station dat gaat staan.

Dat past bij wat er staat: een kerngegeven is een feit dat vastligt, en
een bord dat zich zet leest als "dit is hoe het is". Het is bovendien het
eerste effect in dit project dat op **letterniveau** werkt; `splitReveal`
doet regels, `countUp` getallen, `drawTimeline` een lijn.

`rolbord()` staat naast `splitReveal()` in `motion.ts` en deelt er de
opbouw mee. Vijf dingen die er bewust zo in zitten:

- **`mask: 'chars'` doet het maskeren, niet wij.** Zelf iets eromheen
  bouwen werkt tot de tekst anders afbreekt.
- **De tekst wordt nooit vervangen.** Er rolt geen willekeurige reeks
  tekens doorheen zoals bij een echt rolbord; alleen de échte letters
  bewegen. Een animatie die de inhoud tijdelijk vervalst is een animatie
  die een schermlezer voorliegt.
- **SplitText zet zelf een `aria-label`** met de hele tekst. Een
  schermlezer leest "Vanaf januari" en niet "V, a, n, a, f". Niet
  weghalen.
- **Alleen `translate` en `opacity`.** Compositor-only; alles wat `height`
  of `padding` aanraakt kost een layout per frame -- de les van de
  slideshow.
- **Boven de 24 tekens gaat het per woord in plaats van per letter.**
  Tachtig losse letters is geen bord meer maar ruis, en het zijn tachtig
  elementen om te animeren. Het blijft wel hetzelfde bord met dezelfde
  terugstuit, alleen grover. Dat is bewust geen platte schuif: die zou
  "Op locatie, op afstand of allebei" tot een tweede soort maken naast
  "Vanaf januari", terwijl ze naast elkaar op één bord horen te staan.

Bij `prefers-reduced-motion` staat alles meteen goed.

### Twee bewegingen, in volgorde

De strook komt eerst als geheel op met de gedeelde `revealOnScroll` uit
`PublicLayout` -- dezelfde beweging als de projectkaarten en de
werkwijze -- en daarna pas zetten de waarden zich vast.

Die volgorde is er niet vanzelf. `rolbord()` stond op `top 90%` en de
reveal op `top 85%`, en 90% ligt lager in beeld: de letters rolden dus
terwijl het vak eronder nog doorzichtig was, en dan zie je het effect
niet. Nu beginnen ze allebei op 85% en wacht het rolbord 0,3 seconde.

De reveal zit op de strook en niet op de losse vakken. Per vak zouden het
acht aparte fades worden, en dan is het geen strook meer maar een rij
tegels -- precies wat deze module niet is.

### Als je al naar de strook kijkt

Beide bewegingen hebben een pad voor "staat al in beeld". `alInBeeld()`
kijkt bij het aanhaken of het element al binnen 85% van de vensterhoogte
staat; zo ja, dan komt er geen scroll-trigger aan te pas en speelt de
beweging meteen. Zonder dat pad komt de trigger nooit meer langs en blijft
de strook staan waar hij staat -- onzichtbaar, want de markup draagt
`opacity-0`. Dat is dezelfde valkuil als bij elke andere reveal in
`motion.ts` en daarom staat hij daar overal.

## In rust: stroom door het bord

De binnenkomst speelt één keer, en daarna stond de strook stil. Nu trekt
er elke negen seconden een smalle cyaan baan achter de vakken langs.

De vakken zijn ondoorzichtig, dus je ziet die baan alleen in de **naden
van 1px ertussen**: de scheidingslijntjes lichten één voor één op, als
stroom die door het bord loopt. Die naden zijn precies wat deze strook
onderscheidt van elke andere vorm op de site, en dit is het enige effect
dat ze gebruikt.

**Waarom er niet af en toe een letter omklapt**, wat voor een vertrekbord
voor de hand had gelegen: tekst die uit zichzelf verspringt trekt het oog
weg van de regel die je net aan het lezen bent, en een bezoeker leest dat
als een storing en niet als een bord. Beweging in rust mag opvallen zodra
je ernaar kijkt, niet terwijl je iets anders leest.

**En geen `brand-glans`.** Die klasse staat buiten `@layer components` en
hoort bij het formaat van de actieknop; de uitleg erbij zegt met zoveel
woorden dat je er een eigen moet schrijven, nadat hij één keer op de
veelgestelde vragen is geplakt en daar een wit vlak voorbij liet razen.
`brand-kerngegevens-stroom` is die eigen.

De baan is een `::before` over de volle strook met alleen `translate` in
de keyframes -- compositor-only, geen herberekening van de opmaak per
frame. Hij steekt in 45% van de negen seconden over en wacht de rest:
zonder die pauze is het geen bord dat af en toe overklikt maar een lampje
dat staat te knipperen.

## Hover

Het vak reageert, maar nodigt niet uit tot klikken: het pictogram licht
op en groeit een fractie, het label komt uit zijn gedempte grijs en het
vak krijgt een zweem cyaan. Dezelfde taal als elke andere hover op de
site.

**Geen lift van 2px** zoals bij de project- en dienstkaarten. Die kaarten
staan los; dit vak zit vast in een strook. Til je er één op, dan trek je
de naad met zijn buren open en zie je de achtergrond staan waar het
scheidingslijntje hoort. En een lift belooft dat er iets te klikken valt,
terwijl hier niets te klikken valt.

Bij `prefers-reduced-motion` blijven de baan en het groeien weg. De kleur
bij hover blijft: dat is geen beweging.

## De vorm op de pagina

Eén omlijnd blok met scheidingslijntjes, hoogstens vier kolommen vanaf
`64rem`, twee vanaf `40rem`, daaronder één. De lijntjes zijn de
achtergrond die door de naden schijnt (`gap: 1px` plus een
achtergrondkleur): met een rand per vak krijg je op elke naad twee lijnen
op elkaar, en die zijn zichtbaar dikker dan de buitenrand.

### Waarom het flex is en geen raster

Dat achtergrondtrucje is meteen de reden dat een raster hier niet kan.
Vier vaste kolommen houden bij zeven vakken een lege plek over, en door
die plek schijnt de achtergrond van de strook: een grijs blok in de hoek
waar niets staat. Dat gebeurt bij elk aantal dat geen veelvoud is van het
aantal kolommen -- bij vijf, zes en zeven van de acht die er kunnen zijn,
en op twee kolommen bij elk oneven aantal.

Flex met `flex-grow` kent dat gat niet: wat er op de laatste rij
overblijft verdeelt de breedte onder elkaar. Zeven vakken worden vier
plus drie, en die drie zijn samen even breed als de vier erboven.

De breedte van een vak is `calc((100% - var(--kolommen) * 1px) /
var(--kolommen))`: een kolombreedte min de naden ertussen, met een naad
extra als speling. Valt die berekening ook maar een fractie te breed uit,
dan klapt het laatste vak naar de volgende rij en staat er een rij van
drie waar vier hoort. `flex-grow` haalt die speling er meteen weer bij.

### Hoeveel kolommen

Op een breed scherm hangt het aantal kolommen af van hoeveel gegevens er
staan. `KerngegevensSection.vue` geeft dat mee als `--kolommen-breed`,
want alleen daar is het bekend:

| Aantal  | Kolommen | Rijen         |
| ------- | -------- | ------------- |
| 1 t/m 4 | evenveel | één volle rij |
| 5       | 3        | 3 + 2         |
| 6       | 3        | 3 + 3         |
| 7       | 4        | 4 + 3         |
| 8       | 4        | 4 + 4         |

Vier plus één ziet eruit alsof er iets is weggevallen; drie plus twee
leest als twee volle rijen. Gaten zijn het niet -- die vangt `flex-grow`
hoe dan ook af -- dit gaat puur over de rust.

### Op een telefoon

Onder `40rem` staat alles onder elkaar, met minder ruimte in het vak en
de waarde een maat kleiner (1,125rem in plaats van 1,25rem). Op volle
grootte breekt "Op locatie, op afstand of allebei" daar over drie regels
en wordt het vak hoger dan breed.

Acht gegevens onder elkaar is een lange strook op een telefoon, en dat is
hier geen bezwaar: elke waarde heeft zijn eigen scroll-trigger, dus elk
vak zet zich vast op het moment dat je het bereikt. Zou de hele strook in
één keer afgaan, dan miste je er zeven.

**Geen losse tegels.** Die staan al bij de statistieken, en kaarten bij de
diensten. Een strook leest als één mededeling in plaats van als acht
losse -- en dat is wat deze gegevens samen zijn.

Op de pagina is het een `<dl>`: dit zijn termen met hun omschrijving, en
een schermlezer kondigt hem dan ook zo aan.

## Het maximum van acht

Afgevangen in `CoreFactController::store()` en niet in de FormRequest: het
gaat niet over de geldigheid van wat er is ingevuld maar over hoeveel er
al staan, en een foutmelding onder een veld zou daar niets over zeggen. De
eigenaar krijgt een melding.

Op het beheerscherm **verdwijnt de knop** zodra er acht staan, met de
reden erbij. Een knop die je mag indrukken hoort iets te doen.

Er kunnen er dus nooit negen zijn, en `CoreFactCrudTest` bewijst dat: die
maakt er acht aan, probeert een negende en eist dat de teller op acht
blijft staan. Zou de grens ooit omhoog gaan, dan hoeft de opmaak daar
niet voor om -- de strook vult elke laatste rij vanzelf, bij negen net
zo goed als bij zeven. Wat niet meeschaalt is het lezen, en dat is nu
juist de reden dat die grens er staat.

## Waar het onderdeel staat

`standaardPositie()` is **1, direct na de kop**; alles daaronder is één
opgeschoven. Een feitenstrook hoort tegen de header aan: de bezoeker heeft
net gelezen wie hij is en krijgt meteen de praktische kant.

> **Op een bestaande installatie komt het onderdeel onderaan te staan.**
> Dat is geen fout: `PageSectionSeeder::plekVoor()` geeft een nieuw
> onderdeel de laatste plek zodra de tabel al gevuld is, want de
> standaardplek zou botsen met een bezette plek. De eigenaar sleept hem op
> Indeling naar boven.

## In de back-up

`core_facts` staat in `Inhoudsregister::TABELLEN`. Zonder dat zou de
module niet meegaan in een back-up, en dan zet de klant iets terug en zijn
zijn kerngegevens weg.

Dat wordt afgedwongen:
`BackupMakenTest::test_every_table_in_the_database_is_classified` valt om
zodra er een tabel bestaat die niet in het register en niet op de verboden
lijst staat.

## Bewust niet gedaan

- **Geen eigen pagina.** Dit is een strook op de landing, zoals de
  statistieken. Een `/kerngegevens` zou een pagina zijn met acht regels
  erop.
- **Geen groepen.** Statistieken heeft ze omdat daar twintig cijfers in
  kunnen; bij acht feiten is een kopje erboven ruis.
- **Geen eigen soort per veld** (datum, bedrag, keuzelijst). De waarde is
  vrije tekst, want "in overleg" en "2 tot 3 dagen" zijn geldige
  antwoorden en in geen enkel vast formaat te persen.
- **Geen koppeling met de voettekst.** Het KvK-nummer hoort daar misschien
  ook thuis, maar dat is een tweede plek met een eigen reden; die
  vermengen maakt van deze module een instellingenscherm.

## Zie ook

- [Pagina-indeling](../pagina-indeling.md)
- [Automatisch vertalen](../automatisch-vertalen.md)
- [Back-ups](../../operations/back-ups.md)
