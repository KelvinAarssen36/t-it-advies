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

## Inloggen

De seeder maakt in `local` een beheerdersaccount aan:

- e-mail: `admin@t-it-advies.test`
- wachtwoord: `password`

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

## Veelgebruikte commando's

```bash
composer dev            # server + queue + logs + vite tegelijk
php artisan test        # tests
composer lint           # pint, formatteert PHP
composer types:check    # phpstan
npm run types:check     # vue-tsc
composer ci:check       # alles wat CI ook draait
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
