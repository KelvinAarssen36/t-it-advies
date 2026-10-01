# Mail en queues

## Uitgangspunt

Alle mail gaat via de queue. Een bezoeker die een contactformulier verstuurt
hoort niet te wachten op een API-aanroep naar een mailprovider, en een
tijdelijke storing bij die provider mag geen bericht kosten. Mailables
implementeren daarom `ShouldQueue` en worden met `Mail::queue()` verstuurd.

De queue draait standaard op de `database`-connectie. Dat is genoeg voor dit
volume en scheelt een Redis-server. Wordt het druk, dan is overstappen op
Redis een wijziging in `.env`, niet in de code.

## De twee adressen

**Er zijn twee e-mailadressen in dit project en ze doen iets anders.** Dat
onderscheid is uitdrukkelijk afgesproken en het is het soort ding dat je
per ongeluk door elkaar haalt:

| Adres                   | Waarvoor                                                                                                                             |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `aarssen@atitadvies.nl` | **Uitsluitend inloggen** in het portaal. Zie `config/security.php`.                                                                  |
| `info@atitadvies.nl`    | **Al het andere**: wat er aan contactgegevens op de website staat, en waar het contactformulier naartoe gaat. Zie `config/site.php`. |

Het inlogadres hoort nergens op de publieke site te staan, en het publieke
adres hoort nergens als inlog te worden gebruikt.

`SITE_EMAIL` in `.env` is de enige bron voor dat tweede adres. Zowel het
afzendadres als het ontvangstadres van het contactformulier valt daarop
terug, dus ze kunnen niet uiteen gaan lopen. Vul je `MAIL_FROM_ADDRESS` of
`MAIL_CONTACT_ADDRESS` toch zelf in, dan wint die.

> **Wat hier eerst fout zat.** Het ontvangstadres viel terug op het
> from-adres, en dat stond op `no-reply@t-it-advies.nl` -- een no-reply op
> een domein dat niet eens klopt. Liet iemand `MAIL_CONTACT_ADDRESS` leeg,
> dan kwam een bericht van een bezoeker dus binnen op een postbus die
> niemand leest. Daar komt niemand achter, behalve de klant die zich
> afvraagt waarom er nooit iemand mailt.

**Het afzendadres is ook `info@` en geen verzonnen no-reply.** Op gedeelde
hosting -- en daar gaat dit naartoe, zie
[deployment](../operations/deployment.md) -- komt mail van een adres dat
niet als echte postbus bestaat eerder in de spam terecht; SPF en DKIM
horen bij een bestaand adres.

[`EmailAdressenTest`](../../tests/Feature/EmailAdressenTest.php) houdt
allebei de adressen vast, inclusief een controle die afgaat zodra het
inlogadres in een publiek onderdeel belandt.

## Mailprovider

De basis staat ingesteld op **Resend**. Dat is een keuze, geen verplichting:
Laravel ondersteunt Postmark, Mailgun en SES net zo goed. Resend is gekozen
omdat de inrichting eenvoudig is en de webhooks goed gedocumenteerd zijn.

### Lokaal: MailHog, en niet het logbestand

Lokaal staat `MAIL_MAILER=smtp` met `MAIL_HOST=127.0.0.1` en
`MAIL_PORT=1025`. Dat is MailHog (of Mailpit, dezelfde poorten): de mail
komt binnen op <http://localhost:8025> en je ziet hem zoals de ontvanger
hem krijgt, met opmaak en al. Er gaat nog steeds niets de deur uit.

`MAIL_MAILER=log` kan ook, en dan belandt de mail als platte tekst in
`storage/logs/laravel.log`. Dat werkt, maar het is niet de stand waar je
in wil zitten als je iets wil _bekijken_.

> **Hier is tijd in gaan zitten, dus het staat er met nadruk.** De stand
> was `log`, en dat ziet er van buiten precies hetzelfde uit als een
> kapotte mailinstelling: je drukt op "Stuur de link opnieuw", het scherm
> zegt "Er is een nieuwe link naar je e-mailadres gestuurd", en in MailHog
> blijft het leeg. Er was niets stuk -- de mail stond in het logbestand.
> Zie je een mail niet aankomen, controleer dan **eerst** deze instelling.

**En zet er een worker naast.** Alles behalve de verificatiemail gaat via
de queue, dus zonder `php artisan queue:work` blijft een bericht in de
tabel `jobs` staan en lijkt het weer alsof er niets gebeurt. Een worker van
een ánder project helpt niet: die leest de database van dat project. Zie
[Queue draaiend houden](#queue-draaiend-houden).

| Wat je stuurt           | Via de queue? |
| ----------------------- | ------------- |
| De verificatiemail      | nee, meteen   |
| Het contactformulier    | ja            |
| Een beveiligingsmelding | ja            |

### Overstappen naar een andere provider

1. Zet `MAIL_MAILER` op `postmark`, `mailgun` of `ses`.
2. Installeer de bijbehorende transport (`symfony/postmark-mailer`,
   `symfony/mailgun-mailer`, `aws/aws-sdk-php`).
3. Zet de credentials in `config/services.php` en `.env`.
4. **Pas de webhookcontroller aan.** `ResendWebhookController` begrijpt het
   formaat en de handtekening van Resend. Elke provider doet dat anders; zie
   hieronder.
5. Werk dit document bij.

## Wat er wordt vastgelegd

Elke verstuurde mail komt in de tabel `mail_logs`, via de listener
[`RecordOutgoingMail`](../../app/Listeners/RecordOutgoingMail.php). We bewaren:

- het Message-ID (de sleutel waarmee webhooks terugkoppelen),
- de mailable-klasse, de mailer, het onderwerp,
- de ontvangers (to, cc, bcc),
- de status en de volledige tijdlijn van provider-events,
- wanneer het is verstuurd en wanneer het laatste event binnenkwam.

**Niet** de inhoud van de mail. Wil je die kunnen terugzien, maak daar dan een
bewuste keuze van met een bewaartermijn, en leg vast waarom.

De tabel wordt elke nacht opgeruimd door `mail:prune-logs`. De termijn staat
in `MAIL_LOG_RETENTION_DAYS` (standaard 180 dagen) en is korter dan die van
het beveiligingslogboek: een bounce van een jaar geleden zegt niets meer. Zie
[onderhoudstaken](../operations/onderhoudstaken.md).

Gaat er structureel iets mis met uitgaande mail, dan hoef je dat niet zelf op
te merken: `security:report` mailt bij een piek in bounces, klachten en
mislukte verzendingen.

> De listener wordt niet handmatig geregistreerd. Laravel ontdekt listeners in
> `app/Listeners` automatisch aan de hand van een methode die met `handle`
> begint. Meld je hem daarnaast ook aan in een service provider, dan draait hij
> twee keer en krijg je dubbele regels.

## Statussen

`App\Enums\MailStatus` kent een rangorde. Problemen (bounce, klacht, mislukt)
staan bovenaan en kunnen niet worden overschreven door een later binnenkomend
`delivered`- of `opened`-event. Providers leveren events niet altijd op
volgorde af; zonder die rangorde zou een trage `delivered` een bounce kunnen
wegpoetsen en denk je dat alles goed ging.

De tijdlijn in `events` bewaart wél alles, in de volgorde van binnenkomst.

## Webhooks

Endpoint: `POST /webhooks/resend`, zie [`routes/webhooks.php`](../../routes/webhooks.php).

Drie dingen maken dit endpoint veilig:

1. **Handtekeningcontrole.** Resend gebruikt Svix-handtekeningen. De
   controle zit in
   [`SvixSignature`](../../app/Support/Security/SvixSignature.php) en
   vergelijkt met `hash_equals`, zodat de duur van de controle niets verraadt.
2. **Een tijdvenster.** Verzoeken ouder dan vijf minuten worden geweigerd.
   Zonder die grens kan iemand een oud, geldig ondertekend verzoek eindeloos
   opnieuw afspelen.
3. **Rate limiting.** Ook een geldig endpoint mag niet onbeperkt worden
   bestookt.

Zonder ingesteld `MAIL_WEBHOOK_SECRET` weigert het endpoint **alles**. Een
webhook zonder handtekeningcontrole is een open deur, dus bij twijfel gaat hij
dicht. Elke geweigerde poging komt in het beveiligingslogboek.

De route is uitgezonderd van CSRF (in `bootstrap/app.php`), omdat er geen
browser en dus geen sessie aan te pas komt.

### Instellen bij Resend

1. Maak een webhook aan in het Resend-dashboard, met als URL
   `https://<domein>/webhooks/resend`.
2. Abonneer op minimaal `email.delivered`, `email.bounced` en
   `email.complained`.
3. Zet het ondertekeningsgeheim (begint met `whsec_`) in `MAIL_WEBHOOK_SECRET`.

## Het mailoverzicht

Te vinden onder `/admin/mail`, achter het recht `manage portal`. Je ziet per
mail de status, de ontvangers en de volledige tijdlijn van wat de provider
heeft teruggemeld. Regels met een probleem zijn rood.

## Queue draaiend houden

Lokaal:

```bash
php artisan queue:listen
```

In productie draai je een `queue:work` als beheerde service. Zie
[deployment](../operations/deployment.md).

Zonder draaiende worker blijft mail in de tabel `jobs` staan en gebeurt er
niets. Dat is de **tweede** plek om te kijken als iemand meldt dat er geen
mail aankomt; de eerste is `MAIL_MAILER`, zie
[hierboven](#lokaal-mailhog-en-niet-het-logbestand).

Draai hem **vanuit deze map**. Op een machine met meerdere Laravel-projecten
staat er makkelijk al een worker, maar die leest de database van zijn eigen
project en raakt deze `jobs`-tabel niet aan. `pgrep -a -f queue:work` laat
zien wat er draait, maar niet waar; `readlink /proc/<pid>/cwd` wel.

Een snelle controle of er iets klaarstaat:

```bash
php artisan queue:monitor default
```
