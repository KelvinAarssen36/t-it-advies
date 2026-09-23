# Mail en queues

## Uitgangspunt

Alle mail gaat via de queue. Een bezoeker die een contactformulier verstuurt
hoort niet te wachten op een API-aanroep naar een mailprovider, en een
tijdelijke storing bij die provider mag geen bericht kosten. Mailables
implementeren daarom `ShouldQueue` en worden met `Mail::queue()` verstuurd.

De queue draait standaard op de `database`-connectie. Dat is genoeg voor dit
volume en scheelt een Redis-server. Wordt het druk, dan is overstappen op
Redis een wijziging in `.env`, niet in de code.

## Mailprovider

De basis staat ingesteld op **Resend**. Dat is een keuze, geen verplichting:
Laravel ondersteunt Postmark, Mailgun en SES net zo goed. Resend is gekozen
omdat de inrichting eenvoudig is en de webhooks goed gedocumenteerd zijn.

Lokaal staat `MAIL_MAILER=log`: mail belandt in `storage/logs/laravel.log` en
er gaat niets de deur uit.

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

Te vinden onder `/admin/mail`, achter het recht `view mail log`. Je ziet per
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
niets. Dat is de eerste plek om te kijken als iemand meldt dat er geen mail
aankomt.
