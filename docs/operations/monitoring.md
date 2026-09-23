# Monitoring

## Waar je kijkt

| Wat                                          | Waar                                         | Nodig recht         |
| -------------------------------------------- | -------------------------------------------- | ------------------- |
| Overzicht van de laatste 24 uur              | `/admin`                                     | `view security log` |
| Wat is er verstuurd en wat meldt de provider | `/admin/mail`                                | `view mail log`     |
| Geslaagde en mislukte beveiligingspogingen   | `/admin/security`                            | `view security log` |
| Wie er toegang heeft en met welke rollen     | `/admin/users`                               | `manage users`      |
| Prestaties, trage queries, achtergrondtaken  | `/pulse`                                     | `view pulse`        |
| Ruwe logs                                    | `storage/logs/laravel.log` en `security.log` | server              |
| Gezondheidscheck                             | `/up`                                        | geen                |

## Laravel Pulse

Pulse toont trage verzoeken, trage queries, mislukte taken, uitzonderingen en
serverbelasting. Toegang loopt via de gate `viewPulse`, die is gekoppeld aan
het recht `view pulse`.

Pulse schrijft veel. Groeit de database onhandig hard, zet dan sampling aan in
`config/pulse.php` (bijvoorbeeld `sample_rate` op 0.1) of laat Pulse via Redis
ingesten. In de testomgeving staat Pulse uit (`PULSE_ENABLED=false` in
`phpunit.xml`).

## Als er iets mis is

**"Ik krijg geen mail"**

1. Draait de queue worker? Zonder worker blijft alles in de tabel `jobs`
   staan.
2. Staat er iets in `/admin/mail`? Zo nee, dan is er nooit iets verstuurd en
   moet je verder terug in de keten kijken.
3. Staat de status op `bounced` of `complained`? Dan is het bij de ontvanger
   misgegaan; de tijdlijn zegt waarom.
4. Kloppen SPF, DKIM en DMARC nog? Zie
   [e-mailauthenticatie](../security/e-mailauthenticatie.md).

**"Iemand probeert in te breken"**

Filter `/admin/security` op uitkomst "Mislukt". Let op:

- veel `auth.login_failed` vanaf één IP-adres of op één e-mailadres,
- `throttle.limited`, wat betekent dat de rate limiting daadwerkelijk aanslaat,
- `sensitive.failed` of `sensitive.recovery_code_refused`: iemand probeert
  langs de 2FA-controle van een gevoelige actie te komen.

**"Het contactformulier werkt niet"**

1. Staat `TURNSTILE_SECRET_KEY` ingesteld? In productie weigert de applicatie
   zonder secret alles, met opzet.
2. Kijk in het beveiligingslogboek op `spam.turnstile_failed` en
   `spam.blocked`.
3. Is Cloudflare bereikbaar vanaf de server? Bij een storing weigert de
   applicatie bewust.

**"De site is traag"**

Kijk in Pulse naar trage verzoeken en trage queries. Groeit `security_events`
of `mail_logs` heel hard, controleer dan of de indexen nog worden gebruikt.

## Alarmering

Je hoeft niet zelf te blijven kijken. `security:report` draait elk uur en
mailt bij een piek in mislukte inlogpogingen of bij mailproblemen, met een
afkoeltijd zodat één aanval geen stroom mails oplevert. Instellen doe je met
`SECURITY_ALERT_ADDRESS` en de drempels; alles staat in
[onderhoudstaken](onderhoudstaken.md).

Zonder adres blijft de applicatie stil. Zet dat adres dus voordat je live
gaat.

## Wat er nog niet is

- **Geen melding als de scheduler zelf stilvalt.** Draait de cronregel niet,
  dan ruimt niets op en meldt niets iets -- en dat is aan de buitenkant
  onzichtbaar. Een externe dienst die een heartbeat verwacht dekt dit af.
- **Geen escalatie en geen Slack of Teams.** Alleen e-mail, en een melding
  die een dag aanhoudt is niet dringender dan een melding van één uur.
