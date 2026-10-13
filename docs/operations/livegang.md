# Livegang: wat jij moet doen

**Dit is de menselijke lijst.** Hier staat alleen wat een mens moet
uitzoeken, aanvragen, beslissen of op het echte domein controleren -- dingen
die niet in code op te lossen zijn.

De technische hoe-doe-ik-dat staat in [deployment](deployment.md); deze
lijst verwijst ernaar en herhaalt hem niet, want twee lijsten over hetzelfde
gaan vroeg of laat uiteen lopen.

> Hier stond "een genummerde lijst van achttien stappen". Dat getal klopte
> toen het werd opgeschreven en twee uur later niet meer, omdat er vier
> stappen bij kwamen. Een aantal in proza is een datum zonder houdbaarheid;
> daarom staat het er niet meer.

> **Werk hem van boven naar beneden af.** De volgorde is niet willekeurig:
> DNS heeft dagen nodig, en zonder domein kun je geen Turnstile-widget voor
> het juiste adres maken.

---

## A. Wat je bij een andere partij moet regelen

Dit kost wachttijd. Begin hier, ruim voor de lanceerdatum.

| #   | Wat                                                                                       | Waarom het niet kan wachten                                                                                                                                                                                                            |
| --- | ----------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A1  | **Domein wijzen naar de hosting**                                                         | Alles hieronder hangt eraan.                                                                                                                                                                                                           |
| A2  | **Kies één vaste host: `atitadvies.nl` óf `www.atitadvies.nl`**, met een 301 van de ander | Dit is geen smaak. Een passkey zit vast aan de host waar hij is gemaakt; is de site op allebei bereikbaar, dan werkt een passkey van de ene niet op de andere en lijkt het alsof hij zomaar kwijt is. Dezelfde host moet in `APP_URL`. |
| A3  | **DNS: SPF, DKIM en DMARC**                                                               | DNS heeft tijd nodig om door te werken. Doe dit het eerst. Zie [e-mailauthenticatie](../security/e-mailauthenticatie.md).                                                                                                              |
| A4  | **Resend: account, domein verifiëren, API-sleutel, webhook**                              | Zonder dit verstuurt de site geen enkele mail. De webhook komt op `https://<domein>/webhooks/resend`.                                                                                                                                  |
| A5  | **Cloudflare Turnstile: widget voor het productiedomein**                                 | Levert twee sleutels. Zonder de geheime sleutel weigert de applicatie in productie bewust élk beschermd formulier -- dat is ontwerp, geen storing.                                                                                     |

---

## B. Wat je moet uitzoeken bij Strato

**Dit zijn vragen aan je hostingpakket, geen instellingen in de code.** De
antwoorden bepalen wat er daarna moet gebeuren.

### B1. Wat is de kórtste cron-interval die je pakket toestaat?

Hier hangen twee dingen aan, en ze vallen allebei _stil_ in plaats van stuk
-- dat maakt ze zo vervelend.

- **De wachtrij.** Álle mail van deze site gaat via de wachtrij. Op gedeelde
  hosting bestaat Supervisor niet, dus de worker draait per cron. De
  interval die je kiest is de vertraging waarmee een bezoeker zijn
  bevestigingsmail krijgt. Neem de kortste die mag.
- **De scheduler.** De documentatie gaat uit van elke minuut. Kan je pakket
  alleen elke vijf of vijftien minuten, dan vuren de opruimtaken op hun
  vaste tijdstippen (03:05 tot 03:50) onbetrouwbaar en groeien de
  logboektabellen door. Zet die tijdstippen dan op een raster dat wél wordt
  geraakt; zie [onderhoudstaken](onderhoudstaken.md).

### B2. Mag de server naar buiten (uitgaand https)?

Sommige pakketten staan dat niet toe. Kan het niet, dan blijft de website
gewoon werken en geeft de vertaalknop een nette foutmelding -- maar zet hem
dan liever uit met `TRANSLATE_ENABLED=false`, zodat hij niet elke keer
teleurstelt.

> Let op dat dit over de vertaaldienst gaat en niet over álles. Mail loopt
> via Resend en heeft dezelfde uitgaande verbinding nodig; staat die dicht,
> dan verstuurt de site geen enkele mail.

### B3. Mag er een proces blijven draaien?

Vrijwel zeker niet op gedeeld. Mag het wél, dan is dat de moeite waard: het
lost de wachtrij én server-side rendering in één keer op. Zie
[beslissing 018](../decisions/README.md).

### B4. Wat staat er in het toegangslogboek, en hoe lang blijft dat staan?

De privacyverklaring noemt dat logbestand **zonder termijn**, omdat wij die
niet bepalen. Is het antwoord bekend en vast, dan kan die termijn er alsnog
bij -- maar alleen als hij klopt.

### B5. Staat `upload_max_filesize` ruim genoeg?

Op Strato staat hij standaard krap. Laat je dat staan, dan kan de klant zijn
logo niet kwijt **en ziet hij niet waarom**. Er staat een meetcommando in
[deployment](deployment.md#de-php-instellingen-voor-uploads).

---

## C. Wat je moet beslissen en invullen

Dit zijn waarden die alleen jij kunt kiezen. Ze horen in `.env` op de
server en **nergens anders** -- niet in git, niet in een bericht, niet in
een logboek.

| Waarde                        | Beslissing                                                                                                                                         |
| ----------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| `PORTAL_ACCOUNT_PASSWORD`     | Het wachtwoord van de klant, vóór de eerste seed. Zet er daarna meteen 2FA op.                                                                     |
| `SECURITY_ALERT_ADDRESS`      | Naar welk adres gaan beveiligingsmeldingen? Nu leeg: alles wordt wél vastgelegd, maar niemand krijgt bericht.                                      |
| `PASSKEYS_USER_HANDLE_SECRET` | Eén keer genereren en laten staan. Laat je hem leeg, dan hangt hij aan `APP_KEY` -- en dan sneuvelen álle passkeys zodra die ooit wordt vernieuwd. |
| `APP_KEY`                     | Genereren bij installatie. Daarna nooit meer aanraken.                                                                                             |
| `TRANSLATE_EMAIL`             | Zonder adres telt de vertaaldienst per IP-adres, en dat deel je op gedeelde hosting met alle andere sites op die server.                           |
| `APP_URL`                     | Het echte domein met `https://`, en de host uit A2. Passkeys halen hier hun herkomst uit. Let op: **atitadvies.nl**, niet `t-it-advies.nl`.        |

> **Ik hoef die waarden niet te zien en wil ze ook niet.** Geheimen horen in
> `.env` op de server. Zet ze nergens anders neer.

---

### B6. Mag je op de server `composer` en `npm` draaien?

De deploystappen gaan uit van `composer install` en `npm run build` op de
server. Op gedeelde hosting staat vaak geen van beide. Kan het niet, dan
bouw je lokaal en zet je `vendor/` en `public/build/` mee over -- en dan
hoort `public/build/` dus **niet** in `.gitignore` van de map die je
uploadt.

Vraag dit vóór de lancering na; het is het verschil tussen een deploy van
vijf minuten en een avond puzzelen.

---

## C2. Twee instellingen die je makkelijk vergeet

| Waarde               | Waarom                                                                                                                                                                                                                      |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `APP_ENV=production` | Hier hangt meer aan dan alleen de foutpagina's: de applicatie dwingt in productie https af en weigert formulieren zonder Turnstile-sleutel. Staat hij op `local`, dan zijn die beschermingen uit zonder dat iets dat meldt. |
| `APP_DEBUG=false`    | Met `true` toont een fout de inhoud van je `.env` aan de bezoeker.                                                                                                                                                          |

> **Eén vaste host is hier belangrijker dan het lijkt.** Laravel vertrouwt
> standaard elke `Host`-kop, en wachtwoordherstel staat in dit project aan.
> Komt een verzoek binnen met een vreemd domein in die kop, dan kan de
> herstellink in de mail naar dat domein wijzen. Punt A2 -- één host, de
> rest doorverwijzen bij je hostingpartij -- sluit dat af. Wil je het ook
> in de applicatie dichtzetten, dan is dat `->trustHosts(at: ['atitadvies.nl'])`
> in `bootstrap/app.php`; dat staat er nu niet in.

---

## D. De privacyverklaring laten nalezen

Op `/privacy` staat een verklaring die **woord voor woord tegen de code is
nagelopen**: elke bewering erin klopt met wat de software doet, en waar dat
niet zo was is óf de tekst óf de code aangepast.

**Dat is iets anders dan juridisch advies.** Laat hem één keer nalezen door
iemand met die kennis voordat de site live gaat. Het is een halfuur werk en
het is precies het soort ding waar je later gedoe mee krijgt.

Het staat ook op het scherm **Beheer → Juridisch** in het portaal, zodat het
niet alleen hier blijft staan.

---

## E. Wat de klant zelf moet invullen

**De site gaat niet leeg live, maar ook niet af.** Een aantal onderdelen is
geseed met tekst die er vooral staat zodat het blok niet leeg is -- een leeg
blok verdwijnt namelijk van de website. `ServiceSeeder` zegt het met zoveel
woorden: _"een startpunt en geen definitieve tekst."_

Loop vóór de lancering met hem door:

| Onderdeel                                  | Wat er nu staat                                                                                                                                      |
| ------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Kop** bovenaan                           | Een geseede tekst. Dit is het eerste dat een bezoeker leest.                                                                                         |
| **Diensten**                               | Drie voorbeelddiensten: Advies, Realisatie, Beheer.                                                                                                  |
| **Kerngegevens**                           | **Leeg.** Wordt bewust niet geseed -- een verzonnen KvK-nummer op een echte site is een onwaarheid. Zolang hij niets invult staat de strook er niet. |
| **Voettekst**                              | **Leeg.** Geen telefoonnummer, geen plaats, geen KvK. Zelfde reden.                                                                                  |
| **Over mij, Werkwijze, Projecten, Vragen** | Controleren of de tekst van hém is en niet van ons.                                                                                                  |

> De kerngegevens en de voettekst zijn de twee nieuwste modules en de enige
> die bewust leeg beginnen. Vergeet je ze, dan mist de site precies het
> praktische stuk waar hij voor bedoeld was.

---

## F. Wat alleen op het echte domein kan

Deze vier zijn lokaal niet te testen. Doe ze **direct na de lancering**, in
deze volgorde.

### F1. Vul het contactformulier één keer echt in

En kijk of **beide** mails aankomen: de melding op het publieke adres en de
bevestiging op het adres dat je invulde.

Dit is de stap die betrapt dat de wachtrij-worker niet draait, en dat is de
enige storing in deze applicatie **die er van buiten volledig gezond
uitziet**. Blijft er iets in `jobs` staan, dan is het dat.

```bash
php artisan tinker --execute="echo DB::table('jobs')->count();"
```

### F2. Maak één echte passkey aan

Zonder https geeft de browser `PublicKeyCredential` niet vrij, dus lokaal
verschijnt de knop niet eens. Ga naar **Instellingen → Beveiliging**, maak
een passkey en log er één keer mee in.

Lukt het niet, dan is het bijna altijd `APP_URL`, de keuze uit A2, of
`PASSKEYS_USER_HANDLE_SECRET`. Het is **geen blokkade**: inloggen met
wachtwoord en 2FA werkt er los van.

### F3. Controleer `/.well-known/passkey-endpoints`

```bash
curl -s https://<domein>/.well-known/passkey-endpoints
```

Hoort JSON te geven en geen 404 van de webserver. Op gedeelde hosting wordt
`/.well-known/` soms door de server zelf afgehandeld -- dat is de map waar
ook de certificaatcontrole van Let's Encrypt doorheen gaat. Vangt hij alles
af, dan vinden wachtwoordmanagers de beheerpagina niet.

### F4. Pas het adres in `robots.txt` aan als je `www` koos

`public/robots.txt` noemt de sitemap met een volledig adres, want de
standaard eist dat. Dat adres staat er hardgecodeerd in. Koos je bij A2 voor
`www`, dan moet die ene regel mee.

> **Waarom dat geen route is geworden**, zodat hij het domein vanzelf volgt:
> Google behandelt een `robots.txt` die een 5xx geeft als "crawl voorlopig
> helemaal niets". Eén storing zou dan de hele site uit de index kunnen
> halen. Een statisch bestand staat er ook als de applicatie omvalt. Zie
> [sitemap](../architecture/sitemap.md).

---

## G. En meteen daarna: één back-up, en één keer terugzetten

Er is een hele [back-upmodule](back-ups.md), maar hij speelt nog geen rol in
de lancering -- en dat is precies hoe je erachter komt dat hij niet werkt op
het moment dat je hem nodig hebt.

Maak dus direct na de livegang, zodra de klant zijn inhoud erin heeft staan:

1. **Eén back-up** via Beheer → Back-ups.
2. **Download hem** en bewaar hem ergens anders dan op die server.
3. **Zet hem één keer terug** en kijk of alles er nog staat.

> Dat derde punt is het hele punt. `back-ups.md` zegt het zelf: _"een
> back-up die nooit is teruggezet is geen back-up."_

Spreek daarna af hoe vaak hij er een maakt, en zoek uit of Strato zelf ook
snapshots maakt -- twee halve regelingen die geen van beide worden
gecontroleerd is slechter dan één hele.

---

## Zie ook

- [Deployment](deployment.md) -- de technische stappen, de
  omgevingsvariabelen en de volledige checklist van achttien punten
- [Openstaande punten](../openstaand.md) -- wat er bewust nog niet af is
- [Monitoring](monitoring.md) -- waar je kijkt als er iets misgaat
- [Back-ups](back-ups.md)
