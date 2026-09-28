# Monitoring

## Waar je kijkt

| Wat                                          | Waar                                         | Nodig recht     |
| -------------------------------------------- | -------------------------------------------- | --------------- |
| Overzicht van de laatste 24 uur              | `/admin`                                     | `manage portal` |
| Wat is er verstuurd en wat meldt de provider | `/admin/mail`                                | `manage portal` |
| Wat er aan de website is veranderd           | `/admin/activiteit`                          | `manage portal` |
| Geslaagde en mislukte beveiligingspogingen   | `/admin/security`                            | `manage portal` |
| Wie er toegang heeft en met welke rollen     | `/admin/users`                               | `manage portal` |
| Prestaties, trage queries, achtergrondtaken  | `/pulse`                                     | `manage portal` |
| Ruwe logs                                    | `storage/logs/laravel.log` en `security.log` | server          |
| Gezondheidscheck                             | `/up`                                        | geen            |

## Laravel Pulse

Pulse toont trage verzoeken, trage queries, mislukte taken, uitzonderingen en
serverbelasting. Toegang loopt via de gate `viewPulse`, die is gekoppeld aan
het recht `manage portal`.

## Als de applicatie omvalt

Er gaat een mail uit naar `SECURITY_ALERT_ADDRESS`. Zonder dat staat een 500
alleen in `storage/logs/laravel.log`, en daar kijkt niemand in tot de klant
belt -- en dan is het al een dag oud.

Dat doet
[`CrashReporter`](../../app/Support/Security/CrashReporter.php), met vier
grenzen die er allemaal zijn om één reden: een melder die te veel stuurt
wordt weggefilterd, en dan ben je slechter af dan met geen melder, want dan
dénk je dat je bewaking hebt.

1. **Alleen echte fouten.** Een 404 of een 403 zegt dat het systeem werkt.
2. **Niet lokaal.** Tijdens het ontwikkelen zie je de fout op je scherm.
3. **Een afkoeltijd per soort fout** (`CRASH_ALERT_COOLDOWN_MINUTES`,
   standaard een half uur). Eén kapotte pagina die tien keer wordt bezocht
   is één probleem. De sleutel is de soort fout plus de plek in de code, niet
   de melding -- daar staat vaak een id in dat per verzoek verschilt.
4. **Geen adres ingesteld betekent stil blijven.** Een melder die zelf
   fouten gooit omdat hij niet kan mailen is een probleem erbij.

**De mail is karig met opzet:** soort, plek, verzoek. Geen stacktrace, want
die mail landt in een postbus die minder goed beveiligd is dan de
applicatie. Voor het hele verhaal ga je naar het logbestand op de server.

De melding zelf gaat door `SecurityLogger::redactText()`. Dat is een vangnet
en geen garantie -- een foutmelding is vrije tekst, en er kan van alles in
staan wat een pakket erin heeft gezet. Regel 2 uit
[`AGENTS.md`](../../AGENTS.md) blijft leidend.

Deze mail gaat **niet** door de wachtrij, in tegenstelling tot de rest. Valt
de applicatie om doordat de database weg is, dan komt een taak in de
wachtrij nooit aan -- en dan mis je juist de melding die je het hardst nodig
had.

Wordt het er te veel, dan is dit het moment om een dienst als Sentry ernaast
te zetten. Tot die tijd is het verschil tussen "we horen het" en "we horen
het niet" groter dan het verschil tussen een mail en een dashboard.

### Waar kijk je na zo'n mail?

**Er is bewust geen foutenscherm in het portaal.** Dat is een beslissing en
geen gat: zo'n scherm zou stacktraces en ontwikkelaarstaal aan de eigenaar
van de website laten zien, terwijl die er niets mee kan. Er is één rol, dus
alles wat we in het menu zetten ziet hij ook.

De mail zegt dát er iets is en waar. Voor de rest:

| Wat je zoekt                             | Waar                                                   |
| ---------------------------------------- | ------------------------------------------------------ |
| De volledige stacktrace                  | `storage/logs/laravel.log` op de server                |
| Hoe vaak dezelfde fout voorkomt, en waar | `/pulse` -- de uitzonderingenkaart staat standaard aan |
| Of het aan een gebruiker lag             | `/admin/security` en `/admin/activiteit`               |

`/pulse` staat **niet** in het menu, om diezelfde reden. Type het adres in;
het recht (`manage portal`) heb je al.

Verandert dat ooit -- bijvoorbeeld als er een tweede beheerder komt die
alleen inhoud beheert -- dan is een eigen foutenscherm in de schil van het
portaal de nettere oplossing. De crashmelder legt nu al vast wat zo'n scherm
zou tonen.

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
