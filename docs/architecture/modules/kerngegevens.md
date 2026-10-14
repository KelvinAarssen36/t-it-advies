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
| Opmaak       | `resources/css/site/kerngegevens.css` (`brand-site-kern-*`)                              |
| Animatie     | `rolbord()` in `resources/js/lib/motion.ts`, het in elkaar schuiven in de sectie zelf    |
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

De waarden staan in de gewone letter (Geist): het zijn zinnen als "Eén
werkdag" en "Op locatie, op afstand of allebei", geen maten. Ze stonden
eerst in Geist Mono, en dat is een kostuum: mono is op deze site voor
maat, tijd en telling. Komt de strook in beeld, dan
**zetten de waarden zich letter voor letter vast**, door elkaar en met een kleine terugstuit -- als een vertrekbord op
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

### Drie bewegingen, in volgorde

De strook komt eerst als geheel op met de gedeelde `revealOnScroll` uit
`PublicLayout`, dan zetten de vakken zich één voor één in de strook
(een kleine schaal en `opacity`, met een stagger van 0,05 seconde in
leesvolgorde), en daarna zetten de waarden zich vast. Zo bouwt de strook
zich op: eerst het vlak met zijn naden, dan de vakken, dan de feiten.

**Eén trigger voor alle vakken, op de strook.** Met een trigger per vak
wacht een vak in de tweede rij op zijn eigen plek terwijl de strook al in
beeld staat, en dan zie je een leeg vak met alleen de naadkleur.
`rolbord()` hangt om dezelfde reden aan de strook en niet aan de losse
waarde. Zie `bouwOp()` in KerngegevensSection.vue.

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

## Beweging: twee kolommen die in elkaar schuiven

Op een breed scherm schuift de linkerkolom van links en de rechterkolom
van rechts naar zijn plek, **gekoppeld aan de scroll** (`scrub`): je schuift
de feiten zelf in elkaar, en omhoogscrollen haalt ze weer uiteen. Elke rij
lager begint een stukje verder weg, zodat het als één waaier dichtgaat.
Alles staat stil voordat de lijst het midden van het scherm bereikt (van
`top 95%` tot `top 52%`), dus je leest nooit iets wat beweegt. Daarna rollen
de waarden zich vast.

De afstand is een deel van de breedte van de lijst zelf (12%, hoogstens
140 pixels) en wordt bij elke verversing opnieuw gemeten. De lijst knipt
horizontaal af (`overflow-x: clip`), zodat de pagina tijdens het schuiven
niet breder wordt dan het scherm.

**Geen `stagger` in die scrub, en dat is een les.** Met een stagger in een
scrub-tween waarvan de afstand bij elke verversing opnieuw wordt berekend,
tekent GSAP de beginstand van een later element pas als zijn beurt komt:
de rechterkolom stond eerst op zijn plek en schoot dan weg. De volgorde zit
daarom in de afstand en niet in de tijd. Zie de valkuilen.

Op een telefoon is er één kolom en dus geen twee kanten; daar komen de
feiten één voor één op (`stapelBinnen`). Onder reduced motion staat alles
er meteen.

### Wat er niet meer is

De strook had een licht dat elke tien seconden door de naden tussen de
vakken trok. Met de naden is ook dat licht weg: een lijst met regels heeft
geen naden om door te schijnen, en het was het enige effect in rust op een
blok dat juist rustig hoort te zijn.

## Hover

Het vak met het teken licht op als je over een rij gaat; dat zegt dat
label, waarde en notitie bij elkaar horen. Er valt niets te klikken, dus er
is geen lift en geen pijltje. Onder reduced motion zonder overgang.

## Lange tekst: afbreken, nooit afknippen

Label en waarde staan er allebei helemaal: in een lijst met eigen
rijhoogtes is daar ruimte voor, en de klant typt ze zelf. **Een waarde
had eerst een afkapping**: die
had eerst ook een afkapping op drie regels, maar het beletselteken van
`line-clamp` viel dan tussen de inline-blokken van het rolbord, als een
los puntje midden in de regel. De klant typt de waarde zelf; dan hoort hij
er ook te staan. Een woord van veertig tekens
breekt af (`overflow-wrap: anywhere`) in plaats van uit het vak te
steken.

**Hier ging het eerst mis.** Het label stond op één regel met
`overflow: hidden`, zonder beletselteken: een lange naam werd halverwege
een letter afgesneden en je zag niet dat er meer stond. En de maskers die
het rolbord om een woord zet zijn inline-blokken; een woord dat langer is
dan het vak paste als één masker nooit op de regel. Die maskers krijgen
nu `max-width: 100%`, zodat het woord erin kan afbreken.

## De vorm op de pagina

**Een lijst in twee kolommen, en geen strook met gelijke vakken.** De strook
die hier eerst stond (vakken met naden van één pixel, vier kolommen breed)
gaf elk vak de hoogte van het langste in zijn rij. Eén lange waarde, en de
drie buren kregen een leeg vlak onder hun tekst; op de echte gegevens was
de helft van het blok lucht. Nu heeft elke rij zijn eigen hoogte.

Per feit:

- links het **teken** in een eigen vak van 2,5rem, groot genoeg om als teken
  te lezen en klein genoeg om de waarde niet weg te drukken;
- het **label** klein en zilver erboven, in dezelfde vorm als een
  cijferlabel elders op de site;
- de **waarde** in de maat van een kopje (`--site-maat-kop`), halfvet: dat
  is het antwoord;
- de **notitie** klein eronder.

Tussen de rijen een haarlijn en geen omlijning eromheen: de lijn zegt waar
het ene feit ophoudt en het volgende begint.

### Hoeveel kolommen

Vanaf `50rem` twee kolommen, daaronder één. Bij een oneven aantal krijgt
het laatste feit de hele breedte (`:last-child:nth-child(odd)`), zodat er
geen halve rij leeg blijft. Eén feit staat altijd alleen, over de volle
breedte. Het aantal komt mee als `data-aantal` op de lijst.

### Op een telefoon

Eén kolom, met dezelfde opbouw per rij. De waarden rollen zodra de lijst
binnenkomt, allemaal tegelijk: `data-rolbord-strook` op de lijst zegt
`rolbord()` dat de lijst de trigger is, en niet elke waarde apart.

**Daar ging het eerst mis.** `rolbord()` begon vast op 85% van de
vensterhoogte, terwijl de lijst zelf op een telefoon al op 115% opkomt
(`beweging()` in motion.ts). Een waarde in de onderste strook van het
scherm stond dan zichtbaar leeg tot je verder scrolde. Nu gebruikt het
rolbord dezelfde grens als `revealOnScroll`, en de lijst als geheel als
trigger.

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
- **Geen koppeling met de voettekst.** Het KvK-nummer staat daar
  inmiddels ook -- zie [voettekst](voettekst.md) -- maar met een eigen
  veld en niet met een verwijzing hierheen. De reden is gedrag en geen
  netheid: een kerngegeven kan de eigenaar verwijderen, of hij kan deze
  hele strook uitzetten, en dan zou zijn KvK stilletjes uit de voet
  verdwijnen zonder dat hij daarom vroeg. De prijs is dat hij het nummer
  twee keer kan typen als hij het op allebei wil; dat is één handeling,
  één keer, voor een nummer dat nooit verandert.

## Zie ook

- [Pagina-indeling](../pagina-indeling.md)
- [Automatisch vertalen](../automatisch-vertalen.md)
- [Back-ups](../../operations/back-ups.md)
