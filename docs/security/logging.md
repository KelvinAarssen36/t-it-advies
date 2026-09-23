# Security logging

## De harde regel

**Er gaat nooit een wachtwoord, TOTP-code, recovery code, secret of token de
opslag in.** Niet ingekort, niet gehasht, niet "voor de zekerheid even".

Dit is geen richtlijn maar een eigenschap van het systeem, afgedwongen door
code en door tests. Als
[`SecurityLoggerTest`](../../tests/Feature/Security/SecurityLoggerTest.php)
faalt, lekt de applicatie en is dat een blokkerend probleem.

## Hoe het werkt

Alles loopt via
[`SecurityLogger`](../../app/Support/Security/SecurityLogger.php):

```php
app(SecurityLogger::class)->success(SecurityEventType::Login, $user, ['guard' => 'web']);
app(SecurityLogger::class)->failure(SecurityEventType::LoginFailed, email: $email);
```

Elke context gaat eerst door `redact()`. Die functie:

- vergelijkt sleutelnamen hoofdletter-ongevoelig,
- negeert scheidingstekens, zodat `recovery_code`, `Recovery-Code` en
  `recoveryCode` alle drie worden herkend,
- werkt door in geneste arrays op elk niveau,
- kort lange waarden af op duizend tekens.

De lijst met sleutels staat in `config/security.php` onder
`logging.redacted_keys`. Voeg je ergens een gevoelig veld toe, zet de naam
daar dan bij én breid de dataprovider in de test uit.

## Waar het heen gaat

Twee plekken, allebei tegelijk:

1. De tabel **`security_events`**. Append-only: rijen worden nooit bijgewerkt,
   alleen opgeruimd volgens het retentiebeleid.
2. Het logkanaal **`security`** (`storage/logs/security.log`, dagelijks
   geroteerd, standaard negentig dagen). Handig voor centrale logverzameling en
   voor een langere bewaartermijn dan de gewone applicatielog.

## Wat er wordt vastgelegd

De volledige lijst staat in `App\Enums\SecurityEventType`. In grote lijnen:

- **Inloggen** -- geslaagd, mislukt, uitgelogd, geblokkeerd na te veel
  pogingen.
- **Wachtwoord** -- hersteld, gewijzigd.
- **E-mail** -- geverifieerd.
- **2FA** -- ingeschakeld, bevestigd, uitgeschakeld, gevraagd, gelukt,
  mislukt, recovery code gebruikt, nieuwe recovery codes aangemaakt.
- **Gevoelige acties** -- zie [gevoelige acties](gevoelige-acties.md).
- **Gebruikersbeheer** -- rollen gewijzigd, account verwijderd. Bij een
  verwijderd account leggen we het e-mailadres vast: de koppeling naar
  `users` wordt op null gezet, dus zonder dat adres loopt het spoor dood op
  een id dat nergens meer bij hoort.
- **Alarmering** -- er is een melding verstuurd.
- **Spam** -- honeypot aangeslagen, Turnstile mislukt.
- **Rate limiting** -- grens geraakt.
- **Webhooks** -- geweigerd wegens ontbrekende of foute handtekening.

Per regel leggen we vast: de gebruiker (indien bekend), het opgegeven
e-mailadres, het gebeurtenistype, de uitkomst, het IP-adres, de user agent en
de geschoonde context.

Het e-mailadres bewaren we óók bij mislukte pogingen zonder bestaande
gebruiker. Zonder dat veld kun je niet zien dat iemand een lijst adressen
aan het aflopen is.

## Waar je het ziet

`/admin/security`, achter het recht `view security log`. Je kunt filteren op
gebeurtenis, uitkomst, e-mailadres en IP-adres. Klik een regel open voor de
user agent en de volledige context.

## Aansluiten van nieuwe gebeurtenissen

Auth- en Fortify-events lopen via
[`RecordSecurityEvents`](../../app/Listeners/RecordSecurityEvents.php). Dat is
bewust één bestand: zo is er maar één plek waar je hoeft te kijken om te zien
wat er wel en niet wordt vastgelegd.

De methoden daar heten `record*` en niet `handle*`. Laravel ontdekt listeners
in `app/Listeners` automatisch via methoden die met `handle` beginnen; met die
naamgeving zou elke gebeurtenis twee keer worden vastgelegd -- een keer via de
ontdekking en een keer via `Event::subscribe()`.

Voeg je een eigen gevoelige handeling toe, dan:

1. Zet een waarde in `SecurityEventType` met een Nederlands label.
2. Roep `SecurityLogger` aan op de plek van de handeling.
3. Schrijf een test die bewijst dat er niets gevoeligs in de context zit.
4. Werk dit document bij.

## Bewaartermijn

`SECURITY_LOG_RETENTION_DAYS` (standaard 365) voor de tabel,
`SECURITY_LOG_DAILY_DAYS` (standaard 90) voor het logbestand.

De tabel wordt elke nacht opgeruimd door `security:prune-events`, het bestand
rouleert Laravel zelf. De taak draait alleen als de scheduler draait; zie
[onderhoudstaken](../operations/onderhoudstaken.md) voor hoe dat werkt en
waarom het in blokken gebeurt.

## Alarmering

Vastleggen is niet hetzelfde als merken. `security:report` kijkt elk uur in
dit logboek en mailt bij een piek in mislukte inlogpogingen of bij
mailproblemen. In die melding staan bewust geen e-mailadressen van
gebruikers. Zie [onderhoudstaken](../operations/onderhoudstaken.md).
