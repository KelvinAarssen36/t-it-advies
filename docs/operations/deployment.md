# Deployment

Het project is één Laravel-applicatie. Dat is de reden dat hosting eenvoudig
blijft: Laravel Cloud, Forge, Ploi of een gewone VPS kunnen dit allemaal aan.
Er is geen aparte frontend-server en geen losse API nodig.

## Wat de server nodig heeft

- PHP 8.3 of hoger (lokaal 8.5, CI draait op 8.3), met de gebruikelijke extensies (`bcmath`, `ctype`,
  `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`,
  `pdo_mysql`, `tokenizer`, `xml`)
- **`gd`**, voor het verkleinen van geüploade logo's. Ontbreekt hij, dan
  blijft het beheerscherm werken maar wordt een logo opgeslagen zoals het
  binnenkwam -- en downloadt elke bezoeker dat hele bestand. Zie
  [de ervaringsmodule](../architecture/modules/ervaring.md#het-logo).
- Voor uploads: `upload_max_filesize` en `post_max_size`. Zie
  [de PHP-instellingen voor uploads](#de-php-instellingen-voor-uploads)
  hieronder; dit is de instelling die het vaakst fout staat.
- MySQL 8
- Node (alleen om te bouwen; de server hoeft Node niet te draaien)
- Een **queue worker** die blijft draaien
- De **scheduler**, elke minuut

Die laatste twee worden het vaakst vergeten. Zonder worker wordt er geen mail
verstuurd.

## De PHP-instellingen voor uploads

De klant uploadt logo's, en daar gaan drie PHP-instellingen over. Staan ze
verkeerd, dan is dat niet zichtbaar als een foutmelding maar als een
formulier dat niets doet -- PHP kapt het verzoek af vóórdat Laravel eraan
toekomt, dus er is geen bestand om over te klagen.

| Instelling            | Minimaal | Aanbevolen | Waarom                                                             |
| --------------------- | -------- | ---------- | ------------------------------------------------------------------ |
| `upload_max_filesize` | 2M       | **8M**     | Moet **boven** `media.logo.max_kb` (1,5 MB) liggen, niet erop.     |
| `post_max_size`       | 8M       | **12M**    | Geldt voor het hele formulier, dus bestand plus beide talen tekst. |
| `memory_limit`        | 128M     | **256M**   | GD pakt een beeld uit in het geheugen: vier bytes per beeldpunt.   |

**De grens in de applicatie staat bewust op anderhalve megabyte**
(`MEDIA_LOGO_MAX_KB` in `config/media.php`). Dat is geen zuinigheid maar
een keuze voor gedeelde hosting: die staat vaak standaard op
`upload_max_filesize = 2M`, en dan past onze grens er nog net onder. Zou
hij er gelijk aan zijn, dan is een bestand op de grens al te groot voor PHP
terwijl onze eigen regel hem nog goedkeurt -- precies het geval waarin de
klant een fout krijgt die nergens beschreven staat.

Ruimer hoeft ook niet. Wat er bewaard wordt is een vierkantje van 256 bij
256, in de praktijk tien tot dertig kilobyte, en de browser verkleint een
te groot beeld al vóór het versturen (zie
[formulieren](../architecture/formulieren-en-schuifbalken.md#een-bestand-kiezen)).
Wat er binnenkomt is normaal gesproken een paar honderd kilobyte.
Anderhalve megabyte is het vangnet, niet de gewone gang van zaken.

`memory_limit` is de enige die met de afmetingen te maken heeft en niet met
de bestandsgrootte. Een beeld van 3000 bij 3000 -- onze bovengrens -- kost
GD zo'n 36 MB, en er staat er even meer dan één tegelijk in het geheugen.
Met 256M is er ruimte over; met 128M gaat het bij het uiterste geval net
goed en heb je geen marge.

### Op Strato

Op de gedeelde pakketten van Strato zet je dit niet in een `php.ini` van
jezelf. Twee wegen, en de eerste heeft de voorkeur:

1. **In het klantenmenu**, onder de PHP-instellingen van het pakket. Daar
   staan `upload_max_filesize`, `post_max_size` en `memory_limit` als
   velden die je gewoon invult.
2. **Een `.user.ini`** in de webroot -- dus naast `index.php`, in `public/`
   en niet in de hoofdmap van het project:

    ```ini
    upload_max_filesize = 8M
    post_max_size = 12M
    memory_limit = 256M
    ```

    Let op: PHP leest dat bestand niet bij elk verzoek opnieuw. Standaard
    duurt het tot vijf minuten voordat een wijziging meetelt, dus meet niet
    meteen en concludeer niet te snel dat het niet werkt.

**Controleer het na de deploy**, en niet op je woord:

```bash
php -r 'echo ini_get("upload_max_filesize"), " ", ini_get("post_max_size"), " ", ini_get("memory_limit"), PHP_EOL;'
```

De CLI van PHP kan andere instellingen hebben dan de webserver. Twijfel je,
zet dan tijdelijk een `phpinfo()` in de webroot en kijk naar de kolom
"Local Value" -- en haal hem daarna meteen weg.

[`LogoLimietenTest`](../../tests/Feature/Website/LogoLimietenTest.php)
controleert de verhouding tussen deze instellingen en `config/media.php`,
maar draait op de PHP van je testomgeving. Hij bewaakt dus dat de getallen
kloppen, niet dat de productieserver goed staat. Dat blijft deze controle.

## Deploystappen

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan migrate --force

# Rollen en het account van de eigenaar. Allebei geen testdata: zonder
# rollen werkt geen rechtencontrole, zonder dat account kan niemand inloggen.
php artisan db:seed --force

# De symlink naar de uploadmap. Staat in .gitignore, dus die moet op elke
# nieuwe omgeving opnieuw worden gelegd; zonder deze link geeft elke
# geüploade afbeelding een 404.
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan queue:restart
```

De seeder van rollen en rechten is idempotent en hoort bij elke deploy. Voeg
je een recht toe, dan staat het daarna meteen goed.

`queue:restart` is niet optioneel: workers houden de oude code in het geheugen
en pakken nieuwe code pas op na een herstart.

`event:cache` is belangrijk in dit project. Laravel ontdekt listeners in
`app/Listeners` automatisch; die scan wil je in productie niet bij elk verzoek
doen.

## Queue worker

Met Supervisor:

```ini
[program:t-it-advies-worker]
command=php /var/www/t-it-advies/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stopwaitsecs=3600
```

Op Laravel Cloud, Forge of Ploi klik je dit in de interface aan; de instellingen
zijn dezelfde.

## Scheduler

```cron
* * * * * cd /var/www/t-it-advies && php artisan schedule:run >> /dev/null 2>&1
```

Deze ene regel laat álle geplande taken draaien: het opruimen van de
logboeken en de alarmering. Vergeet je hem, dan groeien de tabellen door en
komt er nooit een melding, zonder dat er iets zichtbaar stuk is. Controleer
na een deploy met `php artisan schedule:list`; wat er hoort te staan vind je
in [onderhoudstaken](onderhoudstaken.md).

## Omgevingsvariabelen

Naast de standaard Laravel-variabelen:

| Variabele                         | Waarvoor                                        | Verplicht in productie              |
| --------------------------------- | ----------------------------------------------- | ----------------------------------- |
| `RESEND_API_KEY`                  | Mail versturen                                  | ja                                  |
| `MAIL_WEBHOOK_SECRET`             | Handtekening van de mailwebhook                 | ja                                  |
| `MAIL_CONTACT_ADDRESS`            | Waar contactformulieren binnenkomen             | ja                                  |
| `TURNSTILE_SITE_KEY`              | Botcheck in de browser                          | ja                                  |
| `TURNSTILE_SECRET_KEY`            | Botcheck op de server                           | ja                                  |
| `SECURITY_ALERT_ADDRESS`          | Waar beveiligingsmeldingen heen gaan            | ja                                  |
| `PORTAL_ACCOUNT_PASSWORD`         | Wachtwoord van het account van de eigenaar      | ja, vóór de eerste seed             |
| `PASSKEYS_USER_HANDLE_SECRET`     | Sleutel waarmee passkeys aan een account hangen | ja, zie hieronder                   |
| `PORTAL_TWO_FACTOR_REQUIRED`      | 2FA verplicht in het portaal                    | nee, staat in productie vanzelf aan |
| `SENSITIVE_ACTION_TTL`            | Geldigheid van een 2FA-bevestiging (seconden)   | nee, standaard 900                  |
| `SECURITY_LOG_RETENTION_DAYS`     | Bewaartermijn logboektabel                      | nee, standaard 365                  |
| `MAIL_LOG_RETENTION_DAYS`         | Bewaartermijn mailoverzicht                     | nee, standaard 180                  |
| `SECURITY_ALERT_WINDOW_MINUTES`   | Hoe ver de alarmering terugkijkt                | nee, standaard 60                   |
| `SECURITY_ALERT_COOLDOWN_MINUTES` | Pauze na een melding                            | nee, standaard 180                  |
| `SECURITY_ALERT_FAILED_LOGINS`    | Drempel mislukte inlogpogingen                  | nee, standaard 25                   |
| `SECURITY_ALERT_MAIL_PROBLEMS`    | Drempel mailproblemen                           | nee, standaard 5                    |

Zonder `TURNSTILE_SECRET_KEY` weigert de applicatie in productie bewust elk
beschermd formulier. Zonder `MAIL_WEBHOOK_SECRET` weigert het
webhook-endpoint alles. Dat is geen storing maar het ontwerp: zie
[spam- en botbescherming](../security/spam-en-botbescherming.md).

### `PASSKEYS_USER_HANDLE_SECRET` hoort apart te staan

Laat je hem leeg, dan valt hij terug op `APP_KEY`. Dat wérkt, en daarom is
het een val: wordt `APP_KEY` ooit vernieuwd -- bij een verhuizing, of omdat
iemand denkt dat het netjes is -- dan zijn **alle bestaande passkeys in één
klap onbruikbaar**, en er is niets dat uitlegt waarom.

Zet er dus een eigen waarde in, één keer, en laat hem daarna staan:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Verandert hij later alsnog, dan moet de eigenaar met zijn wachtwoord en 2FA
inloggen en zijn passkeys opnieuw aanmaken. Vervelend, niet fataal.

## Voor de eerste keer live

1. DNS: SPF, DKIM en DMARC. Zie
   [e-mailauthenticatie](../security/e-mailauthenticatie.md). Doe dit vóór de
   lancering; DNS heeft tijd nodig.
2. Domein verifiëren in het dashboard van de mailprovider.
3. Webhook instellen op `https://<domein>/webhooks/resend`.
4. Turnstile-widget aanmaken voor het productiedomein.
5. `PORTAL_ACCOUNT_PASSWORD` zetten en seeden; daarmee bestaat het account
   van de eigenaar. Zet er meteen 2FA op. Registratie via de website staat
   uit, dus een extra account maak je met `php artisan user:create`. Zie
   [rollen en rechten](../security/rollen-en-rechten.md).
6. HTTPS afdwingen. De applicatie doet dat zelf al in productie
   (`URL::forceScheme('https')`), maar de webserver hoort ook te redirecten.
7. `APP_DEBUG=false` controleren.
8. **De PHP-instellingen voor uploads nalopen** en het resultaat meten met
   het commando hierboven. Op Strato staat `upload_max_filesize` standaard
   krap; laat je dat staan, dan kan de klant zijn logo niet kwijt en ziet
   hij niet waarom.
9. `SECURITY_ALERT_ADDRESS` zetten en de cronregel voor de scheduler
   aanzetten. Zonder die twee logt de applicatie wel alles, maar krijgt
   niemand ooit bericht. Controleer het met
   `php artisan security:report --force --window=10080`.
10. **`TRANSLATE_EMAIL` zetten.** Zonder dat adres telt de vertaaldienst het
    dagtegoed per **IP-adres**, en op gedeelde hosting deel je dat met alle
    andere sites op die server -- dan kan het tegoed op zijn zonder dat er
    bij ons iemand op de knop heeft gedrukt. Mét adres telt hij per adres,
    en is het tegoed bovendien tien keer zo hoog. Zie
    [automatisch vertalen](../architecture/automatisch-vertalen.md).
11. **Controleer of de server naar buiten mag** (uitgaand https). Sommige
    hostingpakketten staan dat niet toe. Kan het niet, dan blijft alles
    werken -- de knop geeft dan netjes een foutmelding -- maar zet hem dan
    liever uit met `TRANSLATE_ENABLED=false`, zodat hij niet elke keer
    teleurstelt.
12. **`APP_URL` op het echte domein zetten, met `https://` ervoor.** Dat is
    hier geen cosmetische instelling: passkeys halen hun _relying party id_
    en hun toegestane herkomst uit die waarde
    (`config/fortify.php`). Staat er nog `http://t-it-advies.test`, dan
    weigert de browser elke passkey -- zonder foutmelding die uitlegt
    waarom. Let ook op het domein zelf: dat is **atitadvies.nl** en niet
    `t-it-advies.nl`.
13. **Kijk na wat de hostingpartij in zijn toegangslogboek zet en hoe lang
    hij dat bewaart.** De privacyverklaring noemt dat logbestand, maar
    zonder termijn, omdat wij die niet bepalen. Is hij bekend en vast, dan
    kan hij er alsnog bij -- maar alleen als hij klopt. Zie
    [bezoekcijfers](../architecture/bezoekcijfers.md#de-privacyverklaring).
14. **Kies één vaste host en verwijs de andere door.** Dus `atitadvies.nl`
    óf `www.atitadvies.nl`, met een 301 van de ene naar de andere. Een
    passkey zit vast aan de host waar hij is gemaakt; is de site op allebei
    bereikbaar, dan werkt een passkey van de ene niet op de andere en lijkt
    het alsof hij zomaar kwijt is. Dezelfde host hoort in `APP_URL`.
15. **`PASSKEYS_USER_HANDLE_SECRET` zetten.** Zie het stuk hierboven: laat
    je hem leeg, dan hangt hij aan `APP_KEY`, en dan sneuvelen alle
    passkeys zodra die ooit wordt vernieuwd.
16. **Controleer dat `/.well-known/passkey-endpoints` een JSON-antwoord
    geeft** en geen 404 van de webserver. Op gedeelde hosting wordt
    `/.well-known/` soms door de server zelf afgehandeld -- dat is de map
    waar ook de certificaatcontrole van Let's Encrypt doorheen gaat. Vangt
    hij alles af, dan vinden wachtwoordmanagers de beheerpagina niet.
    Controleer het met `curl -s https://<domein>/.well-known/passkey-endpoints`.

### En daarna: maak één echte passkey aan

**Dit is het enige onderdeel dat lokaal niet te testen is**, want zonder
https geeft de browser `PublicKeyCredential` niet vrij en verschijnt de knop
niet eens. Alles eromheen is nagelopen en in orde -- de routes, de feature,
het model, de componenten en de `Permissions-Policy` -- maar of het echt
werkt zie je pas op het echte adres.

Ga dus na de lancering één keer naar **Instellingen → Beveiliging**, maak
een passkey aan en log er één keer mee in. Lukt dat, dan is alles hierboven
goed gezet. Lukt het niet, dan is het bijna altijd stap 12, 14 of 15.

> Gaat dit mis, dan is dat **geen blokkade**: inloggen met wachtwoord en 2FA
> werkt er los van.

## Draait het achter een proxy of load balancer

Zet dan **`TRUSTED_PROXIES`** in `.env` op de adressen van die proxy,
gescheiden door komma's. Laat je dat leeg, dan ziet de applicatie het
IP-adres van de proxy in plaats van dat van de bezoeker, en gaat er op drie
plekken iets stil mis:

| Waar                                                 | Wat je ziet                                                                 |
| ---------------------------------------------------- | --------------------------------------------------------------------------- |
| De snelheidsgrenzen                                  | Gelden voor alle bezoekers samen; één drukke bezoeker sluit de rest buiten. |
| Het beveiligingslogboek                              | Eén adres bij elke poging, dus je kunt niets meer onderscheiden.            |
| De [bezoekcijfers](../architecture/bezoekcijfers.md) | Iedereen krijgt dezelfde code: voor altijd één bezoeker per dag.            |

> **Vul er geen `*` in.** De kop `X-Forwarded-For` is door de afzender zelf
> te verzinnen. Vertrouw je hem zonder dat er een proxy voor staat, dan kiest
> iedereen zijn eigen IP-adres -- en dan zijn het logboek en die grenzen
> helemaal niets meer waard. Leeg is veiliger dan `*`.

Op het gedeelde pakket van Strato staat er geen proxy voor, dus daar blijft
deze instelling leeg. Zet je er later een CDN voor, dan is dit het eerste
dat mee moet.

## Zet er geen volledige paginacache voor

Een cache vóór de applicatie -- bij een CDN bijvoorbeeld -- betekent dat de
middleware van de bezoekcijfers niet meer draait. De grafiek blijft dan plat
zonder dat er iets zichtbaar stuk is. Zie
[bezoekcijfers](../architecture/bezoekcijfers.md).
