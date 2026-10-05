# Lokale setup

De ontwikkelomgeving is **WSL2 (Ubuntu 24.04) met Valet Linux**, benaderd
vanuit Windows. Dat heeft één eigenaardigheid die je moet kennen; die staat
hieronder bij "hosts-bestand".

## Vereisten

- PHP 8.3 of hoger (lokaal draait 8.5)
- Composer 2
- Node 20 of hoger (lokaal 24, via nvm)
- MySQL 8
- Valet Linux

## Installeren

```bash
cd /home/kelvi/Sites/t-it-advies

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Maak de databases aan:

```sql
CREATE DATABASE t_it_advies CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE t_it_advies_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Zet `DB_USERNAME` en `DB_PASSWORD` in `.env`, en draai dan:

```bash
php artisan migrate --seed
valet link
npm run dev
```

`valet link` zonder argument gebruikt de naam van de map. De map heet
`t-it-advies`, dus de site komt op `http://t-it-advies.test`. Hernoem de map
niet zonder opnieuw te linken.

## Hosts-bestand (belangrijk bij WSL)

Valet Linux draait dnsmasq **binnen WSL**. Daardoor kan Ubuntu `.test`-namen
zelf oplossen, maar je browser op Windows niet: die vraagt het aan de
Windows-DNS, en die kent `.test` niet.

Daarom heeft elke site een regel nodig in het Windows-hosts-bestand:

```
C:\Windows\System32\drivers\etc\hosts
```

Voeg toe:

```
127.0.0.1	t-it-advies.test
```

Het bestand is alleen met beheerdersrechten te bewerken. In een
PowerShell-venster **als administrator**:

```powershell
Add-Content -Path "$env:SystemRoot\System32\drivers\etc\hosts" -Value "127.0.0.1`tt-it-advies.test"
```

Werkt het daarna nog niet, leeg dan de DNS-cache:

```powershell
ipconfig /flushdns
```

Dat `127.0.0.1` klopt ook echt: WSL2 stuurt poorten die binnen WSL luisteren
door naar localhost op Windows. Je hoeft dus geen IP-adres van de
WSL-netwerkkaart op te zoeken, en dat is maar goed ook, want dat verandert bij
elke herstart.

Voeg je later subdomeinen toe (bijvoorbeeld `api.t-it-advies.test`), dan heeft
elk subdomein een eigen regel nodig. Windows-hosts kent geen wildcards.

## https lokaal (alleen nodig voor passkeys)

**Je hebt dit niet nodig om te ontwikkelen.** De site draait prima op
`http://t-it-advies.test`. Eén ding werkt daar niet, en dat is het enige
waarvoor je dit doet: **passkeys**.

Browsers geven `PublicKeyCredential` alleen vrij in een _secure context_ --
https of `localhost`. Op een `.test`-adres over http bestaat dat object niet,
dus toont het scherm Beveiliging de melding dat de verbinding niet beveiligd
is en verschijnt de knop "Passkey toevoegen" niet. Dat is de browser en niet
de applicatie. Zie
[authenticatie en 2FA](../security/authenticatie-en-2fa.md#passkeys).

### De drie stappen

**1. Beveilig de site.** Dit vraagt je sudo-wachtwoord, dus doe het zelf in
een terminal:

```bash
cd ~/Sites/t-it-advies
valet secure t-it-advies
```

Valet maakt dan `~/.valet/Certificates/t-it-advies.test.crt` en `.key` aan en
zet nginx op poort 443.

**2. Zet `APP_URL` om** in je `.env` -- en alleen daar, want dat bestand zit
niet in git:

```bash
sed -i 's|^APP_URL=http://|APP_URL=https://|' .env
php artisan config:clear
```

Dit is niet cosmetisch: `config/fortify.php` leidt de _relying party_ en de
toegestane herkomst van passkeys af uit `APP_URL`. Blijft er `http://` staan,
dan weigert de browser elke passkey ook al draait de site op https.

**3. Vertrouw de Valet-CA op Windows.** Valet zet zijn certificaten in de
vertrouwde-lijst van _Ubuntu_, maar je browser draait op Windows en kent ze
niet. Zonder deze stap krijg je bij elk bezoek een waarschuwing.

Open PowerShell **als administrator**:

```powershell
Import-Certificate -FilePath "\\wsl$\Ubuntu-24.04\home\kelvi\.valet\CA\LaravelValetCASelfSigned.pem" -CertStoreLocation Cert:\LocalMachine\Root
```

> **Let op dat dit de CA is en niet het certificaat van de site zelf.**
> `~/.valet/Certificates/t-it-advies.test.crt` is het sitecertificaat, en dat
> is **niet** zelfondertekend: het is uitgegeven door
> `Laravel Valet CA Self Signed CN`. Importeer je dat, dan blijft Windows
> klagen, want de uitgever ontbreekt nog steeds in de keten. Je moet de
> **uitgever** vertrouwen, en die staat in `~/.valet/CA/`.
>
> Het voordeel is meteen duidelijk: je doet dit **één keer**. Elke site die
> je daarna met `valet secure` beveiligt, wordt vanzelf vertrouwd.

Herstart daarna je browser. Firefox heeft een eigen certificaatopslag; daar
moet je de CA apart importeren onder _Instellingen → Privacy → Certificaten →
Certificaten tonen → Certificaatinstanties → Importeren_, met het vinkje
"Deze CA vertrouwen om websites te identificeren".

Controleer het daarna zo:

```powershell
Invoke-WebRequest -Uri "https://t-it-advies.test/login" -UseBasicParsing | Select-Object StatusCode
```

Komt daar `200` uit, dan vertrouwt Windows de keten. Komt er "Kan geen
vertrouwde relatie met het beveiligde SSL/TLS-kanaal maken", dan is de CA nog
niet geïmporteerd of is de browser nog niet herstart.

### `npm run dev` blijft werken, maar daar was wél iets voor nodig

`laravel-vite-plugin` kan dit zelf: hij zoekt een certificaat in
`~/.valet/Certificates/` en zet de ontwikkelserver dan ook op https. **Op
Valet Linux werkt die detectie niet**, en dat is een stille fout.

De plugin leest de topdomeinnaam uit `~/.valet/config.json` onder de sleutel
`tld` -- zo noemen Valet op macOS en Herd hem. Valet **Linux** noemt
diezelfde sleutel `domain`:

```json
{ "domain": "test", "paths": [...], "port": "80" }
```

De plugin leest daar dus `undefined`, zoekt naar een certificaat voor
`t-it-advies.undefined`, vindt niets, en valt zonder te mopperen terug op
http. Het gevolg: de pagina staat op https en de ontwikkelserver op http, de
browser blokkeert het script van Vite als onveilige inhoud, en je kijkt naar
een pagina zonder opmaak **zonder foutmelding die uitlegt waarom**.

Daarom zoekt [`vite.config.ts`](../../vite.config.ts) het certificaat zelf
op, met `domain` én `tld` als sleutel. Ligt er geen certificaat, dan geeft
die functie `undefined` terug en verandert er niets -- dus het breekt niet op
een machine zonder Valet en niet voor wie zijn site op http laat staan.

Dat het goed staat zie je aan `public/hot`:

```
https://t-it-advies.test:5173
```

Staat daar `http://`, dan is het certificaat niet gevonden.

### Terugdraaien

```bash
valet unsecure t-it-advies
sed -i 's|^APP_URL=https://|APP_URL=http://|' .env
php artisan config:clear
```

### Dit verandert niets aan productie

Alleen `.env` op deze machine en de nginx-configuratie binnen WSL.
`.env.example` blijft op `http://t-it-advies.test` staan, want dat is de
standaard voor wie hier begint. Wat er voor de echte site moet gebeuren staat
los beschreven in
[deployment](../operations/deployment.md#voor-de-eerste-keer-live).

## Inloggen

Er is één account: dat van de eigenaar. Het wordt aangemaakt door
`PortalAccountSeeder`, ook lokaal -- dit is geen testdata.

Zet eerst `PORTAL_ACCOUNT_PASSWORD` in je `.env` (vraag het wachtwoord aan
iemand die het al heeft; het staat nergens in git), en draai dan:

```bash
php artisan db:seed --class=PortalAccountSeeder
```

Het e-mailadres staat in `config/security.php`. Registratie via de website
staat uit; heb je een extra account nodig, gebruik dan
`php artisan user:create`. Zie
[rollen en rechten](../security/rollen-en-rechten.md).

## Queue

Mail gaat via de queue. Zonder draaiende worker gebeurt er niets:

```bash
php artisan queue:listen
```

Of gebruik `composer dev`, dat server, queue, logs en Vite tegelijk start.

## Mail bekijken

Lokaal staat `MAIL_MAILER=log`: mail komt in `storage/logs/laravel.log` en
gaat niet de deur uit. Wil je de mail echt in een postbus zien, zet dan
Mailpit of MailHog op en gebruik de `smtp`-mailer.

Wat er is verstuurd zie je altijd terug in `/admin/mail`, ongeacht de mailer.

## Turnstile lokaal

Zonder `TURNSTILE_SECRET_KEY` slaat de applicatie de botcheck in `local` en
`testing` over. Je kunt dus zonder Cloudflare-account werken. In productie
weigert de applicatie bewust alles zonder secret. Zie
[spam- en botbescherming](../security/spam-en-botbescherming.md).

## Voorbeelddata om de schermen mee te bekijken

Een vers geseede database is leeg waar het om de klant gaat: geen
contactonderwerpen, geen aanvragen, geen mailoverzicht. Dat is met opzet --
`ContactSeeder` zet alleen de velden en de instellingen klaar, want verzonnen
onderwerpen op een echte site zijn erger dan geen onderwerpen. Maar het maakt
de schermen wel moeilijk te beoordelen.

Daarvoor is er een aparte seeder:

```bash
php artisan voorbeeld:zaaien
```

Die zet neer:

- **vijf onderwerpen**, waarvan één offline en één zonder Engelse naam, zodat
  je ziet wat er op de site komt en wat niet;
- **negen aanvragen** in alle standen die het scherm kent: ongelezen,
  gelezen, beantwoord, met en zonder bedrijf en telefoon, Nederlands en
  Engels, een zelf getypt onderwerp, een onderwerp dat later is verwijderd,
  een bericht van ruim duizend tekens en een van dertig;
- **zeven regels in het mailoverzicht**, inclusief een bounce en een
  verzending die nooit is vertrokken -- want dat is precies waar dat scherm
  voor bestaat.

En weer weg:

```bash
php artisan voorbeeld:opruimen
```

> **Hij weigert buiten `local` en `testing`.** Deze rijen zijn in het portaal
> niet van echte aanvragen van echte mensen te onderscheiden, en die twee door
> elkaar laten lopen is onherstelbaar. Daarom staat deze seeder ook **niet** in
> `DatabaseSeeder`: je draait hem expliciet of niet.
>
> Alles wat hij maakt staat op `@voorbeeld.test` -- een domein dat de IANA
> gereserveerd heeft en dat dus nooit bestaat. Daar gaat `voorbeeld:opruimen`
> op af, zodat er nooit een echte aanvraag meegaat. Alleen de onderwerpen
> worden op naam gevonden; dat zegt de opdracht erbij voordat hij iets doet.

## Veelgebruikte commando's

```bash
composer dev            # server + queue + logs + vite tegelijk
php artisan test        # tests
composer lint           # pint, formatteert PHP
composer types:check    # phpstan
npm run types:check     # vue-tsc
composer ci:check       # alles wat CI ook draait

php artisan voorbeeld:zaaien                      # verzonnen data erbij
php artisan voorbeeld:opruimen                    # en weer weg
```

## Problemen

**`Vite manifest not found`** -- draai `npm run dev` of `npm run build`. De
tests hebben ook een build nodig, omdat ze de echte pagina's renderen.

**`No application encryption key has been specified`** -- `php artisan key:generate`.

**`npm: command not found` in een script** -- Node komt van nvm en wordt
alleen in een interactieve shell geladen. Laad nvm expliciet:

```bash
export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"
```

**`npm run types:check` faalt op ontbrekende `.form()`** -- de gegenereerde
Wayfinder-bestanden zijn zonder form-varianten weggeschreven. Draai
`npm run build`, of `php artisan wayfinder:generate --with-form`.

**"Deze browser kan niet met passkeys overweg" of "Passkeys werken alleen op
een beveiligde verbinding"** -- je zit op http. Dat is geen fout in de
applicatie; zie [https lokaal](#https-lokaal-alleen-nodig-voor-passkeys).

**`valet secure` lijkt te hangen** -- hij wacht op je sudo-wachtwoord. Draai
hem in een terminal waar je dat kunt intypen, niet in een script.

**Na `valet secure` geeft de browser een certificaatwaarschuwing** -- stap 3
hierboven is nog niet gedaan. Valet vertrouwt het certificaat binnen Ubuntu;
Windows heeft zijn eigen lijst.

**De waarschuwing blijft ook ná het importeren** -- dan is waarschijnlijk het
sitecertificaat geïmporteerd in plaats van de CA. Controleer wat er in je
Root-opslag staat:

```powershell
Get-ChildItem Cert:\LocalMachine\Root | Where-Object { $_.Subject -match "Valet" } | Select-Object Subject, Thumbprint
```

Hoort daar `CN=Laravel Valet CA Self Signed CN` te staan. Staat er
`CN=t-it-advies.test`, dan is dat het sitecertificaat; dat mag weg en de CA
moet erbij.

**`curl` binnen WSL klaagt alsnog over het certificaat** -- dat is te
verwachten en geen probleem. curl gebruikt zijn eigen CA-bundel en niet die
van de browser. Gebruik `curl -k` als je vanuit WSL wilt testen; voor de
browser op Windows maakt het niets uit.

**De pagina laadt zonder opmaak terwijl `npm run dev` draait** -- dan staat
de site op https en de ontwikkelserver op http, en blokkeert de browser het
script van Vite. Kijk in `public/hot`: daar hoort `https://` te staan. Zie
[`npm run dev`](#npm-run-dev-blijft-werken-maar-daar-was-wél-iets-voor-nodig).

**De site is stuk en `npm run dev` draait niet** -- kijk of er een
`public/hot` is blijven staan. Zolang dat bestand er is, wijst Laravel naar
een ontwikkelserver die er niet meer is. Weghalen en klaar:

```bash
rm -f public/hot
```
