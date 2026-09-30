# Automatisch vertalen

De klant voert zijn inhoud in het Nederlands in en klikt door naar een
tweede stap: "en nu het Engels". Daar staat één knop die de tekst voor hem
vertaalt.

**Dit is hulp, geen vervanging.** Wat eruit komt is een voorstel dat hij
naleest en aanpast. Het uitgangspunt blijft dat de klant zijn teksten twee
keer invoert -- dat leest netter dan een machinevertaling -- en deze knop is
er voor de keren dat hij daar geen zin in heeft.

## Hoe het loopt

1. De klant vult stap 1 in en gaat naar stap 2.
2. Hij drukt op **Vertaal automatisch**. Het formulier stuurt de
   Nederlandse tekst naar de server.
3. De server vraagt de vertaling op en stuurt hem terug als flits-prop,
   hetzelfde mechanisme als de [meldingen](meldingen.md).
4. Het formulier vult de Engelse velden. **Er is nog niets opgeslagen.**
5. De klant leest na, past aan wat hij wil, en drukt op Opslaan.

**Stap 4 is de kern.** Zou de knop meteen wegschrijven, dan staat er
automatisch vertaald Engels op de website voordat iemand het heeft gezien.
Nu is er altijd een mens tussen.

### Twee maten: een grote knop en een klein knopje

De grote knop hierboven hoort bij een blok tekst -- een functietitel met
een beschrijving, of de titel boven de tijdlijn met de zin eronder. Bij de
**cijfers** boven de tijdlijn staat er een tweede vorm: een klein knopje
per cijfer, naast het Engelse woordveld.

Dat is dezelfde route met een eigen veld (`woord_nl`), en drie dingen zijn
er bewust anders:

- **Geen bevestiging vooraf.** De grote knop waarschuwt eerst als er
  Engels staat dat hij zou overschrijven, want daar kan een zorgvuldig
  geschreven alinea verdwijnen. Hier gaat het om één woord, en dat typt de
  eigenaar sneller terug dan hij een venster wegklikt.
- **Geen merkje "automatisch vertaald".** Dat merkje gaat over de titel en
  de zin eronder. Zou een vertaald woord het ook zetten, dan staat er
  "automatisch vertaald" bij tekst die de eigenaar zelf heeft geschreven.
- **Het venster blijft open**, en het knopje verschijnt pas als er
  Nederlands staat om te vertalen.

Welk cijfer om de vertaling vroeg weet de server niet, en hoeft hij niet
te weten: één woord erin, één woord eruit. Het venster heeft de knop zelf
ingedrukt en onthoudt de rest. Er kan er maar één tegelijk lopen, want de
knoppen staan ondertussen uit.

De **expertisepunten** van een dienst werken precies zo, met het veld
`punt_nl`. Ook daar staat een rij korte teksten onder elkaar, en één knop
die er acht tegelijk overschrijft is te grof.

### De velden die de route kent

| Veld             | Lengte | Wie het stuurt                                       |
| ---------------- | ------ | ---------------------------------------------------- |
| `role_nl`        | 120    | Eén ervaring                                         |
| `location_nl`    | 120    | Eén ervaring                                         |
| `description_nl` | 5000   | Een ervaring, en de uitgebreide tekst van een dienst |
| `title_nl`       | 120    | De koppen boven een blok, en de titel van een dienst |
| `intro_nl`       | 300    | De zin onder een kop                                 |
| `eyebrow_nl`     | 60     | Het opschrift boven een kop                          |
| `summary_nl`     | 300    | De korte tekst op een dienstkaart                    |
| `woord_nl`       | 40     | Het woord onder één cijfer                           |
| `punt_nl`        | 60     | Eén expertisepunt                                    |

Ze zijn allemaal `nullable`, dus elk scherm stuurt alleen wat het heeft.
Komt er een module bij met een nieuw soort tekst, dan komt daar een veld
bij -- géén tweede route.

> **De regel voor een volgende module:** één route, meerdere velden. Een
> tweede route ernaast zou dezelfde begrenzing, dezelfde foutafhandeling
> en dezelfde sleutelvertaling moeten herhalen, en dat is precies waar
> twee dingen uiteen gaan lopen.

## Het merkje "automatisch vertaald"

In de database staat `machine_translated_at`. Die wordt gezet als de klant
de knop gebruikte **en daarna niets meer in de Engelse velden wijzigde**;
het formulier houdt daarvoor bij wat de dienst voorstelde en vergelijkt dat
bij het opslaan.

Het overzicht toont er een merkje bij. Zo ziet de klant een half jaar later
nog welke teksten hij zelf heeft geschreven en welke hij nog moet nalopen.
Zonder die vlag is een machinevertaling niet meer van een eigen tekst te
onderscheiden, en dan loop je ze allemaal opnieuw na of geen enkele.

## Welke dienst, en waarom

**MyMemory**, via een gewone HTTP-aanroep. De afweging staat in het
[beslislogboek](../decisions/README.md).

**Er is geen account en geen sleutel voor nodig.** Dat was de
doorslaggevende eis: een knop die pas werkt nadat iemand zich ergens heeft
aangemeld, is voor de klant geen knop. De dienst staat gewoon open.

**Het tegoed** is anoniem 5.000 tekens per dag per IP-adres. Zet je een
adres in `TRANSLATE_EMAIL`, dan wordt dat 50.000 -- dat adres gaat als
parameter mee en er hoort géén aanmelding bij. Voor een site van deze
omvang is zelfs het anonieme tegoed ruim.

**De kwaliteit haalt het niet bij een betaalde dienst**, en dat is een
bewuste ruil. Wat hier uitkomt is een startpunt: negen van de tien keer
herschrijft de klant het toch in eigen woorden, en dan is "goed genoeg om
mee te beginnen" precies genoeg. Wil je ooit beter, dan is DeepL de
voor de hand liggende stap -- die vraagt wel een account, en hij past in
hetzelfde contract.

### Drie dingen die deze dienst eigen zijn

**Er past hoogstens 500 tekens in één verzoek.** Daarboven komt er geen
vertaling terug maar een foutcode. Dat was goed voor een functietitel en
een plaats, maar niet voor een beschrijving van een paar alinea's -- en
het gaf precies het beeld "soms doet die knop het en soms niet". Lange
tekst wordt daarom opgeknipt: eerst per **regel**, want een lege regel
scheidt alinea's en die structuur wil je terugzien; past een regel niet,
dan per **zin**; past een zin nog steeds niet, dan per **woord**. Daarna
worden de stukken weer aan elkaar gezet. Zie `stukken()` in
[`MyMemoryVertaler`](../../app/Support/Translation/MyMemoryVertaler.php),
met tests erop.

Twee gevolgen om te kennen: een lange beschrijving kost **meer verzoeken
en meer tegoed** (zet `TRANSLATE_EMAIL` en je gaat van 5.000 naar 50.000
tekens per dag), en zinnen die binnen één regel zijn opgeknipt komen elk
op een eigen regel te staan. Dat laatste is een schoonheidsfoutje dat de
klant zo wegpoetst; het alternatief was geen vertaling.

Het derde gevolg zit in het scherm: **het duurt langer.** De stukken gaan
één voor één de deur uit, dus een beschrijving van een paar alinea's kan
tientallen seconden kosten. Het laadschermpje telt daarom de seconden,
zegt in hoeveel stukjes de tekst uiteenvalt, en laat een balkje lopen. Na
twintig seconden verandert de tekst in "het duurt langer dan verwacht".
Een laadschermpje dat de hele tijd hetzelfde zegt, leest bij zo'n wachttijd
namelijk als vastgelopen.

**Het vertaalgeheugen kan onzin teruggeven.** MyMemory is deels een
geheugen dat mensen vullen; voor een korte term als "Beheer" komt daar
zomaar een zin uit iemands oude handleiding uit. Daarom pakt
[`MyMemoryVertaler`](../../app/Support/Translation/MyMemoryVertaler.php)
de **machinevertaling** als die in het antwoord zit, en pas daarna wat de
dienst zelf als beste aanmerkt. Minder mooi soms, maar voorspelbaar -- en
voorspelbaar is hier meer waard.

**Een leeg tegoed komt terug als een 403 ín het antwoord**, niet als een
HTTP-fout. Zonder die controle zou de klant de zin "YOU USED ALL AVAILABLE
FREE TRANSLATIONS FOR TODAY" als vertaling in zijn veld krijgen. Daar staat
een test op.

Verder worden HTML-entiteiten gedecodeerd -- een apostrof komt binnen als
`&#39;` -- en gaat er één verzoek per stuk tekst uit, want de dienst kent
geen bundel.

## Eerst vragen voordat er iets wordt overschreven

Staat er al Engels dat de klant **zelf** heeft getypt of bijgeschaafd, dan
vraagt de knop eerst of dat weg mag. Er is geen ongedaan maken in dit
formulier; een vertaling die je net met de hand hebt bijgewerkt kwijtraken
aan een knop die je per ongeluk raakt, is niet iets waar je van terugkomt.

Wat de dienst zojuist zelf heeft neergezet telt niet mee: dat nog eens
laten vertalen kost niets, en dan is de vraag alleen maar in de weg. Dat
onderscheid is dezelfde `isNogAutomatisch` die ook het merkje bepaalt.

**Het merkje blijft staan bij een wijziging die het Engels met rust
laat.** Dat klinkt vanzelfsprekend en was het niet: het bewerkvenster
begon met een schone lei, dus verbeterde je een typefout in de
Nederlandse tekst, dan ging het merkje eraf terwijl niemand het Engels
had nagelopen. Het formulier legt nu bij het openen vast hoe het Engels
erbij stond, en vergelijkt daarmee.

## Als er iets misgaat

De knop hoort nooit een fout op het scherm van de klant op te leveren, en
al helemaal geen half resultaat. Vier gevallen, elk met een eigen zin die
zegt **wat hij nu kan doen**:

| Reden             | Wat de klant leest                                             |
| ----------------- | -------------------------------------------------------------- |
| Tegoed op         | "Vul de Engelse tekst zelf in, of probeer het volgende maand." |
| Geen verbinding   | "Probeer het zo nog eens, of vul de Engelse tekst zelf in."    |
| Sleutel geweigerd | "Neem even contact op; je kunt het intussen zelf invullen."    |
| Iets anders       | "Het lukt nu niet. Vul het zelf in, of probeer het later."     |

In alle vier de gevallen blijft het formulier onaangeraakt. Er wordt
**nooit** stilletjes Nederlandse tekst in een Engels veld gezet.

Gaat er iets mis waar de server een **validatiefout** van maakt -- een
beschrijving boven de grens, bijvoorbeeld -- dan komt die melding in het
venster zelf te staan, naast de knop. Zonder die regel verdween alleen het
laadschermpje en bleven de velden leeg, en dat leest als "soms doet die
knop het niet".

## Als het vertalen uitstaat

Zet je `TRANSLATE_ENABLED=false`, dan komt
[`GeenVertaler`](../../app/Support/Translation/GeenVertaler.php) in de
container, verdwijnt de knop uit het scherm en geeft de route een 404. De
rest van het portaal werkt gewoon.

Dat is met opzet een lege klasse en geen `null`: zou de container niets
teruggeven, dan moet elke aanroeper eerst controleren of er wel een
vertaler ís -- en die controle vergeet iemand een keer, met een foutmelding
op het scherm van de klant als gevolg. Nu is er altijd een vertaler; hij
kan alleen niets.

Dit is ook de stand in **elke test**.

## Wat er de deur uit gaat

**Dit is het enige punt in de applicatie waar inhoud van de klant naar een
derde partij gaat.** Druk hij op de knop, dan verlaten de functietitel, de
plaats en de beschrijving onze server en gaan ze naar MyMemory.

Dat is hier geen bezwaar: het is tekst die hij op zijn eigen openbare
website gaat zetten. Maar het is wél een eigenschap om te onthouden, want
MyMemory is deels een **openbaar vertaalgeheugen**. Ga ervan uit dat wat je
erheen stuurt daar blijft staan en door anderen teruggevonden kan worden.

> **De regel voor elke volgende module:** hang deze knop alleen aan velden
> waarvan de inhoud tóch openbaar wordt. Komt er ooit een module met
> gegevens die niet op de website horen -- een interne notitie, iets over
> een klant van de klant -- dan hoort daar géén vertaalknop bij, of een
> andere dienst met een verwerkersovereenkomst.

Het adres uit `TRANSLATE_EMAIL` gaat als parameter mee in de URL en belandt
dus in de logboeken van de dienst. Neem daar een zakelijk adres voor en
geen privéadres.

## Wat er nooit gebeurt

- **De echte dienst wordt niet aangeroepen in tests.** `phpunit.xml` zet
  `TRANSLATE_ENABLED` op false. Tests die het vertalen toetsen zetten zelf
  een dubbel in de container, en
  [`MyMemoryVertalerTest`](../../tests/Feature/Website/MyMemoryVertalerTest.php)
  gebruikt `Http::fake()`. Een testsuite die het internet op gaat, is
  traag en valt om zodra er bij een ander iets hapert.
- **Er belandt niets geheims in een log.** Er ís hier geen sleutel, maar
  regel 2 uit [`AGENTS.md`](../../AGENTS.md) geldt onverkort: de
  foutmeldingen van de dienst gaan niet ongezien het logboek in.
- **De route kan niet worden leeggetrokken.** Elke aanroep kost tekens van
  het dagtegoed, dus hij staat achter een rate limit van twintig per
  minuut. Een knop die per ongeluk in een lus staat, zou dat tegoed anders
  in een paar minuten opmaken.

## Hoe houdbaar is dit?

Eerlijk: **een gratis dienst zonder account geeft geen enkele garantie.**
Er is geen afspraak, geen ondersteuning en geen belofte dat het er volgend
jaar nog zo staat. Dat is de keerzijde van "geen aanmelding".

Wat wél vaststaat, is **hoe het stukgaat**. Dat is hier het belangrijkste
ontwerp:

| Als dit gebeurt              | Dan                                                      |
| ---------------------------- | -------------------------------------------------------- |
| De dienst ligt eruit         | De knop geeft een nette melding; je typt het zelf.       |
| Het dagtegoed is op          | Idem, met een andere zin.                                |
| Ze vragen ineens een sleutel | `TRANSLATE_ENABLED=false` en de knop verdwijnt.          |
| De kwaliteit zakt in         | Je ziet het vóór het opslaan, want je leest het toch na. |

**In geen van die gevallen gebeurt er iets met de website.** De knop slaat
niets op, er hangt geen enkele pagina van af, en het portaal blijft volledig
werken. Het ergste wat er kan gebeuren is dat een gemak wegvalt.

Twee dingen die de kans wél verkleinen, en die daarom in de
[deploychecklist](../operations/deployment.md) staan:

- **Zet `TRANSLATE_EMAIL` in productie.** Zonder dat adres telt de dienst
  het dagtegoed per IP-adres, en op gedeelde hosting deel je dat met elke
  andere site op die server. Mét adres telt hij per adres, en is het tegoed
  tien keer zo hoog.
- **Controleer of de server naar buiten mag.** Sommige hostingpakketten
  staan geen uitgaand verkeer toe.

## Een andere dienst gebruiken

Alleen `MyMemoryVertaler` weet iets van MyMemory; de rest praat met het
contract [`Vertaler`](../../app/Support/Translation/Vertaler.php).
Overstappen is dus één nieuwe klasse en één regel in `AppServiceProvider`,
en er verandert niets aan de formulieren. Dat is niet theoretisch: deze
module is begonnen met DeepL en binnen een uur omgezet toen bleek dat
daar een account voor nodig was.

Wordt het ooit een taalmodel in plaats van een vertaaldienst, dan past dat
in hetzelfde contract. Zet de afweging dan wel in het beslislogboek: een
model is beter in toon en context, maar niet deterministisch en duurder
per aanroep.

## Instellen

Niets doen: het werkt uit zichzelf. Wil je het dagtegoed verhogen van
5.000 naar 50.000 tekens, zet dan een adres in `.env`:

```dotenv
TRANSLATE_ENABLED=true
TRANSLATE_EMAIL=info@voorbeeld.nl
```

Dat adres gaat als parameter mee bij elke aanroep. Er hoort geen
aanmelding bij en er komt geen post op.
