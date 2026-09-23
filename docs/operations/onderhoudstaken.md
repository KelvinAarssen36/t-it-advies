# Onderhoudstaken

De taken die vanzelf draaien: opruimen en alarmeren. Ze staan in
[`routes/console.php`](../../routes/console.php).

> **Zonder scheduler gebeurt hier niets.** Er is één cronregel nodig op de
> server; zie [deployment](deployment.md). Vergeet je die, dan groeien de
> logboektabellen door en komt er nooit een melding -- zonder dat er iets
> zichtbaar stuk is. Dat is precies het soort storing dat je pas maanden
> later ontdekt. Controleer na een deploy met `php artisan schedule:list`.

## Het overzicht

| Tijd    | Taak                    | Wat het doet                                       |
| ------- | ----------------------- | -------------------------------------------------- |
| elk uur | `security:report`       | Meldt pieken in mislukte pogingen en mailproblemen |
| 03:10   | `security:prune-events` | Ruimt `security_events` op                         |
| 03:20   | `mail:prune-logs`       | Ruimt `mail_logs` op                               |
| 03:30   | `queue:prune-failed`    | Ruimt mislukte jobs ouder dan 14 dagen op          |
| 03:40   | `queue:prune-batches`   | Ruimt afgeronde batches op                         |
| 03:50   | `auth:clear-resets`     | Ruimt verlopen wachtwoordherstel-tokens op         |

De opruimtaken staan bewust niet op hetzelfde moment: twee grote deletes
tegelijk op dezelfde database maken elkaar alleen maar trager.

Pulse staat hier niet bij. Dat pakket ruimt zichzelf op tijdens het
ingesten; zie [monitoring](monitoring.md) als de Pulse-tabellen toch hard
groeien.

## Opruimen

### Hoe het werkt

Beide opruimtaken gebruiken
[`RowPruner`](../../app/Support/Maintenance/RowPruner.php). Die verwijdert in
blokken van duizend en niet met één grote `DELETE`. Twee redenen:

- Op MySQL houdt één grote delete de tabel lang op slot, terwijl de
  applicatie er tegelijk in schrijft -- `security_events` krijgt bij elke
  inlogpoging een rij.
- `DELETE ... LIMIT` bestaat niet op SQLite, en daar draaien de tests op.
  Daarom halen we eerst de sleutels op en verwijderen we daarna op sleutel.

Het is een mass delete: modelevents draaien niet. Voor logboektabellen is dat
precies goed, want die hangen nergens aan vast.

### Bewaartermijnen

| Wat                     | Variabele                     | Standaard |
| ----------------------- | ----------------------------- | --------- |
| Tabel `security_events` | `SECURITY_LOG_RETENTION_DAYS` | 365 dagen |
| Bestand `security.log`  | `SECURITY_LOG_DAILY_DAYS`     | 90 dagen  |
| Tabel `mail_logs`       | `MAIL_LOG_RETENTION_DAYS`     | 180 dagen |

De termijnen verschillen met opzet. Een bounce van een jaar geleden zegt
niets meer; een inlogpoging van een jaar geleden kan bij onderzoek naar een
inbraak nog steeds het ontbrekende puzzelstuk zijn.

Het logbestand wordt door Laravel zelf geroteerd, niet door deze taken.

### Met de hand draaien

```bash
php artisan security:prune-events
php artisan mail:prune-logs

# Eenmalig afwijken van de ingestelde termijn:
php artisan security:prune-events --days=90
```

Een termijn van nul dagen wordt geweigerd. Het hele logboek wissen is nooit
de bedoeling van een onderhoudstaak, en een typefout in een cronregel mag
geen bewijsmateriaal kosten.

## Alarmering

Een logboek waar niemand in kijkt is geen bewaking. `security:report` kijkt
elk uur voor je en mailt zodra er iets boven een drempel uitkomt.

### Waar het naar kijkt

[`AnomalyScanner`](../../app/Support/Security/AnomalyScanner.php) stelt twee
vragen over het ingestelde venster:

| Signaal                | Wat er wordt geteld                            | Drempel |
| ---------------------- | ---------------------------------------------- | ------- |
| Mislukte inlogpogingen | `auth.login_failed` plus `auth.lockout`        | 25      |
| Mailproblemen          | mails met status bounced, complained of failed | 5       |

Die twee gebeurtenissen tellen samen bij het eerste signaal, en dat is geen
detail: een aanvaller die tegen de rate limiter aanloopt levert juist _minder_
`auth.login_failed` op. Los van elkaar geteld zou een geslaagde afweer
eruitzien als rust.

### Wat er in de mail staat

Alleen wat er speelt, hoe vaak, de drempel, de vijf drukste IP-adressen en
een link naar het logboek.

**Geen e-mailadressen van gebruikers en geen mailinhoud.** Zo'n melding komt
terecht in een postbus die doorgaans minder goed is beveiligd dan de
applicatie zelf. Wie de details nodig heeft logt in op `/admin/security`;
daar staat alles, achter 2FA en een recht. Er is een test die dit afdwingt:
[`SecurityAlertTest`](../../tests/Feature/Security/SecurityAlertTest.php).

### De afkoeltijd

Na een melding blijft hetzelfde soort signaal `SECURITY_ALERT_COOLDOWN_MINUTES`
stil (standaard drie uur). Zonder die pauze levert een aanval die drie uur
duurt ook drie uur lang elk uur een mail op -- en dan zet de ontvanger een
filter aan, wat precies het tegenovergestelde is van wat je wilt.

In het logboek loopt intussen alles gewoon door; alleen de mail wacht.

Handmatig melden, met voorbijgaan aan de afkoeltijd:

```bash
php artisan security:report --force
php artisan security:report --window=1440   # kijk een etmaal terug
```

### Instellingen

| Variabele                         | Waarvoor                               | Standaard |
| --------------------------------- | -------------------------------------- | --------- |
| `SECURITY_ALERT_ADDRESS`          | Waar de melding heen gaat              | leeg      |
| `SECURITY_ALERT_WINDOW_MINUTES`   | Hoe ver de taak terugkijkt             | 60        |
| `SECURITY_ALERT_COOLDOWN_MINUTES` | Pauze per soort signaal na een melding | 180       |
| `SECURITY_ALERT_FAILED_LOGINS`    | Drempel mislukte inlogpogingen         | 25        |
| `SECURITY_ALERT_MAIL_PROBLEMS`    | Drempel mailproblemen                  | 5         |

**Zonder adres verstuurt de taak niets.** Hij faalt dan niet, maar zet een
waarschuwing in de applicatielog. Een scheduler die elk uur een fout meldt
leidt af van echte problemen; een taak die stilletjes niets doet is erger.
Vandaar die waarschuwing -- en vandaar dat `SECURITY_ALERT_ADDRESS` in de
[deploy-checklist](deployment.md) staat.

De melding gaat via de queue, net als alle andere mail. Draait er geen queue
worker, dan blijft hij in de tabel `jobs` staan.

### Drempels afstellen

De standaardwaarden zijn een startpunt, geen waarheid. Kijk na een paar weken
in `/admin/security` hoeveel mislukte pogingen een rustige dag oplevert en zet
de drempel daar ruim boven. Te laag afgesteld went de ontvanger aan meldingen
die niets betekenen, en dan mist hij de echte.

## Wat hier nog niet in zit

- **Geen melding naar Slack of Teams.** Alleen e-mail. Een extra kanaal is een
  tweede `Mailable`-achtige klasse plus een adres in de config; het scannen
  hoeft er niet voor te veranderen.
- **Geen escalatie.** Een signaal dat een dag aanhoudt wordt niet dringender
  gemeld dan een signaal van één uur.
- **Geen melding als de scheduler zelf stilvalt.** Dat is de blinde vlek van
  elke geplande taak: als hij niet draait, meldt hij ook niet dat hij niet
  draait. Wil je dat afdekken, gebruik dan een externe dienst die een
  heartbeat verwacht.
