# Deployment

Het project is één Laravel-applicatie. Dat is de reden dat hosting eenvoudig
blijft: Laravel Cloud, Forge, Ploi of een gewone VPS kunnen dit allemaal aan.
Er is geen aparte frontend-server en geen losse API nodig.

## Wat de server nodig heeft

- PHP 8.3 of hoger (lokaal 8.5, CI draait op 8.3), met de gebruikelijke extensies (`bcmath`, `ctype`,
  `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`,
  `pdo_mysql`, `tokenizer`, `xml`)
- MySQL 8
- Node (alleen om te bouwen; de server hoeft Node niet te draaien)
- Een **queue worker** die blijft draaien
- De **scheduler**, elke minuut

Die laatste twee worden het vaakst vergeten. Zonder worker wordt er geen mail
verstuurd.

## Deploystappen

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force

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

| Variabele                         | Waarvoor                                      | Verplicht in productie |
| --------------------------------- | --------------------------------------------- | ---------------------- |
| `RESEND_API_KEY`                  | Mail versturen                                | ja                     |
| `MAIL_WEBHOOK_SECRET`             | Handtekening van de mailwebhook               | ja                     |
| `MAIL_CONTACT_ADDRESS`            | Waar contactformulieren binnenkomen           | ja                     |
| `TURNSTILE_SITE_KEY`              | Botcheck in de browser                        | ja                     |
| `TURNSTILE_SECRET_KEY`            | Botcheck op de server                         | ja                     |
| `SECURITY_ALERT_ADDRESS`          | Waar beveiligingsmeldingen heen gaan          | ja                     |
| `SENSITIVE_ACTION_TTL`            | Geldigheid van een 2FA-bevestiging (seconden) | nee, standaard 900     |
| `SECURITY_LOG_RETENTION_DAYS`     | Bewaartermijn logboektabel                    | nee, standaard 365     |
| `MAIL_LOG_RETENTION_DAYS`         | Bewaartermijn mailoverzicht                   | nee, standaard 180     |
| `SECURITY_ALERT_WINDOW_MINUTES`   | Hoe ver de alarmering terugkijkt              | nee, standaard 60      |
| `SECURITY_ALERT_COOLDOWN_MINUTES` | Pauze na een melding                          | nee, standaard 180     |
| `SECURITY_ALERT_FAILED_LOGINS`    | Drempel mislukte inlogpogingen                | nee, standaard 25      |
| `SECURITY_ALERT_MAIL_PROBLEMS`    | Drempel mailproblemen                         | nee, standaard 5       |

Zonder `TURNSTILE_SECRET_KEY` weigert de applicatie in productie bewust elk
beschermd formulier. Zonder `MAIL_WEBHOOK_SECRET` weigert het
webhook-endpoint alles. Dat is geen storing maar het ontwerp: zie
[spam- en botbescherming](../security/spam-en-botbescherming.md).

## Voor de eerste keer live

1. DNS: SPF, DKIM en DMARC. Zie
   [e-mailauthenticatie](../security/e-mailauthenticatie.md). Doe dit vóór de
   lancering; DNS heeft tijd nodig.
2. Domein verifiëren in het dashboard van de mailprovider.
3. Webhook instellen op `https://<domein>/webhooks/resend`.
4. Turnstile-widget aanmaken voor het productiedomein.
5. Een beheerdersaccount maken en er meteen 2FA op zetten.
6. HTTPS afdwingen. De applicatie doet dat zelf al in productie
   (`URL::forceScheme('https')`), maar de webserver hoort ook te redirecten.
7. `APP_DEBUG=false` controleren.
8. `SECURITY_ALERT_ADDRESS` zetten en de cronregel voor de scheduler
   aanzetten. Zonder die twee logt de applicatie wel alles, maar krijgt
   niemand ooit bericht. Controleer het met
   `php artisan security:report --force --window=10080`.

## Draait het achter een proxy of load balancer

Stel dan de vertrouwde proxy's in, anders ziet de applicatie het IP-adres van
de load balancer in plaats van dat van de bezoeker. Dat maakt rate limiting
per IP waardeloos en zet het verkeerde adres in het beveiligingslogboek.
