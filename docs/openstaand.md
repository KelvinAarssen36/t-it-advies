# Wat er nog open staat

Een korte, eerlijke lijst. Niet alles hoeft meteen af -- maar het moet wel
érgens staan, anders is "dat doen we later" hetzelfde als "dat doen we
niet".

Haal een punt hier weg zodra het klaar is. Een lijst met afgevinkte dingen
erin leest niemand meer.

## Voor livegang

### Beslissen wat we met SSR doen

`config/inertia.php` zegt `ssr.enabled => true`, maar er wordt **nergens
server-side gerenderd**: `npm run build` bouwt de SSR-bundel niet (dat doet
alleen `npm run build:ssr`), en de deploystappen starten er geen proces
voor. Inertia valt dan stil terug op opbouwen in de browser, dus dat werkt
-- maar drie dingen zijn er niet zoals de config suggereert:

- **Elke paginaweergave doet een mislukte verbindingspoging** naar
  `127.0.0.1:13714` voordat hij terugvalt. Op gedeelde hosting kost dat
  latency bij elk bezoek, voor niets.
- **Een bezoeker ziet een lege pagina tot het JavaScript binnen is.** Met
  SSR staat de tekst er meteen.
- **Een crawler zonder JavaScript ziet geen inhoud.** Google voert het uit,
  maar linkvoorbeelden op WhatsApp en LinkedIn en de crawlers van
  AI-diensten doen dat vaak niet.

Nagemeten dat SSR het wél doet: met `npm run build:ssr` en
`php artisan inertia:start-ssr` komt de volledige pagina uit de server. Maar
dat is een proces dat moet blijven draaien, en dat is bij Strato hetzelfde
probleem als de queue worker hieronder.

Twee uitwegen, en het is een keuze:

1. **`SSR_ENABLED=false` zetten** en accepteren dat de site in de browser
   wordt opgebouwd. Dan is de config in elk geval eerlijk en is die
   mislukte verbindingspoging weg.
2. **Hosting waar een proces mag draaien**, en dan `build:ssr` in de deploy
   en de SSR-server ernaast. Dat lost dit én de queue worker in één keer op.

> De vragenlijst is hier niet van afhankelijk: die zet zijn vragen als
> structuurdata in de `<head>` vanuit Blade, dus die staan er ook zonder
> JavaScript. Zie [FAQ](architecture/modules/faq.md).

### De kórtste cron-interval van het hostingpakket uitzoeken

**Dit is het enige punt op deze lijst waar wij niets aan kunnen doen**: het
hangt van het pakket bij Strato af, niet van de code. Er hangen twee dingen
aan, en allebei vallen ze stil in plaats van stuk:

- **De queue worker.** Álle mail van deze site gaat via de wachtrij -- de
  melding aan de eigenaar, de bevestiging aan de bezoeker, de
  beveiligingsmeldingen. Op gedeelde hosting bestaat Supervisor niet, dus de
  worker moet per cron draaien (`queue:work --stop-when-empty`). De interval
  die je daar kiest is de vertraging waarmee een bezoeker zijn
  bevestigingsmail krijgt, dus neem de kortste die mag.
- **De scheduler.** De documentatie gaat uit van `* * * * *`, elke minuut.
  Kan het pakket alleen elke vijf of vijftien minuten, dan vuren de
  opruimtaken op hun vaste tijdstippen (03:05 tot 03:50) onbetrouwbaar en
  groeien de logboektabellen door. Dan moeten die tijdstippen op een raster
  dat wél wordt geraakt.

Het recept en de stappen staan in
[deployment](operations/deployment.md#op-gedeelde-hosting-zoals-strato) en als
stap 10 en 11 in de checklist daar. Er is ook een alarm bijgekomen voor het
geval dit tóch misgaat -- `security:report` meldt werk dat langer dan een
kwartier in de wachtrij ligt, en die melding gaat met opzet niet via de
wachtrij -- maar een alarm is geen oplossing. Zie
[onderhoudstaken](operations/onderhoudstaken.md#werk-dat-in-de-wachtrij-blijft-liggen).

### De privacyverklaring juridisch laten nalezen

Op `/privacy` staat een verklaring die **woord voor woord tegen de code is
nagelopen**: elke bewering erin klopt met wat de software doet, en waar dat
niet zo was is óf de tekst óf de code aangepast. Zie
[bezoekcijfers](architecture/bezoekcijfers.md#wat-de-eerste-versie-van-deze-verklaring-fout-had)
voor de vier onwaarheden die eruit zijn gehaald.

**Dat is iets anders dan juridisch advies.** Laat hem één keer nalezen door
iemand met die kennis voordat de site live gaat. Het is een halfuur werk en
het is precies het soort ding waar je later gedoe mee krijgt.

Het staat ook op het scherm **Beheer → Juridisch**, zodat het niet alleen
hier blijft staan.

### Een adres voor beveiligingsmeldingen

`SECURITY_ALERT_ADDRESS` is leeg. Alles wordt wel vastgelegd, maar niemand
krijgt bericht bij een piek in mislukte inlogpogingen of als de site omvalt.
Het scherm **Instellingen → Veiligheid** zegt dat er nog geen adres is
ingesteld, dus het valt op -- maar het moet vóór de livegang geregeld zijn.
Zie [monitoring](operations/monitoring.md).

### Passkeys op het echte domein nalopen

**Dit is het enige onderdeel dat hier lokaal niet te testen is**, en het
breekt stil als er iets niet klopt: je klikt, er gebeurt niets, en er komt
geen foutmelding in welk logboek dan ook.

De code is nagelopen en in orde -- routes, feature, model, componenten, en
de `Permissions-Policy` blokkeert WebAuthn niet. Wat overblijft hangt aan de
omgeving:

| Wat                                                              | Waarom het stuk gaat                                                                                                                                 |
| ---------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| **https moet aanstaan** op het echte domein                      | Browsers geven `PublicKeyCredential` alleen vrij op een beveiligde verbinding. Zonder slotje is er geen knop.                                        |
| **`APP_URL` precies gelijk aan het adres in de adresbalk**       | `relying_party_id` en `allowed_origins` komen daaruit. Eén letter verschil en elke passkey wordt geweigerd.                                          |
| **Eén vaste host: `atitadvies.nl` óf `www.atitadvies.nl`**       | Een passkey zit vast aan de host. Is de site op allebei bereikbaar zonder doorverwijzing, dan werkt een passkey van de ene niet op de andere.        |
| **`PASSKEYS_USER_HANDLE_SECRET` apart zetten**                   | Zonder die variabele valt hij terug op `APP_KEY`. Wordt die ooit vernieuwd, dan zijn alle bestaande passkeys in één klap onbruikbaar.                |
| **`/.well-known/passkey-endpoints` moet de applicatie bereiken** | Op gedeelde hosting wordt `/.well-known/` soms door de webserver zelf afgehandeld. Doet hij dat, dan vinden wachtwoordmanagers de beheerpagina niet. |

De stappen staan in
[deployment](operations/deployment.md#voor-de-eerste-keer-live). Loop ze na
en maak daarna één echte passkey aan; werkt dat, dan werkt de rest ook.

> **Het is geen blokkade voor de livegang.** Inloggen met wachtwoord en 2FA
> werkt los hiervan. Gaat er iets mis met passkeys, dan is dat vervelend en
> niet fataal.

### De foutpagina van de landing

Het portaal heeft er een; de landing krijgt voorlopig de standaardpagina van
Laravel. Wat die variant anders moet doen staat in
[foutpagina's](architecture/foutpaginas.md).

### De inhoud van de overige onderdelen beheerbaar maken

De [ervaring](architecture/modules/ervaring.md), de
[kop](architecture/modules/kop.md), de
[diensten](architecture/modules/diensten.md), de
[certificaten](architecture/modules/certificaten.md) en de
[statistieken](architecture/modules/statistieken.md) zijn af. Alleen de
werkwijze staat nog in zijn Vue-component; de stappen ernaartoe staan in
[pagina-indeling](architecture/pagina-indeling.md). Op het
indelingsscherm staat bij dat onderdeel "Nog niet te beheren".

### Een opschrift boven de tijdlijn

Sinds het samenvoegen van de koptabellen heeft élk onderdeel een kolom
voor een opschrift -- ook de tijdlijn, die er nooit een had. Het
bewerkvenster van de tijdlijn laat dat veld niet zien, en de seeder vult
het niet, want dat samenvoegen was een verhuizing en geen herontwerp van
de voorpagina.

Wil de klant er later een, dan is het één veld in het bewerkvenster en
`opschriftVerplicht()` op `false` laten staan. Zie
[kopteksten](architecture/kopteksten.md).

### Een venster dat met Escape dichtgaat

Wie het bewerkvenster van de indeling met Escape sluit, verliest zijn
wijzigingen zonder melding. Bewust nog niet gebouwd: het venster is een
afgebakende handeling met een zichtbare knop "Annuleren", en een
bevestiging om een bevestiging heen maakt het niet veiliger. Zodra er meer
schermen met een bewerkvenster zijn en de inhoud er zwaarder in wordt, is
het de moeite waard om het één keer goed te doen.

### Een sitemap

Pas zinvol zodra er pagina's zijn, en die komen uit de modules.

## Nog te beslissen

### Back-ups

**Dit is een open vraag, geen taak.** Er is nu niets: geen pakket, geen
geplande taak, niets in de documentatie.

Zodra de klant inhoud invoert staat die alleen in de database. Gaat daar
iets mis, dan is het weg. Maar wat verstandig is hangt af van de hosting:
veel partijen maken zelf al dagelijkse snapshots, en daar nog een eigen
regeling naast zetten levert twee halve oplossingen op die allebei niet
worden gecontroleerd.

Drie richtingen, als het zover is:

1. **De hosting doet het.** Dan hoeven wij niets te bouwen, maar moeten we
   wél vastleggen hoe vaak, hoe lang ze blijven staan en hoe je terugzet --
   en dat één keer geprobeerd hebben.
2. **Zelf, met `spatie/laravel-backup`.** Database en uploads naar een
   externe opslag, met een melding als het misgaat.
3. **Allebei**, waarbij de onze de "ik heb per ongeluk alles verwijderd"-kant
   dekt en die van de hosting de "de server is weg"-kant.

Wat je ook kiest: een back-up die nooit is teruggezet is geen back-up.

## Wat hier bewust níet op staat

- **Een foutmelder van een externe dienst** (Sentry, Flare). Er is nu een
  eigen crashmelding per e-mail; zie [monitoring](operations/monitoring.md).
  Dat is genoeg tot het aantal meldingen onhandelbaar wordt.
- **Een foutenscherm in het portaal.** Bewust niet: stacktraces en
  ontwikkelaarstaal horen niet bij de eigenaar van de website, en met één
  rol ziet hij alles wat in het menu staat. Waar je dan wél kijkt staat in
  [monitoring](operations/monitoring.md).
- **Een Content-Security-Policy.** De andere beveiligingskoppen staan er;
  waarom de CSP apart aandacht verdient staat in
  [headers](security/headers.md).
