# Testen

## Wat we gebruiken

PHPUnit 12, zoals de starter kit het levert. Geen Pest: de kit schrijft zijn
tests in klassen, en half omzetten levert twee stijlen naast elkaar op. Wil je
alsnog naar Pest, doe dat dan in één keer voor de hele suite en werk dit
document bij.

```bash
php artisan test                      # alles
php artisan test --filter=Sensitive   # één groep
composer ci:check                     # lint + phpstan + vue-tsc + tests
```

De tests draaien op SQLite in het geheugen (zie `phpunit.xml`), niet op MySQL.
Dat is snel en vereist geen opgeruimde database. Gebruik je iets dat
MySQL-specifiek is, dan moet dat in `phpunit.xml` worden omgezet naar
`t_it_advies_test`.

**De tests hebben een Vite-build nodig**, omdat ze de echte Inertia-pagina's
renderen. Draai `npm run build` als je `Vite manifest not found` ziet.

## Wat er getest is

| Bestand                                                                            | Bewaakt                                                                                                                              |
| ---------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| [SecurityLoggerTest](../../tests/Feature/Security/SecurityLoggerTest.php)          | Dat er nooit een wachtwoord, code, secret of recovery code wordt opgeslagen, ook niet genest of anders geschreven.                   |
| [SensitiveActionTest](../../tests/Feature/Security/SensitiveActionTest.php)        | Dat gevoelige acties een verse TOTP-code vragen, recovery codes weigeren, zonder 2FA niet doorgaan, en begrensd zijn.                |
| [TurnstileTest](../../tests/Feature/Security/TurnstileTest.php)                    | Dat Turnstile dichtklapt bij een storing en bij ontbrekende configuratie buiten local.                                               |
| [ContactFormTest](../../tests/Feature/ContactFormTest.php)                         | Honeypot, validatie, rate limiting, en dat mail via de queue gaat.                                                                   |
| [MailLoggingTest](../../tests/Feature/Mail/MailLoggingTest.php)                    | Dat verstuurde mail wordt vastgelegd, dat een bounce niet wordt weggepoetst, en dat de webhook zonder geldige handtekening dichtzit. |
| [AdminAccessTest](../../tests/Feature/Admin/AdminAccessTest.php)                   | Dat het beveiligde gedeelte per pagina op rechten controleert.                                                                       |
| [UserManagementTest](../../tests/Feature/Admin/UserManagementTest.php)             | Dat rollen wijzigen en verwijderen een verse code vragen, en dat je jezelf en de laatste beheerder niet kunt weghalen.               |
| [SecurityAlertTest](../../tests/Feature/Security/SecurityAlertTest.php)            | Dat er pas boven de drempel wordt gemeld, dat de afkoeltijd werkt, en dat er geen e-mailadressen van gebruikers in de melding staan. |
| [MaintenanceCommandsTest](../../tests/Feature/Console/MaintenanceCommandsTest.php) | Dat het opruimen oude regels weghaalt en recente laat staan, ook over meerdere blokken heen.                                         |

Daarnaast de tests die de starter kit meelevert voor inloggen, registreren,
wachtwoord herstellen, e-mailverificatie, de 2FA-challenge en de instellingen.

## Een geldige TOTP-code in een test

De factory-state `withTwoFactor()` zet een nepgeheim neer; dat is genoeg om te
testen dát 2FA aanstaat, maar je kunt er geen geldige code mee maken. Heb je
een echte code nodig:

```php
$secret = app(Google2FA::class)->generateSecretKey();

$user->forceFill([
    'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
    'two_factor_confirmed_at' => now(),
])->save();

$code = app(Google2FA::class)->getCurrentOtp($secret);
```

Gebruik `Fortify::currentEncrypter()` en niet `encrypt()`, zodat de test
dezelfde weg volgt als de applicatie.

## Dingen om op te letten

**Rate limiting lekt tussen tests.** De limiter gebruikt de cache. In de
testomgeving staat `CACHE_STORE=array`, dus die wordt per test geleegd. Zet je
dat om, dan moet je zelf `RateLimiter::clear()` aanroepen.

**De `dns`-variant van de e-mailvalidatie staat uit in tests.** Die doet een
echte DNS-lookup en zou de suite traag en netwerkafhankelijk maken. Zie
`ContactRequest::emailRule()`. Buiten de tests staat hij wél aan; dat verschil
is bewust en het is het enige in zijn soort.

**Dataproviders gebruiken attributen.** PHPUnit 12 kent `@dataProvider` in een
docblock niet meer. Gebruik `#[DataProvider('naam')]`.

## Wat je toevoegt bij een wijziging

- Nieuwe route achter rechten → een test dat iemand zonder dat recht een 403
  krijgt.
- Nieuwe gevoelige actie → een test dat hij zonder verse code niet doorgaat.
- Nieuw veld dat gevoelig kan zijn → zet de sleutel in
  `config/security.php` **en** in de dataprovider van `SecurityLoggerTest`.
- Nieuw formulier → een test voor honeypot en rate limiting.
- Nieuwe geplande taak → een test die bewijst dat hij doet wat hij belooft
  **en** dat hij niet te veel weghaalt. Zie
  [onderhoudstaken](../operations/onderhoudstaken.md).
