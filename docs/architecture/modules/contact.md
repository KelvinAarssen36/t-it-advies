# Contact

De zesde module, en de eerste die twee kanten heeft: de klant beheert het
formulier, en wat bezoekers ermee doen komt bij hem terug in het portaal.

| Onderdeel                   | Waar de klant het beheert                     |
| --------------------------- | --------------------------------------------- |
| De onderwerpen              | Website → Contact                             |
| De velden van het formulier | Website → Contact                             |
| Waar het formulier staat    | Website → Contact, venster _Weergave_         |
| De bevestigingsmail         | Website → Contact, venster _Bevestigingsmail_ |
| De kop erboven              | Website → Contact, venster _Kop erboven_      |
| De binnengekomen aanvragen  | Beheer → Aanvragen                            |

> **Dit is de enige module die inhoud van een bezoeker bewaart.** Dat is een
> bewuste keuze met gevolgen buiten deze module; zie
> [De privacybelofte](#de-privacybelofte). Lees dat stuk voordat je hier iets
> aan verandert.

---

## De velden: een vaste set met een stand

[`ContactVeld`](../../../app/Enums/ContactVeld.php) is de **enige** lijst van
welke velden er kunnen zijn. De database bewaart alleen de stand en de
volgorde.

```
ContactVeld (enum)        wat een veld ís
        +
contact_fields (db)       of het aan staat
        ↓
Contactformulier          de samensteller
     ↙        ↘
de validatie   de props voor het formulier
```

**Waarom geen formulierbouwer.** Een veld is niet alleen een label: het is
een invoertype, een `autocomplete`-token, een maximumlengte, een
validatiesemantiek, een regel in de mail en een kolom in het overzicht. Dat
zijn zes dingen die niet uit een tekstveld van de klant kunnen komen. Zelfde
afspraak als bij de onderdelen van de pagina: _een nieuw soort veld is werk
voor ons, de standen en de volgorde zijn van hem._

Er zit ook een privacyargument in. Met een vrije bouwer kan de eigenaar een
veld "BSN" of "Geboortedatum" aanmaken, en dan verwerkt deze site bijzondere
persoonsgegevens die nergens beschreven staan.

### Eén stand en niet twee schuifjes

[`ContactVeldStatus`](../../../app/Enums/ContactVeldStatus.php) heeft drie
cases: `uit`, `optioneel`, `verplicht`.

"Zichtbaar" en "verplicht" als twee booleans coderen vier toestanden waarvan
er één onzin is: **onzichtbaar én verplicht**. Die ontstaat vanzelf -- de
eigenaar zet Telefoonnummer op verplicht, bedenkt zich, en zet alleen de
zichtbaarheid uit. Dan staat er een formulier dat niet te versturen is om een
veld dat er niet staat, en niets op het scherm legt uit waarom.

Met één driestandskeuze bestaat die toestand niet, en dat scheelt een
verdedigende controle op elke plek waar de velden worden opgebouwd.

### Drie velden staan vast

Naam, e-mailadres en bericht: altijd zichtbaar, altijd verplicht. Zonder die
drie kun je niemand antwoorden, en dan is het geen contactformulier meer.

Dat staat in `ContactVeld::vast()` en dus **in de code**, niet in de
database. `ContactField::stand()` leest nooit rechtstreeks de kolom:

```php
public function stand(): ContactVeldStatus
{
    return $this->key->vast()
        ? ContactVeldStatus::Verplicht
        : $this->status;
}
```

Een rij die met de hand of via een aangepast verzoek wordt omgezet, wordt dus
genegeerd in plaats van dat het formulier opengaat. `ContactFieldTest` houdt
dat op drie manieren vast.

### Een uitgezet veld krijgt `exclude`

En niet "geen regel". Zonder regel blijft de waarde weliswaar buiten
`safe()`, maar dan hangt het ervan af wie `safe()` gebruikt en wie `all()`.
Met `exclude` haalt de validator hem weg en kan geen enkele latere aanroep er
nog bij.

**En niet `prohibited`.** Dat zou een bezoeker wiens formulier tien minuten
openstond een foutmelding geven over een veld dat hij netjes heeft ingevuld,
terwijl de eigenaar het ondertussen uitzette. Stil weglaten is daar het
juiste antwoord.

---

## De onderwerpen

Een gewone module-lijst: tweetalig, aan en uit, versleepbaar. Het Engelse
label mag leeg blijven en valt dan terug op het Nederlands -- een keuzelijst
met een gat erin is geen keuzelijst.

Staan er onderwerpen aan, dan krijgt de bezoeker een keuzelijst met als
laatste optie _"Anders, namelijk..."_, die een tekstveld openklapt. Staat er
geen enkel onderwerp aan, dan is het gewoon een tekstveld -- zoals het altijd
was.

### Uitlichten gaat over de postbus en niet over het formulier

De eigenaar kan een onderwerp aanvinken met **Dit onderwerp uitlichten**.
Dan springt een **binnengekomen aanvraag** met dat onderwerp eruit in Beheer
→ Aanvragen: een sterretje bij het onderwerp en een warme tint op de regel.

**Op de site verandert er niets.** Dat is het hele punt, en het was eerst
anders: er stond een snelkeuze met uitgelichte onderwerpen bóven de
keuzelijst. Dat is een verkeerde lezing van wat uitlichten is. Het
formulier is voor de bezoeker, en dat hoort een neutrale lijst te blijven --
een onderwerp dat eruit springt duwt hem een kant op die niet de zijne is.
Uitlichten is voor de eigenaar: hij weet welke onderwerpen voorgaan.

Daarom gaat `featured` **niet** mee in `onderwerpenVoorDeSite()` en zit het
ook niet in de vingerafdruk van het formulier: het verandert niets aan wat
de bezoeker voor zich heeft.

**Een eigen kolom en niet "zet hem bovenaan".** Dat laatste kan de eigenaar
al met slepen, maar dan is hij zijn volgorde kwijt zodra hij iets anders wil
uitlichten, en terugzetten is handwerk. Zo blijft de volgorde van hem en is
uitlichten één vinkje dat je net zo makkelijk weer uitzet.

Drie gevallen waarin er niets wordt uitgelicht, en dat is juist:

| Geval                       | Waarom niets                                            |
| --------------------------- | ------------------------------------------------------- |
| De bezoeker typte zelf iets | Er hangt geen onderwerp van de eigenaar aan             |
| Het onderwerp is verwijderd | Hij heeft het zelf weggehaald; de tekst blijft leesbaar |
| Het onderwerp staat offline | Dan staat het ook niet in de keuzelijst                 |

Een warme tint en niet de accentkleur, want die is op dat scherm al bezet
door "ongelezen" -- zo kun je ze ook samen zien: een ongelezen aanvraag over
spoed heeft een blauwe rand én een warme achtergrond.

### Het onderwerp wordt twee keer bewaard

| Kolom            | Wat                           | Waarvoor                                                              |
| ---------------- | ----------------------------- | --------------------------------------------------------------------- |
| `subject_id`     | De verwijzing, `nullOnDelete` | Groeperen en de link naar het onderwerp zolang het bestaat            |
| `subject_text`   | **Altijd gevuld**             | Wat de bezoeker zag en verstuurde                                     |
| `subject_custom` | Boolean                       | Het verschil tussen "zelf getypt" en "koos iets dat inmiddels weg is" |

Dat is dezelfde reden als in het activiteitenlogboek, waar de naam als tekst
wordt bewaard en niet als verwijzing: **de regel moet leesbaar blijven nadat
het onderdeel is verwijderd**, en dat is nu juist de regel die je later
terugzoekt.

**De tekst komt bij een gekozen onderwerp van de server**, niet uit het
verzoek. Anders kan iemand een geldig id meesturen met een eigen label erbij,
en staat er in het beheerscherm iets anders dan wat hij aanklikte.

| Handeling van de eigenaar | Gevolg voor oude aanvragen                                                                    |
| ------------------------- | --------------------------------------------------------------------------------------------- |
| Hernoemen                 | Zijn scherm toont de nieuwe naam; `subject_text` houdt de oude                                |
| Uitzetten                 | Niets. Dit is de normale manier om een onderwerp met pensioen te sturen                       |
| Verwijderen               | `subject_id` wordt `null`, de tekst blijft, het scherm zegt "dit onderwerp bestaat niet meer" |

#### Welke van de twee de eigenaar ziet, en waarom dat de verwijzing is

`subject_text` is het **archief** en niet het scherm. Hier stond eerst dat een
oude aanvraag de oude tekst blijft tonen, ook na hernoemen, en dat leek het
veilige antwoord. Het liep stuk op de taal: een Engelse bezoeker kiest
"Informal conversation", dat gaat zo in de tabel, en daarmee stond er een
Engelse regel midden in de Nederlandse postbus van de eigenaar -- terwijl de
keuzelijst om op te filteren voor datzelfde onderwerp "Vrijblijvend gesprek"
zei. Dan zoek je op wat je ziet en vind je niets.

Vier lezers, en ze hoeven niet hetzelfde te zien, als het maar consequent is:

| Wie                          | Welke naam                                  |
| ---------------------------- | ------------------------------------------- |
| De bezoeker op het formulier | Zijn eigen taal                             |
| De bevestiging aan hem       | Zijn eigen taal                             |
| Het archief, `subject_text`  | Zijn eigen taal, zoals hij het zag          |
| De eigenaar                  | De taal van zijn portaal, uit de verwijzing |

Dat regelt
[`ContactSubmission::onderwerpVoorDeEigenaar()`](../../../app/Models/ContactSubmission.php):
bestaat het onderwerp nog, dan komt de naam daaruit in de taal van de lezer;
is het verwijderd of typte de bezoeker er zelf een, dan is de bewaarde tekst
het enige dat er is. **Wat iemand zelf intypte vertalen we nooit.**

Hernoemen valt daarmee aan dezelfde kant uit als de taal, en dat is winst:
anders zou de keuzelijst "Kennismaking" zeggen boven regels die
"Vrijblijvend gesprek" heten -- precies dezelfde fout, alleen door een andere
oorzaak.

Twee plekken gebruiken met opzet nog wél de bewaarde tekst, want daar is de
vraag "wat is er over deze persoon vastgelegd" en niet "waar gaat dit over":
[`Gegevensoverzicht`](../../../app/Support/Juridisch/Gegevensoverzicht.php) en
[`Antwoordtekst`](../../../app/Support/Juridisch/Antwoordtekst.php), allebei
op het scherm Juridisch.

En het **zoeken** op Aanvragen kijkt naar allebei: `subject_text` én de twee
labels van het onderwerp. Zonder dat tweede typt de eigenaar de naam die op
zijn scherm staat en vindt hij niets; zonder het eerste raakt hij een zelf
ingetypt of inmiddels verwijderd onderwerp kwijt.

---

## Waar het formulier staat

[`ContactWeergave`](../../../app/Enums/ContactWeergave.php): het formulier
onderaan de landingspagina, of alleen een knop daar met het formulier op
`/contact`.

Dat is geen smaakverschil. Een formulier onderaan de pagina nodigt uit; een
eigen pagina houdt de landing korter en is te delen als link. Welke beter
werkt hangt af van wat de klant met zijn site wil.

**Bij een eigen pagina gaan de velden niet mee naar de landing.** Dat is niet
alleen zuinig: één formulier op één plek betekent ook **één
Turnstile-widget**.

De pagina bestaat alleen als die weergave is gekozen, en verdwijnt ook als
het hele onderdeel uit staat -- dan wil de eigenaar geen contactformulier, en
een adres dat er toch is leidt bezoekers naar iets dat hij heeft weggehaald.
Hij staat in `pages/public/` en krijgt daarmee vanzelf de publieke layout.

### Die pagina was onaf, en dat was aan drie dingen te zien

Het staat hier omdat het drie verschillende soorten fout waren, en alleen de
eerste viel op als een fout.

**1. Er stond geen kop.** Het type in `Contact.vue` noemde de velden
`eyebrow`, `title` en `intro` -- de kolomnamen uit de database. Maar
`SectionHeading::voorDeSite()` stuurt `opschrift`, `titel` en `inleiding`.
Alle drie waren dus `undefined`: geen foutmelding, want een ontbrekende prop
is in Vue gewoon leeg; geen gefaalde test, want de server stuurde keurig wat
hij moest sturen; en op de pagina stond niets waar de kop hoort.
`ContactSection.vue` had precies dezelfde fout, dus de kop ontbrak ook
onderaan de landingspagina.

> `vue-tsc` kon er niets aan doen: het type zei wat de component verwachtte,
> niet wat er werkelijk binnenkwam. Die brug ligt alleen in een test die de
> échte props van de échte route naleest -- `SectieKopPropsTest`. Gebruik
> voor een kop altijd het gedeelde type `SectieKop` en niet een eigen
> lijstje.

**2. Er zat geen omhulsel om de pagina.** Het formulier liep van rand tot
rand en er was geen ruimte boven en onder -- op een breed scherm een
veldenrij van bijna twee meter. `/privacy` doet het goed met
`mx-auto max-w-* px-6 py-16`; dat patroon staat nu ook hier.

**3. Een formulier alleen op een lege pagina is een invulbriefje.** Er staat
nu naast wíe je schrijft: dezelfde foto als het medaillon in de kop van de
site, de naam van de eigenaar, zijn adres, zijn LinkedIn en hoe snel hij
antwoordt. Dat is het verschil tussen een contactpagina en een veldenlijst.

Het formulier krijgt de brede kolom, want dat is waarvoor iemand komt; het
visitekaartje staat ernaast en onder `lg` eronder -- op een telefoon wil je
eerst het formulier zien.

### Het uitzetten geldt ook voor de POST

`Contactformulier::staatAan()` beantwoordt dat op één plek, en zowel
`PublicContactController` als `ContactController` stelt de vraag.

**Dat tweede ontbrak, en dat was een instelling die maar half werkte.** Zette
de eigenaar het onderdeel uit, dan verdween het formulier netjes van de site,
maar `POST /contact` bleef aannemen, opslaan én mailen. Gemeten: 302, een rij
in `contact_submissions` en twee mails in de wachtrij, met het onderdeel uit.
Een oud tabblad of een bot met die URL kwam er dus nog door.

De regel is met opzet letterlijk dezelfde als op de landingspagina: staat er
nog geen enkele rij in `page_sections`, dan is er nooit geseed en valt alles
terug op de volgorde uit de code -- anders levert een vergeten `db:seed` een
site zonder contactformulier op. Zie `HomeController::secties()`.

> **`TelBezoek` staat op die route.** Zonder dat zakken de bezoekcijfers
> zodra de eigenaar voor deze weergave kiest, en dan lijkt het of zijn
> website minder bekeken wordt terwijl hij alleen iets heeft omgezet. Het
> commentaar bij de route van de landingspagina zei al dat een tweede
> publieke pagina deze middleware ook hoort te krijgen; dit is die tweede.

### Géén teller in `SectionContent`

Bij elke andere module telt die het aantal items, zodat een leeg onderdeel van
de site valt. Hier staat er met opzet geen: zonder onderwerpen is het
onderwerpveld gewoon een tekstvak, en dat werkt. Zou hij de aanvragen tellen,
dan verdwijnt het contactformulier van de website tot de eerste bezoeker iets
instuurt.

---

## Wat er gebeurt bij een inzending

De keten op de route is ongewijzigd: `throttle:contact` (3 per minuut, 20 per
dag) → honeypot → Turnstile in de validatie. Daarna, in deze volgorde:

1. **De aanvraag wordt opgeslagen.**
2. De melding aan de eigenaar gaat de wachtrij in.
3. De bevestiging aan de bezoeker gaat de wachtrij in.
4. De dagteller gaat één omhoog.

**Die volgorde is geen toeval.** Andersom zou de eigenaar een mail kunnen
krijgen waar geen regel in zijn overzicht bij hoort, en dan zoekt hij naar
iets dat er niet is. Het levert bovendien een verbetering op: een bericht
raakt niet meer kwijt als de mailprovider eruit ligt.

### Twee mails, elk in zijn eigen taal

```php
// De melding aan de eigenaar: geen taal hier.
Mail::to(config('mail.contact_address'))->queue(new ContactMessageMail(...));

// De bevestiging aan de bezoeker: wél, want die taal is van hem.
Mail::to($aanvraag->email)->locale($aanvraag->locale)->queue(new ContactBevestigingMail(...));
```

**De bevestiging krijgt zijn taal hier, de melding zet hem zelf.** Dat
verschil is geen stijl maar de oplossing van een fout die twee keer is
gemaakt.

`ContactBevestigingMail` is van de bezoeker, en welke taal dat is weet alleen
dit verzoek -- vandaar de kolom `locale` op de aanvraag en `->locale()` bij
het versturen. Zonder dat zou de wachtrij hem in het Nederlands renderen: die
draait in een losse opdrachtregel waar de taal van het verzoek niet bestaat.
`App::setLocale()` is geen alternatief; dat mag in dit project alleen in
`SetLocale` staan.

`ContactMessageMail` is van de eigenaar en zet `config('site.locale')` in zijn
eigen constructor. **Hier stond eerst `->locale(config('app.locale'))`, en dat
deed niets.** Dat leest als "de ingestelde standaardtaal", maar
`Application::setLocale()` schrijft de taal van het huidige verzoek ín die
configuratiewaarde -- dus na de middleware `SetLocale` ís `app.locale` de taal
van de bezoeker. Gemeten: de eigenaar kreeg `Contact form: Een vraag` in zijn
postvak zodra er een Engelstalige bezoeker schreef, precies wat die regel
moest voorkomen.

Twee dingen die daaruit volgen en die je niet moet terugdraaien:

- **De taal staat in de constructor en niet bij de aanroeper.** Zo kan geen
  enkele aanroeper hem vergeten, en hoeft niemand te weten dat `app.locale`
  onderweg verandert. `PendingMail::fill()` laat een expliciete `->locale()`
  nog altijd winnen, dus afwijken kan.
- **De taal van de bezoeker staat wél in de mail**, als de regel "Taal van de
  bezoeker". De eigenaar moet weten in welke taal hij hoort te antwoorden; dat
  is iets anders dan de mail zelf in die taal zetten.

Hetzelfde geldt voor `CrashAlertMail` -- die vertrekt uit de foutafhandeling
van een gewoon verzoek en had dus dezelfde fout -- en voor `SecurityAlertMail`,
die uit de planner komt en hem bij voorbaat heeft. Zie
[mail en queues](../mail-en-queues.md) en `config/site.php`.

> **De test die dit moest afvangen kon niet falen.** Hij vergeleek
> `$mail->locale` met `config('app.locale')`, en na een Engelstalig verzoek
> waren beide kanten `'en'`. Een groene test met de naam "stays dutch" die het
> tegendeel toestond. Nu staat er de letterlijke `'nl'` plus het gerenderde
> onderwerp; zie `ContactSubmissionTest` en `InterneTaalTest`.

#### En de taal van de mail gaat verder dan de tekst van de eigenaar

`->locale()` zette de taal goed, maar de bevestiging bleef half Nederlands
voor een Engelse bezoeker. De oorzaak: het onderwerp en de tekst komen uit
`contact_settings` en zijn dus tweetalig, maar de zinnen die wij erom heen
zetten gaan door `__()` -- en drie daarvan stonden niet in `lang/en.json`.
Gerenderd zag een Engelse bezoeker:

```
Bedankt voor je bericht          <- onze zin, geen vertaling
Beste John Smith,                <- onze zin, geen vertaling
Thank you for your message...    <- de tekst van de eigenaar, goed
Je onderwerp: ...                <- onze zin, geen vertaling
Kind regards,                    <- onze zin, wel vertaald
© 2026 @T IT Advies. All rights reserved.
```

`TranslationsTest` kijkt alleen in `resources/js`, dus hiervan merkte niets
iets -- de sleutels staan in een Blade-bestand. De laatste regel had een
eigen oorzaak; zie [mail en queues](../mail-en-queues.md).

**Voeg je een zin toe aan deze mail, zet hem dan in `lang/en.json`.** Dit is
de enige mail in het project die in twee talen de deur uit gaat: de melding
aan de eigenaar en de alarmeringsmails staan vast op `config('site.locale')`.

### Van Versturen naar de bevestiging, in één beweging

Het bevestigingsvak animeerde al; wat eraan ontbrak was alles ervoor. Je
drukte op Versturen, het formulier was er in één beeldje niet meer, en daar
kwam los daarvan een vak op. Dat leest als een storing en niet als een
bevestiging -- terwijl dit het enige moment op de hele site is waarop een
bezoeker iets _doet_.

Nu zijn het drie stappen die in elkaar overlopen:

| Wanneer            | Wat je ziet                                                                                |
| ------------------ | ------------------------------------------------------------------------------------------ |
| Bij de klik        | De knop krijgt zijn spinner, en het formulier zakt weg naar 55% met `pointer-events: none` |
| Het antwoord is er | Het formulier schuift 200 ms weg: wegvallend en tien beeldpunten omhoog                    |
| Daarna             | Het vak komt op (500 ms), en het vinkje ploft er met een overshoot in                      |

#### En dan sprong je naar de bovenkant van de pagina

Dat was geen animatiefout maar een instelling die er altijd al verkeerd
stond, en de animatie maakte hem zichtbaar. `preserveScroll` staat bij
Inertia's `<Form>` standaard op `false`: na een geslaagde inzending springt
de bezoeker naar boven, en kijkt hij dus naar de kop van de pagina terwijl
zijn bevestiging onderaan staat. Op de aparte contactpagina net zo.

Er zat een tweede, stillere kant aan. `preserveState` staat standaard op
`null`, en dat is voor Inertia hetzelfde als "niet bewaren": het component
wordt opnieuw opgebouwd. Dan bestaat het bevestigingsvak al bij de eerste
tekening, is er geen wissel, en draait er **geen enkele animatie** -- ook
die van vóór deze ronde niet, want een `watch` vuurt niet bij een eerste
tekening. Het vak plofte er dus altijd zonder beweging in, onderaan een
pagina waar je net vandaan was gesprongen.

Allebei staan nu aan. Je blijft staan waar je bent, het component blijft
bestaan, en daardoor is het een echte wissel met een echte overgang.

> **En ze gaan via `:options` en niet als losse attributen, en dat kostte
> een extra ronde.** Inertia's `<Form>` kent `preserveScroll` en
> `preserveState` **niet** als prop -- alleen `<Link>` heeft die. Zet je ze
> er toch op, dan rendert Vue ze als gewoon HTML-attribuut op het
> `<form>`-element: geen foutmelding, geen waarschuwing, en precies niets
> dat anders gaat. Wat de `Form` wél doorgeeft aan het verzoek is
> `...props.options`, en dat spreidt hij als laatste uit over zijn eigen
> opties. `useForm` geeft ze daarna ongewijzigd door aan de router.
>
> Dat is de valkuil om te onthouden: een onbekende prop op een
> Vue-component verdwijnt stil in de attributen. Wie hier iets aan de
> inzending wil veranderen, zet het in `VERSTUUROPTIES` en niet op de tag.

Twee dingen die ik daarbij heb nagekeken, want `preserve-state` laat meer
in leven dan alleen de velden:

- **De honeypot blijft vers.** Zijn veldnamen en zijn versleutelde
  tijdstempel komen uit de gedeelde props, en die zijn een closure in
  `HandleInertiaRequests` -- dus ze worden bij élk antwoord opnieuw
  gemaakt, bewaarde staat of niet. En `amount_of_seconds` is een
  ondergrens, geen houdbaarheidsdatum.
- **De Turnstile-reset is nu pas echt nodig.** Zolang het component bij
  een mislukte validatie opnieuw werd opgebouwd, kreeg je vanzelf een
  nieuwe widget. Nu blijft hij staan, en is `@error` → `reset()` het enige
  dat een tweede poging laat lukken. Die reparatie zat er al; hij is hier
  van netheid naar noodzaak gegaan.

**Het dimmen is niet alleen vormgeving.** `pointer-events: none` hoort
erbij: zonder dat kun je tijdens het versturen nog in een veld typen dat
zometeen verdwijnt, of een tweede keer op de knop drukken.

**Het komt van `start` en `finish` en niet uit `processing` in de slot.**
Dat laatste bestaat alleen binnen de slot, en het dimmen gaat juist over het
`<form>`-element zelf. En `finish` en niet alleen `error`: bij een mislukte
verbinding komt geen van de twee andere, en dan zou het formulier gedimd en
onaanklikbaar blijven staan.

**De animatie van het vak hangt aan de overgang en niet meer aan een `watch`
op de status.** Die `watch` draaide op het moment dat de status binnenkwam en
zocht het vak met een selector. Sinds het formulier netjes wegschuift
voordat de bevestiging komt -- `mode="out-in"` -- bestaat dat vak op dat
moment nog niet, en had de animatie niets om op te spelen. Vue geeft het
element nu mee zodra het er echt is.

Het opkomen staat met opzet in GSAP en niet in CSS: dat vinkje hoort met een
overshoot in te ploffen (`back.out(2.2)`), en dat is wat "gelukt" zegt.
Zonder die overshoot leest het als nog een blok tekst dat verschijnt. Het
weggaan is wél CSS, want dat is een rechttoe rechtaan overgang.

Bij `prefers-reduced-motion` wisselt het vak gewoon om. **Het dimmen blijft
daar wél staan**: dat is geen animatie maar een toestand, en zonder beweging
is het het enige dat laat zien dat de klik is aangekomen.

### De Turnstile-reset, en waar hij hoort te hangen

`TurnstileWidget` had altijd een `reset()`, en die werd nooit aangeroepen. Een
token werkt één keer, dus een tweede bericht in dezelfde paginaweergave
faalde stil op de verificatie -- met een melding over een controle waar de
bezoeker niets aan kon doen.

**De eerste reparatie hing hem aan `@success`, en daar doet hij niets.** Bij
succes neemt het bevestigingsvak de plek van het formulier in (`v-if` /
`v-else`), dus de widget wordt opgeruimd; resetten van iets dat verdwijnt
helpt niemand. Het geval dat hem écht nodig heeft is een **mislukte
validatie**: het token is dan al verbruikt bij Cloudflare, het formulier
blijft staan, en de volgende poging struikelt over de verificatie terwijl de
bezoeker alleen een te kort bericht had. Vandaar `@error`.

Dat dit niet opviel heeft dezelfde oorzaak als de fout zelf: lokaal zonder
secret wordt Turnstile overgeslagen, dus er valt niets te verbruiken. Zie
[spam- en botbescherming](../../security/spam-en-botbescherming.md).

### Het tokenveld is verplicht, en er staat een uitweg onder het formulier

De regels komen uit `TurnstileRule::veld()`. Daar stond `nullable`, en
daarmee was de hele botcheck te omzeilen door het token niet mee te sturen;
het hele verhaal staat in
[spam- en botbescherming](../../security/spam-en-botbescherming.md).

Gevolg van `required`: laadt het script van Cloudflare niet, dan is er geen
tokenveld en is het formulier niet te versturen. Dat is de keuze -- liever
dicht dan onbeschermd -- en daarom staat het **e-mailadres onder de knop**,
op beide plekken waar het formulier staat. Dat is de prop `email` op
`ContactFormulier.vue`, die `HomeController::contactblok()` en
`PublicContactController` meegeven. Een bezoeker mag nooit met lege handen
staan omdat een derde partij eruit ligt.

### De instellingen veranderen onderweg

De eigenaar kan een veld aanzetten terwijl een bezoeker zit te typen. Dan
mist de inzending dat veld, geeft de validatie een foutmelding bij een veld
dat niet op het scherm staat, en kijkt de bezoeker naar een formulier dat
niet verstuurt en nergens rood is. **Dat is de stilste storing die er
bestaat.**

Daarom gaat er een vingerafdruk van het formulier als verborgen veld mee
(`Contactformulier::versie()`). Komt er een andere waarde terug, dan krijgt
de bezoeker één waarschuwing bovenaan en blijft zijn formulier staan.

#### Een waarschuwing en geen succes

**De eerste reparatie maakte er een stillere storing van.** De controller gaf
die melding terug met `back()->with('status', ...)`, en `status` is het kanaal
van een geslaagde inzending: het formulier toont daarop het groene vak met
"Aanvraag gelukt" en haalt zichzelf weg. De bezoeker kreeg dus een vinkje te
zien, zijn tekst verdween, en er was niets opgeslagen en niets verstuurd.
Gemeten: 302, geen validatiefouten, nul rijen, nul mails, en `flash.status`
gevuld met de waarschuwing.

Nu is het een **validatiefout** op `instellingen`, afgehandeld in
`ContactRequest::after()`. Dat is geen omweg maar de kortste route naar het
gedrag dat je wil, want alle vier de eigenschappen komen er gratis bij:

| Wat er moet gebeuren                  | Waarom het nu gebeurt                                    |
| ------------------------------------- | -------------------------------------------------------- |
| Geen bevestigingsvak                  | Het is geen succes, dus `status` blijft leeg             |
| De tekst van de bezoeker blijft staan | Inertia bewaart de invoer bij een foutantwoord           |
| Opnieuw versturen lukt                | De pagina krijgt verse props, dus een verse vingerafdruk |
| Een vers Turnstile-token              | `@error` op het formulier roept `reset()` aan            |

De melding staat bovenaan en niet bij een veld: er is geen veld dat de
bezoeker kan verbeteren. Hij zegt expliciet dat de tekst er nog staat, want
dat is op dat moment het enige dat hij wil weten.

> **De honeypot blijft `status` wél gebruiken, en dat is de uitzondering die
> het verschil uitlegt.** Een bot mag niet kunnen zien dat hij betrapt is, dus
> `LogSpamResponder` doet alsof het gelukt is. `status` betekent in dit
> formulier dus letterlijk "doe alsof het gelukt is" -- en dat is precies niet
> wat je tegen een echte bezoeker wil zeggen.

#### De vingerafdruk hasht de inhoud en niet de tijdstempels

Eerst stond er `max('updated_at')` van twee tabellen plus een rijaantal. Die
kolommen hebben secondeprecisie, dus een wijziging in dezelfde seconde als de
vorige leverde dezelfde vingerafdruk op -- dan keek de controle weg, en dat is
het soort stilte waar dit veld tegen moest beschermen.

Nu wordt de werkelijke stand gehasht: per veld de sleutel, de stand en de
plek, plus de onderwerpen die online staan en of een eigen onderwerp mag. Dat
is niet alleen juister maar ook zuiniger en vriendelijker:

- **drie queries minder.** Wat er gehasht wordt is al ingelezen door
  `rijen()` en `onderwerpen()`; de aggregaten waren extra werk.
- **minder valse treffers.** Een onderwerp dat offline staat zat wel in
  `max('updated_at')` maar staat niet op het formulier. De eigenaar die
  daaraan sleutelde onderbrak een bezoeker die er niets van zou merken.

---

## De privacybelofte

**Dit is het deel dat niet vergeten mag worden.** Drie uitspraken in de
privacyverklaring werden onwaar zodra aanvragen bewaard worden, en ze zijn in
dezelfde wijziging aangepast:

| Waar                       | Wat er stond                                                               |
| -------------------------- | -------------------------------------------------------------------------- |
| Kop _Het contactformulier_ | "Daarna is het weg uit de website en staat het alleen nog in onze mailbox" |
| De bewaarlijst             | "Op de website zelf alleen tot het verstuurd is"                           |
| `Antwoordtekst`            | "dat staat in onze mailbox en kunnen we opzoeken en verwijderen"           |

Wat er verder bij hoorde:

- **Een bewaartermijn van een jaar**, in `config('site.contact.retention_days')`,
  opgeruimd door `PruneContactSubmissions` om 03:15. De privacyverklaring
  **leest diezelfde waarde**, dus die tekst kan niet verouderen.
- **De aanvragen in `Gegevensoverzicht::plekken()`**, als eerste plek die
  zowel `zoekbaar` als `wisbaar` is -- en daarmee de eerste waar een verzoek
  om verwijdering in het portaal is af te handelen in plaats van alleen in de
  mailbox.
- **`Gegevensoverzicht::zoek()` was de enige zoekopdracht en is het niet
  meer.** Het scherm Juridisch doorzoekt nu twee tabellen met dezelfde
  zoekterm.
- **De drie stappen bij "Niets gevonden"** op Juridisch: daar stond dat
  berichten uit het contactformulier "nergens anders" dan in de mailbox
  staan.
- **Het mailoverzicht werd zoekbaar.** Daar stond "alleen jouw eigen adres
  als ontvanger", en dat was waar tot er een bevestiging naar de bezoeker
  ging. Zijn adres staat er nu honderdtachtig dagen in.

### `ContactSubmission` krijgt géén `LogsActivity`

Die trait legt bij het aanmaken alle invulbare velden vast. Naam, adres en het
volledige bericht zouden dan een **tweede keer** in `activity_entries`
belanden -- met een eigen bewaartermijn van een jaar, op een scherm dat niet
over contact gaat, en `redact()` in `config/security.php` kent alleen
wachtwoorden en tokens, niet `message`.

Alleen het **verwijderen** wordt gelogd, expliciet via `SecurityLogger`, en
daar gaat geen inhoud in mee.

| Model                            | `LogsActivity`?                        |
| -------------------------------- | -------------------------------------- |
| `ContactSubject`                 | **Ja** -- inhoud van de eigenaar       |
| `ContactField`, `ContactSetting` | **Ja** -- instellingen van de eigenaar |
| `ContactSubmission`              | **Nee** -- gegevens van een bezoeker   |

### Géén IP-adres bij een aanvraag

Dat zou een tweede beveiligingslogboek maken met een andere termijn en zonder
doel: een geblokkeerde poging staat al mét IP in `security_events`, en een
geslaagde aanvraag hoeft niet tot een verbinding herleidbaar te zijn.

---

## Vaste kolommen en geen JSON

`contact_submissions` heeft een kolom per veld. Twee redenen die allebei
zwaarwegen:

1. **Juridisch moet op e-mailadres zoeken**, met `LIKE ... ESCAPE '!'` via
   [`Zoekterm`](../../../app/Support/Zoekterm.php). Op een JSON-kolom wordt
   dat `json_extract(...) LIKE ?`, en SQLite -- waar de tests draaien -- gaat
   daar anders mee om dan MySQL. Dat is precies het verschil waardoor je een
   test op een dag ten onrechte gelooft.
2. **Het beheerscherm moet de velden netjes tonen** zonder te raden wat erin
   zit.

De prijs is een migratie per nieuw veld, en dat is precies het moment waarop
iemand er toch naar moet kijken.

**Eén kolom is wél JSON: `shown`.** Dat is geen antwoord maar een
momentopname van de instellingen. Zonder dat kan het beheerscherm niet zien
of "Telefoonnummer" leeg is omdat de bezoeker het oversloeg of omdat het veld
toen niet bestond -- en dat is een ander verhaal. Er wordt nooit op gezocht.

---

## De mail in de huisstijl

Er was geen eigen maillayout: alle mail draaide op het standaardthema van
Laravel. Nu staat in `resources/views/vendor/mail/` een kopie van dat thema
met de kleuren van het project erin, aangewezen door
`config('mail.markdown.theme')`.

**Bewust een kopie en geen eigen sjabloon.** De opbouw van een mail --
tabellen in tabellen, inline stijlen -- is uitgevochten tegen twintig jaar
mailprogramma's, en die strijd doen we niet over. Alleen kleuren, randen en
ruimte zijn aangepast.

Geen gradient op de kop: Outlook op Windows tekent die niet en laat dan een
grijs vlak zien. En de naam staat er als **tekst** en niet als afbeelding:
veel mailprogramma's blokkeren beelden tot je ze toelaat, en dan is de
afzender een leeg vak.

Daarmee gingen de bestaande beveiligings- en crashmails in één keer mee.

### Het voorbeeld onder Weergave

Instellingen → Weergave toont de mail in een `iframe`. Dat rendert de
**échte** mailable met verzonnen gegevens -- `$mail->render()`, niets wordt
verstuurd. Daardoor kan het voorbeeld niet uit de pas lopen: het ís de mail.
Zou het een eigen stukje HTML zijn, dan klopt het tot de dag dat iemand het
thema aanpast en er niet aan denkt.

---

## Wat waar staat

| Bestand                                                                                              | Wat het doet                                                       |
| ---------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| [`ContactVeld`](../../../app/Enums/ContactVeld.php)                                                  | Welke velden er kunnen zijn, en wat een veld is                    |
| [`ContactVeldStatus`](../../../app/Enums/ContactVeldStatus.php)                                      | Uit, optioneel of verplicht                                        |
| [`ContactWeergave`](../../../app/Enums/ContactWeergave.php)                                          | Op de pagina of een eigen pagina                                   |
| [`Contactformulier`](../../../app/Support/Contact/Contactformulier.php)                              | De samensteller: één bron voor de validatie, de site en het scherm |
| [`ContactModuleController`](../../../app/Http/Controllers/Website/ContactModuleController.php)       | Het beheerscherm                                                   |
| [`ContactController`](../../../app/Http/Controllers/ContactController.php)                           | Een inzending aannemen                                             |
| [`ContactSubmissionController`](../../../app/Http/Controllers/Admin/ContactSubmissionController.php) | Beheer → Aanvragen                                                 |
| [`PublicContactController`](../../../app/Http/Controllers/PublicContactController.php)               | De aparte `/contact`-pagina                                        |
| [`ContactBevestigingMail`](../../../app/Mail/ContactBevestigingMail.php)                             | De bevestiging aan de bezoeker                                     |
| [`PruneContactSubmissions`](../../../app/Console/Commands/PruneContactSubmissions.php)               | De bewaartermijn                                                   |
| [`ContactFormulier.vue`](../../../resources/js/components/site/ContactFormulier.vue)                 | Het formulier, voor de landing én de eigen pagina                  |
| [`website/Contact.vue`](../../../resources/js/pages/website/Contact.vue)                             | Het beheerscherm                                                   |
| [`admin/Aanvragen.vue`](../../../resources/js/pages/admin/Aanvragen.vue)                             | De postbus                                                         |
| [`TurnstileRule`](../../../app/Rules/TurnstileRule.php)                                              | De botcheck; gebruik hem via `veld()`                              |
| [`MeldtMislukteVerzending`](../../../app/Concerns/MeldtMislukteVerzending.php)                       | Een mail die nooit vertrok laat alsnog een spoor achter            |

### De twee vinkjes staan vóór de regel

Gelezen en beantwoord zijn twee kleine vakjes aan het begin van elke regel,
niet in het uitgeklapte vlak. **Ze stonden daar wel**, als grote ronde
bollen, en dat betekende dat je eerst een regel moest openklappen voordat je
kon afvinken -- precies één handeling te veel voor het enige dat je op dit
scherm doet.

Drie dingen die daarbij horen:

- **De vinkjes staan buiten de knop die openklapt.** Een knop in een knop
  mag niet van HTML en werkt ook niet: de klik komt bij de buitenste
  terecht, en dan klapt de rij open in plaats van dat je iets aanvinkt.
  Vandaar een regel met drie delen -- de vinkjes, de samenvatting die
  openklapt, en het tijdstip.
- **Twee verschillende tekens en niet twee keer een vinkje.** Een vinkje
  voor gelezen, een antwoordpijl voor beantwoord. Alleen kleur is te weinig:
  wie kleuren slecht onderscheidt ziet anders twee gevulde vakjes zonder
  verschil.
- **Klein en vierkant.** Er staan er twee per regel en twintig regels op een
  pagina; groot zouden ze de naam wegduwen, en dan ben je iets anders aan
  het lezen dan wie je geschreven heeft.

### Filteren op onderwerp

Naast het filter op stand en niet erin: "alleen ongelezen" en "alleen
offertes" zijn twee vragen die je ook samen kunt stellen. Het werkt ook
samen met het zoekveld.

Twee keuzes die uitleg verdienen:

- **"Zelf ingevuld" is een eigen regel in dat filter.** Een bezoeker die
  zijn eigen onderwerp typte hangt aan geen enkel onderwerp en zou anders
  niet te filteren zijn -- en juist daar zit wat niet in de lijst van de
  eigenaar past. Dat is precies waar je af en toe naar wil kijken.
- **Álle onderwerpen staan in het filter, ook de offline.** Zette de
  eigenaar er een offline, dan blijven de aanvragen die eraan hangen
  bestaan, en dan moet hij ze ook nog kunnen opzoeken.

### Wat het scherm Aanvragen níet laat zien

**De bezorgstatus van de twee mails.** Een aanvraag staat er zodra hij is
opgeslagen, en dat gebeurt vóór de mails de wachtrij in gaan -- de twee
kunnen dus los van elkaar misgaan. Een kolom "verstuurd" naast elke regel zou
die twee door elkaar halen en maar half kloppen.

In plaats daarvan staat er één regel die naar Beheer → Mail wijst, en komt
een mislukte verzending daar ook echt te staan; zie
[mail en queues](../mail-en-queues.md). De eigenaar krijgt er binnen het uur
een melding over, want `AnomalyScanner` telt een `Failed`-rij als
mailprobleem.

Dat is tegelijk het antwoord op "wat als de mail niet aankomt": **de aanvraag
is dan niet weg.** Hij staat in het portaal, met naam, adres en bericht, en
de eigenaar kan gewoon antwoorden.

## Tests

| Bestand                              | Wat het bewaakt                                                                                          |
| ------------------------------------ | -------------------------------------------------------------------------------------------------------- |
| `ContactSubjectCrudTest`             | De dertien vaste gevallen, en dat een verwijderd onderwerp zijn aanvragen leesbaar laat                  |
| `ContactFieldTest`                   | Een uitgezet veld levert niets op, een vast veld is niet uit te zetten -- ook niet via een eigen verzoek |
| `ContactSubmissionTest`              | Opslaan, de taal, de twee mails, en dat een aanvraag niet in het activiteitenlogboek komt                |
| `ContactSubmissionScreenTest`        | Rechten, zoeken met ontsnapte jokertekens, de twee bollen, verwijderen achter een verse code             |
| `ContactPlacementTest`               | De twee weergaven, en dat de aparte pagina alleen bestaat als hij gekozen is                             |
| `ContactRetentionTest`               | Een aanvraag van 366 dagen oud verdwijnt, die van 364 niet                                               |
| `ContactOnderwerpTaalTest`           | Wie welke naam van een onderwerp ziet, en dat zoeken op wat je ziet het ook vindt                        |
| `ContactFormTest`                    | De drie beschermingslagen; bestond al                                                                    |
| `PrivacyPageTest`, `LegalScreenTest` | Dat de verklaring niet terugvalt op de oude belofte, en dat Juridisch de aanvragen vindt                 |

## Wat er bewust niet in zit

- **Geen formulierbouwer.** Zie hierboven.
- **Niet antwoorden vanuit het portaal.** Uitdrukkelijk zo gevraagd; de lijst
  is er om bij te houden, niet om mee te mailen.
- **Geen bijlagen.** Een bestand uit een formulier is een heel ander
  beveiligingsverhaal.
- **Geen ander spamfilter.** Turnstile staat er al, is getest en werkt.
- **Geen soft deletes op een onderwerp.** `published` lost hetzelfde al op.
