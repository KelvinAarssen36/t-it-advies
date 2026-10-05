# Onderhoudstaken

De taken die vanzelf draaien: opruimen en alarmeren. Ze staan in
[`routes/console.php`](../../routes/console.php).

> **Zonder scheduler gebeurt hier niets.** Er is één cronregel nodig op de
> server; zie [deployment](deployment.md). Vergeet je die, dan groeien de
> logboektabellen door en komt er nooit een melding -- zonder dat er iets
> zichtbaar stuk is. Dat is precies het soort storing dat je pas maanden
> later ontdekt. Controleer na een deploy met `php artisan schedule:list`.

## Het overzicht

| Tijd    | Taak                    | Wat het doet                                                                                                                                                                                                                                            |
| ------- | ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| elk uur | `security:report`       | Meldt pieken in mislukte pogingen en mailproblemen                                                                                                                                                                                                      |
| 03:05   | `bezoek:prune-codes`    | Gooit de bezoekerscodes en het zout van voorbije dagen weg. **Tweede slot:** dat gebeurt normaal al bij het eerste bezoek na middernacht. Zie [bezoekcijfers](../architecture/bezoekcijfers.md).                                                        |
| 03:10   | `security:prune-events` | Ruimt `security_events` op. Termijn: `SECURITY_LOG_RETENTION_DAYS`, standaard een jaar.                                                                                                                                                                 |
| 03:15   | `contact:prune`         | Ruimt de contactaanvragen op. Termijn: `CONTACT_RETENTION_DAYS`, standaard een jaar. **De enige van deze taken die over inhoud van een bezoeker gaat**, en de termijn staat in de privacyverklaring. Zie [contact](../architecture/modules/contact.md). |
| 03:20   | `mail:prune-logs`       | Ruimt `mail_logs` op. Termijn: `MAIL_LOG_RETENTION_DAYS`, standaard 180 dagen.                                                                                                                                                                          |
| 03:25   | `activity:prune`        | Ruimt het activiteitenlogboek op. Eigen termijn: `ACTIVITY_LOG_RETENTION_DAYS`, standaard een jaar. Zie [activiteitenlogboek](../security/activiteitenlogboek.md).                                                                                      |
| 03:30   | `queue:prune-failed`    | Ruimt mislukte jobs ouder dan 14 dagen op                                                                                                                                                                                                               |
| 03:40   | `queue:prune-batches`   | Ruimt afgeronde batches op                                                                                                                                                                                                                              |
| 03:50   | `auth:clear-resets`     | Ruimt verlopen wachtwoordherstel-tokens op                                                                                                                                                                                                              |

> **Deze tabel stond verhaspeld**: twee regels waren in elkaar geschoven,
> waardoor `activity:prune` op 03:10 leek te staan en
> `security:prune-events` zonder tijd. De tijden hierboven komen nu uit
> `routes/console.php`.

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

| Wat                         | Variabele                     | Standaard                |
| --------------------------- | ----------------------------- | ------------------------ |
| Tabel `security_events`     | `SECURITY_LOG_RETENTION_DAYS` | 365 dagen                |
| Tabel `contact_submissions` | `CONTACT_RETENTION_DAYS`      | 365 dagen                |
| Bestand `security.log`      | `SECURITY_LOG_DAILY_DAYS`     | 90 dagen                 |
| Tabel `mail_logs`           | `MAIL_LOG_RETENTION_DAYS`     | 180 dagen                |
| Tabel `activity_entries`    | `ACTIVITY_LOG_RETENTION_DAYS` | 365 dagen                |
| Tabel `site_visitor_codes`  | geen -- en met opzet          | één dag, niet instelbaar |

**Die laatste heeft bewust geen instelling.** Eén dag is wat er in de
privacyverklaring aan de bezoeker wordt beloofd, en een knop om er dertig
van te maken is een knop om die belofte te breken. Zie
[bezoekcijfers](../architecture/bezoekcijfers.md).

> **`CONTACT_RETENTION_DAYS` is de enige termijn hierboven die over inhoud
> van een bezoeker gaat** en niet over een logboek. Hij staat ook in de
> privacyverklaring, en die leest diezelfde instelling -- dus verander je
> hem, dan verandert die tekst mee. Dat is met opzet zo geregeld; een getal
> dat op twee plekken staat loopt uit elkaar. Zie
> [contact](../architecture/modules/contact.md).

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

[`AnomalyScanner`](../../app/Support/Security/AnomalyScanner.php) stelt drie
vragen:

| Signaal                    | Wat er wordt geteld                            | Drempel |
| -------------------------- | ---------------------------------------------- | ------- |
| Mislukte inlogpogingen     | `auth.login_failed` plus `auth.lockout`        | 25      |
| Mailproblemen              | mails met status bounced, complained of failed | 5       |
| Werk blijft in de wachtrij | jobs die meer dan 15 minuten over tijd zijn    | 1       |

Die twee gebeurtenissen tellen samen bij het eerste signaal, en dat is geen
detail: een aanvaller die tegen de rate limiter aanloopt levert juist _minder_
`auth.login_failed` op. Los van elkaar geteld zou een geslaagde afweer
eruitzien als rust.

De eerste twee kijken naar het ingestelde venster; de derde kijkt naar de
stand van nu. "Er ligt werk dat een kwartier over tijd is" is geen
gebeurtenis in het verleden maar iets dat op dit moment klemt.

### Werk dat in de wachtrij blijft liggen

**Dit is de stilste storing die deze applicatie heeft.** Alle mail gaat via
de wachtrij: de melding aan de eigenaar, de bevestiging aan de bezoeker, de
beveiligingsmeldingen. Draait er geen worker, dan blijven die in de tabel
`jobs` staan -- en er is níets dat eruitziet als een fout. De bezoeker krijgt
zijn bedankje, de aanvraag staat netjes onder Beheer → Aanvragen, en de
eigenaar wacht op een mail die nooit komt.

Daarom een drempel van **1** en niet van vijf of vijfentwintig. Bij de andere
twee signalen gaat het om een piek boven een normaal niveau; hier gaat het om
een toestand die niet hoort te bestaan. Een job die een kwartier over tijd is,
is geen piek maar een stilstand, en dan is het tweede bericht net zo erg als
het vijfde.

Twee dingen die deze controle met opzet níet doet:

- **Ze noemt geen namen van jobs in de mail.** De klasse van een job vertelt
  wat er in de site gebeurt, en zo'n melding komt in een postbus die minder
  goed is beveiligd dan de applicatie.
- **Ze zwijgt bij een andere wachtrij dan de database.** Bij `sync` wordt werk
  tijdens het verzoek zelf gedaan en kan er per definitie niets blijven
  liggen; bij Redis zit de wachtrij ergens waar wij hier niet in kijken. Een
  alarm dat altijd nul meldt leert je het te negeren.

Hoe je de worker op gedeelde hosting aan de praat houdt staat in
[deployment](deployment.md#op-gedeelde-hosting-zoals-strato).

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

| Variabele                          | Waarvoor                               | Standaard |
| ---------------------------------- | -------------------------------------- | --------- |
| `SECURITY_ALERT_ADDRESS`           | Waar de melding heen gaat              | leeg      |
| `SECURITY_ALERT_WINDOW_MINUTES`    | Hoe ver de taak terugkijkt             | 60        |
| `SECURITY_ALERT_COOLDOWN_MINUTES`  | Pauze per soort signaal na een melding | 180       |
| `SECURITY_ALERT_FAILED_LOGINS`     | Drempel mislukte inlogpogingen         | 25        |
| `SECURITY_ALERT_MAIL_PROBLEMS`     | Drempel mailproblemen                  | 5         |
| `SECURITY_ALERT_STUCK_JOB_MINUTES` | Vanaf wanneer werk "blijft liggen"     | 15        |
| `SECURITY_ALERT_STUCK_JOBS`        | Drempel vastgelopen jobs               | 1         |

**Zonder adres verstuurt de taak niets.** Hij faalt dan niet, maar zet een
waarschuwing in de applicatielog. Een scheduler die elk uur een fout meldt
leidt af van echte problemen; een taak die stilletjes niets doet is erger.
Vandaar die waarschuwing -- en vandaar dat `SECURITY_ALERT_ADDRESS` in de
[deploy-checklist](deployment.md) staat.

**Deze melding gaat als enige níet via de queue.** `SecurityAlertMail` is een
`ShouldQueue`, en `Mail::send()` zet zo'n mail alsnog in de wachtrij -- dat
doet de mailer zelf. Daarmee ging precies het ene bericht dat je nodig hebt
als de worker stilstaat, door die stilstaande worker. Vandaar `sendNow()` in
[`ReportSecurityAnomalies`](../../app/Console/Commands/ReportSecurityAnomalies.php),
en vandaar een test die omvalt als iemand daar weer `send()` van maakt.

Dat kost niets: de taak draait per uur vanuit de scheduler, dus er wacht geen
bezoeker op, en mislukt de verzending dan meldt de volgende ronde hetzelfde
signaal opnieuw -- het venster schuift mee.

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
