# Verzoeken van bezoekers

Iemand mailt: _"welke gegevens hebben jullie van mij, en wil je die
verwijderen."_ Dit document gaat over wat er dan gebeurt, en over het scherm
**Beheer → Juridisch** dat daarvoor is gemaakt.

> **De vraag die hieronder wordt beantwoord is niet "mag dit" maar "kan de
> eigenaar dit".** Een recht dat op papier bestaat en in de praktijk niet uit
> te voeren is, levert precies het probleem op dat je wilde voorkomen.

## Waar gegevens van een bezoeker kunnen staan

Dit is nagelopen door elke tabel in de database af te gaan op kolommen die
een persoon kunnen aanwijzen (`ip_address`, `user_agent`, `email`,
`payload`). De uitkomst:

| Plek                                     | Wat erin staat                                                   | Terug te vinden? | Te verwijderen?        |
| ---------------------------------------- | ---------------------------------------------------------------- | ---------------- | ---------------------- |
| **De mailbox van de eigenaar**           | Naam, adres, onderwerp, bericht                                  | ja               | ja, door hem           |
| `security_events`                        | IP + browserkenmerk bij een inlogpoging of geblokkeerd formulier | **ja**           | nee, met reden         |
| `failed_jobs`                            | Een bericht dat niet verstuurd kon worden: naam, adres, inhoud   | nee              | **ja**, via dit scherm |
| `sessions`                               | IP + browserkenmerk, zolang de sessie leeft                      | nee              | verloopt zelf          |
| `mail_logs`                              | Alleen het eigen adres als ontvanger, plus status                | nee              | nee                    |
| `site_visitor_codes`                     | Onomkeerbare codes, hoogstens één dag                            | nee              | n.v.t.                 |
| `site_day_totals`, `site_day_dimensions` | Aantallen per dag                                                | nee              | n.v.t.                 |
| `activity_entries`                       | Wat de eigenaar zelf wijzigde, met zijn IP                       | n.v.t.           | nee                    |

**Eén tabel is dus de hele zoekopdracht**: `security_events`. Dat is waarom
het scherm één zoekveld heeft en niet zeven.

## Het scherm

Drie dingen, in deze volgorde:

1. **Zoeken** op e-mailadres of IP-adres, met een ondergrens van drie
   tekens -- met minder komt het halve logboek terug en is het scherm een
   exportknop voor alles wat we hebben.
2. **Een antwoord om te kopiëren.** De tekst verandert mee met wat er
   gevonden is, met de bewaartermijnen er al in. Dit is waarom het scherm
   bestaat: een procedure die je moet onthouden gaat fout, een tekst die er
   al staat niet.
3. **Wat er waar staat en hoe lang**, uit de instellingen die het ook echt
   bepalen. Een met de hand getypte lijst klopt tot de dag dat iemand een
   termijn wijzigt, en dan geeft de eigenaar een antwoord dat niet waar is.

### Niets gevonden is ook een antwoord -- maar nog niet meteen

Bij een lege uitkomst staat er geen leeg vak maar een zin: _"Niets gevonden
in je beveiligingslogboek. Dat is de enige plek in je website waar een
bezoeker terug te vinden is."_ Dat is wat hij moet kunnen antwoorden, en hij
moet weten dat hij op de goede plek heeft gekeken.

**Daarna komt een stap die er eerst niet stond, en dat was de gevaarlijkste
plek op dit scherm.** Er stond in hetzelfde groene vak meteen "je kunt
antwoorden dat je niets van hem hebt". Die zin klopt binnen de applicatie,
maar het contactformulier komt in de **mailbox** terecht en niet in deze
database -- dus wie daar ooit een bericht liet staan is hier onvindbaar
terwijl er wél iets van hem is.

Het scherm zegt daarom nu, in deze volgorde:

1. Niets gevonden in het beveiligingslogboek.
2. **Kijk nu ook in je mailbox op dit adres.** Berichten uit het
   contactformulier komen daar binnen en staan nergens anders.
3. Staat daar ook niets, dán kun je antwoorden dat je niets van hem hebt.

De waarschuwing stónd er al wel, maar in de antwoordtekst eronder -- als
zin die hij naar de bezoeker stuurt, niet als stap voor hemzelf. En dat is
tekst om te kopiëren, niet om te lezen.

> **Het faalscenario waar dit voor is:** iemand mailt om verwijdering, hij
> vulde twee maanden geleden het contactformulier in, de zoekopdracht geeft
> niets, en de eigenaar antwoordt te goeder trouw "wij hebben niets van je"
> terwijl er drie berichten in zijn inbox staan. Een onjuist antwoord op een
> AVG-verzoek, gegeven omdat het scherm groen licht gaf.

### Het antwoord staat er in allebei de talen

De tekst komt van
[`Antwoordtekst`](../../app/Support/Juridisch/Antwoordtekst.php) en niet uit
het component, en hij wordt in het Nederlands én het Engels tegelijk
opgebouwd. Boven de tekst staan twee vlaggetjes om te kiezen.

**De taal van het antwoord hoort bij de bezoeker en niet bij het scherm.**
Eerst werd deze tekst in Vue met `t()` in elkaar gezet, en liep hij dus mee
met de taal van het portaal. Die staat op Nederlands, want zo werkt de
eigenaar -- dus kreeg een Engelstalige bezoeker een Nederlands antwoord,
tenzij de eigenaar zijn hele beheeromgeving omzette om één mail te kunnen
sturen.

Twee dingen maken dat dit kan zonder `App::setLocale()` aan te roepen, wat
in dit project alleen in `SetLocale` mag:

1. **`__()` neemt een taal als derde argument.** Geen globale toestand, dus
   geen scherm dat stiekem zijn eigen taal kiest.
2. **De Nederlandse zin is in dit project zélf de vertaalsleutel.** "De
   Nederlandse tekst" en "de sleutel om de Engelse mee op te zoeken" zijn
   daarmee hetzelfde ding.

Dat tweede gold nog niet voor de regels uít het logboek: `label()` vertaalde
met de taal van de applicatie, dus stond er "Mislukte login" midden in een
Engelse brief. Daarom hebben `SecurityEventType` en `SecurityOutcome` nu een
`sleutel()` met de kale Nederlandse tekst, en vertaalt `label()` die. De
datum gaat mee via `locale()` op de Carbon-instantie zelf.

Vier tests in `LegalScreenTest` houden dit vast, waaronder
`test_the_english_answer_is_english_in_a_dutch_portal` -- die valt om zodra
iemand de tekst terugzet naar de taal van het scherm.

### Het zoekveld ontsnapt de jokertekens van LIKE

Via [`Zoekterm::patroon()`](../../app/Support/Zoekterm.php), met `escape '!'`
in de query. Dat is hier geen formaliteit: `_` betekent in een LIKE "één
willekeurig teken" en staat in heel veel e-mailadressen, dus
`jan_de_vries@…` vond ook `janXdeYvries@…`.

Je mist er niemand door -- het geeft méér terug, niet minder -- maar het
zijn de logboekregels van een ander, op het ene scherm waar dat het meest
ongewenst is. Drie tests in `LegalScreenTest` houden het vast: de
underscore, het procentteken en het ontsnappingsteken zelf.

### De knop "Kopieer de tekst" is de gedeelde kopieerknop

Niet een eigen knopje op dit scherm, maar
[`CopyButton.vue`](../../resources/js/components/CopyButton.vue) -- dezelfde
als bij de recovery codes en de 2FA-sleutel. Je klikt, het kopieericoon
krimpt weg en er veert een vinkje op met het woord _Gekopieerd_ ernaast, en
na ruim een seconde staat de knop weer zoals hij was.

**Dat vinkje is hier het hele punt.** De eigenaar plakt deze tekst in een
mail die hij aan een bezoeker stuurt; hij moet kunnen zien dát er iets op
zijn klembord staat voordat hij naar zijn mailprogramma wisselt.

> Het stond hier eerst met de hand in, met een kale
> `navigator.clipboard.writeText()`. Die weigert buiten https, dus lokaal
> op een `.test`-adres gebeurde er niets: geen tekst op het klembord, geen
> vinkje, geen foutmelding. `CopyButton` heeft de terugval naar
> `document.execCommand('copy')` al, en toont het vinkje pas ná een
> geslaagde kopie -- anders bevestig je iets wat misschien niet is gebeurd.
> Zie
> [frontend-en-animatie](../architecture/frontend-en-animatie.md#kopiëren-naar-het-klembord).

## Wat er met opzet níet op zit

### Geen knop om een regel uit het beveiligingslogboek te halen

Twee redenen, en elk is op zichzelf voldoende:

1. **Een logboek waar regels uit te halen zijn, is geen logboek.** Het
   bestaat om misbruik te kunnen aantonen en tegenhouden. Zo'n knop is
   bovendien precies wat iemand die binnenkomt zou gebruiken om zijn sporen
   te wissen -- dan beschermt het niemand meer.
2. **Het hoeft niet.** De AVG laat verwijdering weigeren waar de verwerking
   nodig is voor een gerechtvaardigd belang, en het tegengaan van misbruik
   is dat. Het logboek verdwijnt vanzelf na de bewaartermijn.

Dat staat ook **op het scherm** en in de antwoordtekst, want een ontbrekende
knop zonder uitleg is hetzelfde raadsel als een knop die niets doet -- zie
wat daarover is misgegaan in
[pagina-indeling](../architecture/pagina-indeling.md#twee-wegen-naar-hetzelfde-schuifje).

`LegalScreenTest::test_the_security_log_cannot_be_deleted_from_this_screen`
valt om zodra zo'n route er toch komt. Dat is dan een gesprek en geen snelle
fix.

### Geen register van verzoeken

Een lijst met "wie vroeg wanneer wat" zou betekenen dat we de naam en het
adres van iemand die om verwijdering vraagt gaan vastleggen: **nieuwe
persoonsgegevens aanmaken om een verzoek over persoonsgegevens af te
handelen.** De AVG vraagt dat ook niet. Een verzoek komt per mail binnen en
wordt per mail beantwoord.

## Wat er wél weg kan: mislukte mailpogingen

`failed_jobs` is de enige plek in de database waar een bericht van een
bezoeker kan blijven liggen -- met naam, adres en inhoud, veertien dagen
lang -- en hij was nergens in het portaal te zien. Nu staat het aantal op
dit scherm, met een knop om het weg te gooien.

Drie dingen zitten daaraan vast:

- **Achter `2fa.confirm`.** Er verdwijnt een bericht van iemand
  onherstelbaar; dat is precies de handeling waar een verse
  authenticator-code voor bedoeld is.
- **Met een waarschuwing die het echte risico noemt**: deze berichten zijn
  nooit aangekomen en komen daarna ook nooit meer aan. Doe het als iemand om
  verwijdering vraagt, en niet als een bericht alleen is blijven steken
  omdat de mail even niet werkte.
- **Het gaat in het beveiligingslogboek** (`privacy.data_cleared`). Er
  verdwijnen gegevens door een handeling van de beheerder; zonder die regel
  is er later geen manier om te zien dat het is gebeurd.

## De termijn van een maand

De AVG geeft een maand om te antwoorden. Dat is een belofte die de **eigenaar**
moet nakomen -- software kan dat niet voor hem doen. Wat de software wel doet
is zorgen dat hij niet eerst hoeft uit te zoeken waar hij moet kijken.

In de handleiding staat daarom een kaart _"Als een bezoeker zijn gegevens
opvraagt"_ die naar dit scherm wijst.

## Wat waar staat

| Bestand                                                                   | Wat het doet                                                    |
| ------------------------------------------------------------------------- | --------------------------------------------------------------- |
| [`Gegevensoverzicht`](../../app/Support/Juridisch/Gegevensoverzicht.php)  | Het zoeken en de lijst met plekken, uit de instellingen.        |
| [`LegalController`](../../app/Http/Controllers/Admin/LegalController.php) | Het scherm en het weggooien van mislukte mail.                  |
| [`Juridisch.vue`](../../resources/js/pages/admin/Juridisch.vue)           | Wat de eigenaar ziet, inclusief de antwoordtekst.               |
| [`LegalScreenTest`](../../tests/Feature/Admin/LegalScreenTest.php)        | Het zoeken, de rechten, en de twee beslissingen die vastliggen. |

Zie ook [bezoekcijfers](../architecture/bezoekcijfers.md) voor de
privacyverklaring waar dit scherm bij hoort, en
[overzicht-voor-de-eigenaar](overzicht-voor-de-eigenaar.md) voor het scherm
dat uitlegt hoe alles beschermd is.
