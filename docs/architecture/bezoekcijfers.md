# Bezoekcijfers

Hoeveel mensen de website bekijken, waar ze vandaan komen en waarmee ze
kijken. In het portaal onder **Beheer → Bezoekers**.

| Onderdeel                     | Waar                  | Waar het vandaan komt                    |
| ----------------------------- | --------------------- | ---------------------------------------- |
| De cijfers                    | Beheer → Bezoekers    | `site_day_totals`, `site_day_dimensions` |
| De verklaring aan de bezoeker | `/privacy` op de site | De code; zie hieronder                   |

> **Lees eerst het stuk over de wet.** Dit onderdeel is zo gebouwd dat er
> geen cookiebanner nodig is, en dat is geen bijkomstigheid maar de
> ontwerpeis. Elke uitbreiding moet daarbinnen blijven, en wat daarvoor
> nodig is staat hieronder.

## Waarom hier geen cookiebanner bij hoort

Er spelen **twee verschillende wetten** en ze gaan over iets anders. Dat
door elkaar halen is de meest gemaakte fout in dit onderwerp.

| Wet                                                                       | Gaat over                                                    | Ons antwoord                                                                                                                                                                          |
| ------------------------------------------------------------------------- | ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Cookiewet** (art. 11.7a Telecommunicatiewet, uit de ePrivacy-richtlijn) | Iets **opslaan of uitlezen** op het apparaat van de bezoeker | Wij doen dat niet. Geen cookie, geen `localStorage`, geen script dat de browser bevraagt. De wet is dus niet van toepassing, en een banner heeft niets om toestemming voor te vragen. |
| **AVG**                                                                   | Het **verwerken** van persoonsgegevens                       | Minimaal, met gerechtvaardigd belang als grondslag en een privacyverklaring die vertelt wat er gebeurt.                                                                               |

**De sessiecookie van Laravel staat er wél**, en die is technisch
noodzakelijk: hij onthoudt de taalkeuze en beveiligt het contactformulier
tegen CSRF. Dat soort cookies is vrijgesteld van toestemming. Hij staat in
de privacyverklaring, en dat is genoeg.

> **Wat dit betekent voor een uitbreiding.** Wil je ooit iets meten dat een
> cookie, een `localStorage`-sleutel of een vingerafdruk van de browser
> nodig heeft -- hoe lang iemand op de pagina blijft, bijvoorbeeld, of welke
> knop hij aanklikt met een identiteit eraan -- dan verandert dit onderdeel
> van karakter en is die banner er alsnog nodig. Dat is dan een gesprek met
> de klant en geen technische beslissing.

## Hoe bezoekers geteld worden zonder IP-adressen te bewaren

Bij een bezoek wordt één onomkeerbare code gemaakt:

```
sha256( dagzout + ip-adres + browserkenmerk )
```

Die code gaat in `site_visitor_codes`, een tabel die niets anders doet dan
bijhouden "is deze bezoeker vandaag al geteld". Is de code nieuw, dan gaat
`site_day_totals.visitors` één omhoog.

**Het dagzout is een willekeurig geheim dat één dag meegaat.** Bij het
eerste bezoek na middernacht komt er een nieuw zout, en in diezelfde
handeling verdwijnen de codes én het zout van de dagen ervoor. Daarmee is
de code van gisteren naar niemand meer te herleiden -- ook niet door ons,
want het ingrediënt waarmee je hem zou kunnen narekenen bestaat niet meer.

Wat overblijft is een getal: _"op 1 oktober: 47 bezoekers, 71 weergaven"_.

### Het opruimen hangt aan het zout en niet aan de cron

Dat is een bewuste keuze en het is het onthouden waard: **een belofte aan de
bezoeker die afhangt van een cronregel die iemand vergeet, is geen
belofte.** Nu dwingt het systeem zichzelf -- er kan geen zout voor een
nieuwe dag ontstaan zonder dat de oude codes in dezelfde beweging weggaan.

De geplande taak `bezoek:prune-codes` is een **tweede** slot, voor de dagen
dat er niemand langskomt: dan blijven de codes van de laatste bezoeker
staan tot de volgende komt. Die taak heeft met opzet **geen instelbare
bewaartermijn** -- die is één dag, en een knop om er dertig van te maken is
een knop om de belofte te breken.

### Over een langere periode zijn het bezoeken, geen mensen

Omdat het zout elke nacht wisselt, telt iemand die in een maand drie keer
langskomt als drie bezoekers. Dat is de prijs van deze aanpak en hij is
bewust betaald. Het scherm zegt dat er met zoveel woorden bij, want een
cijfer dat je verkeerd leest is erger dan geen cijfer.

## Wat er wordt opgeslagen, en wat niet

| Wel                                            | Niet                                   |
| ---------------------------------------------- | -------------------------------------- |
| Datum en een aantal                            | IP-adressen                            |
| Verwijzer: alleen de **host** (`linkedin.com`) | De volledige verwijzende URL           |
| Apparaatsoort: mobiel / tablet / desktop       | Het browserkenmerk zelf                |
| Taal: `nl` of `en`                             | Land, schermafmetingen, muisbewegingen |
| Aantal berichten via het formulier             | Iets uit die berichten                 |

Die tweede regel is belangrijker dan hij lijkt: **een verwijzende URL kan
zoektermen of persoonsgegevens in zijn queryparameters hebben staan.**
`parse_url` met `PHP_URL_HOST` gooit al het andere weg vóórdat er iets wordt
opgeslagen, en `VisitorStatsTest` legt dat vast.

En `site_visitor_codes` heeft met opzet **geen `timestamps()`**. Een tijdstip
per bezoeker is precies het gegeven dat een code weer naar een persoon toe
brengt: wie om 09:14 op de site was, is een veel kleinere groep dan wie er
die dag was.

## Tellers, geen rij per bezoek

Dit is het belangrijkste architectuurbesluit, met twee redenen die beide op
zichzelf voldoende zijn:

1. **Privacy.** Een logboek met een rij per bezoek is een tijdlijn van wie
   wanneer op de site was. Tellers zijn dat niet.
2. **Gedeelde hosting.** Deze tabellen groeien met 365 rijen per jaar in
   plaats van met elk bezoek. Er valt niets op te ruimen en niets te
   archiveren.

### Hoe er geteld wordt zonder botsingen

Niet met `upsert()` -- dat kan geen `teller + 1` uitdrukken -- en niet met
platte SQL, want `INSERT ... ON DUPLICATE KEY UPDATE` is MySQL-taal en de
tests draaien op SQLite. Dan zou je iets anders testen dan er in productie
gebeurt.

In plaats daarvan twee stappen die elk op zichzelf veilig zijn:

```php
// 1. Optellen bij een rij die al bestaat.
DB::table($tabel)->where($sleutels)->increment($kolom);

// 2. Alleen als dat niets raakte: aanmaken, en de botsing opvangen.
DB::table($tabel)->insertOrIgnore([...$sleutels, $kolom => 1]);
```

In het gewone geval is dat één statement. De database bewaakt de unieke
sleutel, niet wij -- dus twee bezoekers tegelijk kunnen elkaar niet in de
weg zitten.

Per bezoek zijn het vijf à zes van die kleine statements. **Bewust synchroon
en niet via de queue:** de queue is juist het kwetsbare deel op gedeelde
hosting, en een bezoekteller die stilvalt omdat een worker niet draait merk
je pas als de grafiek al een week plat is.

## Wat niet meetelt

| Geval                             | Waarom                                                                                                          |
| --------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| Een ingelogde gebruiker           | De eigenaar die zijn eigen site bekijkt is geen bezoeker; anders meet hij zichzelf zodra hij aan het werk gaat. |
| Een bot                           | Dan gaat de grafiek over zoekmachines.                                                                          |
| Een vooruit geladen pagina        | De browser haalt soms iets op dat je nog niet hebt geopend. Drie koppen, want het is drie keer anders genoemd.  |
| Een verzoek zonder browserkenmerk | Dat is geen browser.                                                                                            |
| Alles behalve `GET`               | Een POST is een handeling en geen bezoek.                                                                       |
| Een antwoord dat geen 200 is      | Een 404 of een doorverwijzing na het wisselen van taal is geen bezoek.                                          |
| Het portaal, de webhooks, `/up`   | `TelBezoek` staat op de publieke route en niet globaal.                                                         |

De middleware telt **ná** het antwoord en **eet zijn eigen fouten op**: gaat
er in de teller iets mis, dan merkt de bezoeker daar niets van. Een gemist
bezoekcijfer is te overzien; een landingspagina die omvalt omdat de teller
struikelde niet. De fout gaat wel naar de crashmelder, dus hij verdwijnt
niet stil.

### Het slot tegen verwijzerspam

Een oude truc: duizenden verzoeken met een verzonnen `Referer`, om je site
in andermans statistieken te krijgen. Zonder grens schrijft dat de tabel vol
en maakt het het scherm onleesbaar.

Daarom bewaren we per dag hoogstens veertig verschillende onbekende
verwijzers; wat erboven komt wordt `overig`. Die grens wordt alleen
nagekeken als een verwijzer die dag nog niet voorkwam -- bij een bekende
kost het dus geen extra vraag aan de database.

## De privacyverklaring

Een publieke pagina op `/privacy`, in beide talen, met een link in de voet.

**De tekst staat in de code en niet in de database.** Een
privacyverklaring beschrijft wat de software doet; zou de eigenaar hem zelf
kunnen aanpassen, dan kan hij een verklaring neerzetten die niet meer klopt
-- en een verklaring die niet klopt is erger dan geen verklaring.

> **Bij elke wijziging aan wat er gemeten of bewaard wordt, hoort deze
> pagina in dezelfde wijziging mee.** Dat is geen nette gewoonte maar de
> kern van het onderdeel: dit is de belofte, en de code eronder hoort hem na
> te komen.

Het e-mailadres en de bewaartermijn van het beveiligingslogboek komen van de
server, uit de instellingen die ze ook echt bepalen. Een getal dat met de
hand in een juridische tekst staat, klopt tot de dag dat iemand die
instelling wijzigt.

**Nog te doen vóór livegang:** laat deze tekst nalezen door iemand met
juridische kennis. Hij beschrijft precies wat de software doet en de basis
is solide, maar dat is wat anders dan juridisch advies.

### Wat de eerste versie van deze verklaring fout had

De eigenaar vroeg om alles na te lopen -- _"zorg dat alles wat je daar op
die privacyverklaring zet wel echt waar is, dus dat je niet liegt"_ -- en
dat leverde vier onwaarheden op. Ze staan hier omdat ze alle vier een
patroon hebben: **een verklaring opschrijven vanuit wat je denkt dat de
software doet, in plaats van vanuit de code.**

| Wat er stond                                                   | Waarom het niet waar was                                                                                                                                                                           | Wat er is gebeurd                                                                                                                                                                           |
| -------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| "Je bericht komt niet in een database op deze website terecht" | Het contactformulier verstuurt via de **queue**, en die staat op de `database`-verbinding. Het bericht staat er dus kort wél in -- en bij een mislukte verzending veertien dagen in `failed_jobs`. | **Tekst aangepast:** de wachtrij staat er nu met zoveel woorden bij. De queue zelf blijft; een bericht kwijtraken omdat de mailprovider even hikt is erger dan een extra alinea.            |
| "Alleen metadata, geen mailinhoud" (over het mailoverzicht)    | Het onderwerp van een contactbericht is "Contactformulier: _wat de bezoeker typte_". Dat is zijn eigen tekst, honderd tachtig dagen in onze database.                                              | **Code aangepast**, niet de tekst. Een mailable kan nu met `logboekOnderwerp()` een neutraal onderwerp voor het logboek opgeven; de eigenaar houdt het volledige onderwerp in zijn postvak. |
| "Een technisch noodzakelijke cookie"                           | Het zijn er twee: de sessiecookie en het CSRF-token.                                                                                                                                               | **Tekst aangepast**, met wat elk van de twee doet.                                                                                                                                          |
| "Cloudflare controleert het formulier"                         | Staat er geen Turnstile-sleutel, dan wordt er geen enkel verzoek naar Cloudflare gedaan. Een derde partij noemen die er niet is, is net zo fout als er een verzwijgen.                             | De alinea staat er **alleen als de sleutel is ingevuld**; `PrivacyController` geeft dat door.                                                                                               |

Twee dingen die er bij diezelfde ronde bij zijn gekomen omdat ze ontbraken:

- **Een mislukte spamcontrole komt in het beveiligingslogboek**, met het
  IP-adres van de bezoeker (`SecurityEventType::TurnstileFailed`). Dat is
  een bezoeker die in een logboek belandt, dus dat hoort erin te staan.
- **De bewaartermijn van het mailoverzicht** stond er niet, terwijl een
  verstuurd bericht daar een spoor achterlaat.

En één bewering is juist **sterker** geworden, omdat hij bleek te kloppen:
de publieke pagina haalt alles van onze eigen server. Geen Google Fonts,
geen CDN, geen sociale knoppen -- nagemeten met
`curl -s <site> | grep -oE 'https?://[a-z.-]+' | sort -u`, en daar komt
alleen het eigen domein uit. Dus staat er nu: _"Bekijk je deze site alleen,
dan gaat er niets naar een andere partij."_

> **De les voor de volgende keer:** schrijf geen regel in die verklaring
> zonder de code ernaast. Vier van de veertien alinea's waren niet waar, en
> dat waren allemaal dingen die "logisch" leken.

### En wat er daarna alsnog ontbrak: het logbestand van de webserver

De eigenaar vroeg later of iemand die alleen de pagina bekijkt echt niets
achterlaat, en of dat ook zo op de verklaring stond. Het antwoord was
grotendeels ja, met één gat: **het toegangslogbestand van de webserver.**

Elke webserver schrijft van elk verzoek het IP-adres in een logbestand. Dat
gebeurt bij een bezoek aan deze site net zo goed, alleen een laag onder
onze code -- bij de partij waar de server staat. Daardoor is het het
makkelijkste gegeven om te vergeten: er is geen regel code die eraan
herinnert.

Dat is precies waarom het dezelfde fout is als de vier hierboven, en het
loopt ook op hetzelfde patroon vast: **de verklaring beschreef wat onze
code doet, in plaats van wat er bij een bezoek gebeurt.** Die twee zijn
niet hetzelfde.

De verklaring noemt het nu in een eigen alinea, en in het overzicht met
bewaartermijnen staat het als enige regel **zonder getal**. Dat is met
opzet: hoe lang die bestanden blijven staan bepaalt de hostingpartij en
niet wij, en een termijn verzinnen is erger dan hem weglaten. Hetzelfde
geldt voor de antwoordtekst op het scherm
[Juridisch](../security/verzoeken-van-bezoekers.md): die zei "wij bewaren
geen IP-adressen" en zegt nu "wij bewaren zelf geen IP-adressen", met het
logbestand als uitzondering erbij.

`PrivacyPageTest::test_the_server_log_is_disclosed` houdt het vast. Die
test leest het component en niet de HTML, want de tekst wordt pas in de
browser opgebouwd.

> **Nog te doen vóór livegang:** kijk na wat de hostingpartij in die logs
> zet en hoe lang ze blijven staan. Is dat bekend en vast, dan kan de
> termijn er alsnog bij -- maar alleen als hij klopt.

### En de grootste wijziging: de module Contact

Hierboven staan correcties van **fouten**. Dit is iets anders: een correctie
van een **keuze**, en daarmee het duidelijkste voorbeeld van waarom de regel
bovenaan bestaat.

Drie alinea's zeiden dat een bericht uit het contactformulier de website
verliet zodra het verstuurd was. Dat was waar -- tot
[de module Contact](modules/contact.md), die aanvragen een jaar in het
portaal bewaart zodat de eigenaar kan terugzoeken wie hem wanneer benaderde.

| Wat er stond                                                               | Wat er nu staat                                                                                   |
| -------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| "Daarna is het weg uit de website en staat het alleen nog in onze mailbox" | Hoe lang het blijft staan, en dat alleen de eigenaar erbij kan, achter een wachtwoord en een code |
| "Op de website zelf alleen tot het verstuurd is"                           | De termijn, uit `config('site.contact.retention_days')`                                           |
| _(niets over een bevestiging aan de bezoeker)_                             | Dat hij er zelf een krijgt, en dat zijn adres daardoor als ontvanger in het mailoverzicht staat   |

Die laatste is het soort gevolg dat je makkelijk mist: de nieuwe mail maakte
een bestaande alinea over het mailoverzicht onwaar, terwijl die alinea zelf
niet veranderde.

`PrivacyPageTest::test_the_statement_no_longer_claims_the_message_leaves`
houdt het vast. Zou iemand de oude zin terugzetten, dan valt die test om.

## Twee valkuilen voor productie

### 1. Een proxy ervoor, en iedereen is één bezoeker

Komt er ooit een Cloudflare of een loadbalancer voor de site, dan is
`REMOTE_ADDR` het adres van die proxy en niet van de bezoeker. Het echte
adres staat dan in `X-Forwarded-For`, en Laravel negeert die kop zolang er
geen vertrouwde proxy is ingesteld.

Gevolg: elke bezoeker krijgt hetzelfde adres en dus dezelfde code -- voor
altijd **één bezoeker per dag**. Er valt niets om; er staat alleen overal
een verkeerd getal. Hetzelfde geldt voor het beveiligingslogboek en de
snelheidsgrenzen, en daar is het erger.

Vul dan `TRUSTED_PROXIES` met de adressen van die proxy. **Niet met `*`:**
`X-Forwarded-For` is door de afzender zelf te verzinnen, dus dan kiest
iedereen zijn eigen IP-adres. Zie `config/security.php` en
[deployment](../operations/deployment.md).

### 2. De tijdzone

`app.timezone` staat op UTC -- de verstandige keuze voor tijdstempels. Maar
een dagcijfer hoort te lopen van middernacht tot middernacht in de tijd van
het bedrijf, niet van 02:00 tot 02:00. Daarom gaat de dagbepaling via
`config('site.timezone')`, standaard `Europe/Amsterdam`.

Dat is **niet** dezelfde instelling als de klok op het dashboard: die volgt
de persoonlijke voorkeur van de gebruiker. Zie [dashboard](dashboard.md).

### En: zet er geen paginacache voor

Een volledige cache vóór de applicatie betekent dat de middleware niet meer
draait. De grafiek blijft dan plat zonder dat er iets zichtbaar stuk is.

## Wat waar staat

| Bestand                                                                       | Wat het doet                                              |
| ----------------------------------------------------------------------------- | --------------------------------------------------------- |
| [`Bezoekteller`](../../app/Support/Bezoek/Bezoekteller.php)                   | Het tellen, het zout, het opruimen en wat niet meetelt.   |
| [`Bezoekcijfers`](../../app/Support/Bezoek/Bezoekcijfers.php)                 | De leeskant: totalen, de reeks per dag, de vergelijking.  |
| [`TelBezoek`](../../app/Http/Middleware/TelBezoek.php)                        | De middleware op de publieke route.                       |
| [`BezoekDimensie`](../../app/Enums/BezoekDimensie.php)                        | De drie uitsplitsingen.                                   |
| [`VisitorController`](../../app/Http/Controllers/Admin/VisitorController.php) | Het beheerscherm.                                         |
| [`Bezoekers.vue`](../../resources/js/pages/admin/Bezoekers.vue)               | Wat de eigenaar ziet, inclusief de uitleg bij de cijfers. |
| [`BezoekGrafiek.vue`](../../resources/js/components/admin/BezoekGrafiek.vue)  | De staafjes per dag, zonder grafiekbibliotheek.           |
| [`PrivacyController`](../../app/Http/Controllers/PrivacyController.php)       | De verklaring, met het adres uit de instellingen.         |
| [`public/Privacy.vue`](../../resources/js/pages/public/Privacy.vue)           | De tekst zelf.                                            |
| [`PruneVisitorCodes`](../../app/Console/Commands/PruneVisitorCodes.php)       | Het tweede slot op het opruimen.                          |
| [`VisitorStatsTest`](../../tests/Feature/Admin/VisitorStatsTest.php)          | Het tellen, en de twee beloften uit de verklaring.        |
| [`VisitorScreenTest`](../../tests/Feature/Admin/VisitorScreenTest.php)        | Het scherm, de perioden en de vergelijking.               |
| [`PrivacyPageTest`](../../tests/Feature/PrivacyPageTest.php)                  | Dat de verklaring er is, klopt en te vinden is.           |

## Wat er bewust niet in zit

- **Geen land.** Vraagt een GeoIP-database (extra gewicht op gedeelde
  hosting) en is meer persoonsgegeven dan het oplevert.
- **Geen klikken op knoppen.** Dat vraagt JavaScript op de publieke site.
  Kan later, en kan cookieloos blijven -- maar het is geen teller meer en
  dus een eigen afweging.
- **Geen uren van de dag.** Zou kunnen met een kolom erbij; er is nog geen
  vraag naar.
- **Geen pagina's.** De site is één pagina met ankers, dus "welke pagina's
  bekijken ze" levert niets op. Komt er een tweede pagina bij, dan is dit
  een vierde `kind` in `site_day_dimensions`.
- **Geen cookiebanner** -- en dat is de bedoeling, zie bovenaan.
