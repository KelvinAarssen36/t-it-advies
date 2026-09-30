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

| Bestand                                                                                         | Bewaakt                                                                                                                                                                                                |
| ----------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| [SecurityLoggerTest](../../tests/Feature/Security/SecurityLoggerTest.php)                       | Dat er nooit een wachtwoord, code, secret of recovery code wordt opgeslagen, ook niet genest of anders geschreven.                                                                                     |
| [SensitiveActionTest](../../tests/Feature/Security/SensitiveActionTest.php)                     | Dat gevoelige acties een verse TOTP-code vragen, recovery codes weigeren, zonder 2FA niet doorgaan, en begrensd zijn.                                                                                  |
| [TurnstileTest](../../tests/Feature/Security/TurnstileTest.php)                                 | Dat Turnstile dichtklapt bij een storing en bij ontbrekende configuratie buiten local.                                                                                                                 |
| [ContactFormTest](../../tests/Feature/ContactFormTest.php)                                      | Honeypot, validatie, rate limiting, en dat mail via de queue gaat.                                                                                                                                     |
| [MailLoggingTest](../../tests/Feature/Mail/MailLoggingTest.php)                                 | Dat verstuurde mail wordt vastgelegd, dat een bounce niet wordt weggepoetst, en dat de webhook zonder geldige handtekening dichtzit.                                                                   |
| [AdminAccessTest](../../tests/Feature/Admin/AdminAccessTest.php)                                | Dat het beveiligde gedeelte per pagina op rechten controleert.                                                                                                                                         |
| [UserManagementTest](../../tests/Feature/Admin/UserManagementTest.php)                          | Dat rollen wijzigen en verwijderen een verse code vragen, en dat je jezelf en de laatste beheerder niet kunt weghalen.                                                                                 |
| [SecurityAlertTest](../../tests/Feature/Security/SecurityAlertTest.php)                         | Dat er pas boven de drempel wordt gemeld, dat de afkoeltijd werkt, en dat er geen e-mailadressen van gebruikers in de melding staan.                                                                   |
| [MaintenanceCommandsTest](../../tests/Feature/Console/MaintenanceCommandsTest.php)              | Dat het opruimen oude regels weghaalt en recente laat staan, ook over meerdere blokken heen.                                                                                                           |
| [CreateUserCommandTest](../../tests/Feature/Console/CreateUserCommandTest.php)                  | Dat `user:create` een bruikbaar account oplevert en het wachtwoord nergens vastlegt.                                                                                                                   |
| [RegistrationTest](../../tests/Feature/Auth/RegistrationTest.php)                               | Dat registratie uit staat en niet ongemerkt terugkomt.                                                                                                                                                 |
| [TwoFactorRequiredTest](../../tests/Feature/Security/TwoFactorRequiredTest.php)                 | Elk poortje van de 2FA-verplichting apart, inclusief de uitzonderingen die een omleidingslus voorkomen.                                                                                                |
| [PortalEntryTest](../../tests/Feature/Security/PortalEntryTest.php)                             | Dat het laadscherm geen open omleiding is, en dat de begroeting één keer verschijnt -- op welke portaalpagina je ook binnenkomt.                                                                       |
| [TwoFactorQrCodeTest](../../tests/Feature/Security/TwoFactorQrCodeTest.php)                     | Dat de QR-code een stille zone houdt en donker-op-licht blijft, anders scant een authenticator-app hem niet.                                                                                           |
| [LocaleTest](../../tests/Feature/LocaleTest.php)                                                | Dat het profiel wint van de sessie, dat een onbekende taal wordt geweigerd en dat wisselen op beide helften doorwerkt.                                                                                 |
| [ActivityLoggerTest](../../tests/Feature/Activity/ActivityLoggerTest.php)                       | Dat een wijziging wordt vastgelegd mét de oude waarde, dat een lege opslag niets oplevert, en dat er nooit iets geheims in belandt.                                                                    |
| [ActivityScreenTest](../../tests/Feature/Activity/ActivityScreenTest.php)                       | Dat het activiteitenlogboek achter hetzelfde ene recht zit als de rest, en dat de filters meegroeien met wat er in het logboek staat.                                                                  |
| [PageLayoutTest](../../tests/Feature/Website/PageLayoutTest.php)                                | Dat de volgorde wordt opgeslagen, dat de kop en de voettekst niet te verplaatsen zijn -- ook niet met een zelfgemaakt verzoek -- en dat een halve indeling wordt geweigerd.                            |
| [LandingSectionsTest](../../tests/Feature/Website/LandingSectionsTest.php)                      | Dat de website die volgorde ook echt toont, en dat een uitgezet of nog leeg onderdeel er niet op staat.                                                                                                |
| [ExperienceCrudTest](../../tests/Feature/Website/ExperienceCrudTest.php)                        | De eerste CRUD van de klant: rechten per route, beide talen opslaan, een lege waarde die `null` wordt, en een periode die niet achterstevoren mag.                                                     |
| [ExperienceHeadingTest](../../tests/Feature/Website/ExperienceHeadingTest.php)                  | Dat de kop boven de tijdlijn van de klant is: allebei de talen, een verplichte titel, en dat een deploy zijn tekst niet terugzet.                                                                      |
| [ExperienceTimelineTest](../../tests/Feature/Website/ExperienceTimelineTest.php)                | Dat een leeg veld niets oplevert op de site, en dat het Engels terugvalt op het Nederlands waar dat moet en juist niet waar dat niet moet.                                                             |
| [ExperienceTranslationTest](../../tests/Feature/Website/ExperienceTranslationTest.php)          | Dat de vertaalknop niets opslaat, netjes faalt als de dienst wegvalt, begrensd is, en dat de echte dienst nooit wordt aangeroepen.                                                                     |
| [ServiceCrudTest](../../tests/Feature/Website/ServiceCrudTest.php)                              | De diensten: rechten op elke route, beide talen opslaan, verwijderen inclusief de expertisepunten, en het activiteitenlogboek.                                                                         |
| [ServicePointsTest](../../tests/Feature/Website/ServicePointsTest.php)                          | De expertisepunten: hoogstens acht, de volgorde, het gelijktrekken van de lijst, en dat een punt zonder Engels wegvalt in plaats van terugvalt.                                                        |
| [ServicePublishingTest](../../tests/Feature/Website/ServicePublishingTest.php)                  | Online en offline, de volgorde slepen, en dat het hele blok verdwijnt zodra alles offline staat -- én terugkomt zodra er één aan staat.                                                                |
| [ServiceHeadingTest](../../tests/Feature/Website/ServiceHeadingTest.php)                        | De kop boven de diensten, en welk veld terugvalt op het Nederlands en welk niet.                                                                                                                       |
| [DatumTest](../../tests/Feature/DatumTest.php)                                                  | Dat de dag vooraan staat in het Nederlands, dat "hoe lang geleden" meegaat met de taal, en dat een lege datum leeg blijft.                                                                             |
| [SecurityHeadersTest](../../tests/Feature/Security/SecurityHeadersTest.php)                     | Dat de beveiligingskoppen op elke respons staan, ook op een foutpagina, en dat HSTS wegblijft van http.                                                                                                |
| [CrashReporterTest](../../tests/Feature/Security/CrashReporterTest.php)                         | Dat een crash gemeld wordt, dat een 404 dat niet is, dat dezelfde fout niet blijft mailen, en dat er niets geheims in de mail belandt.                                                                 |
| [ToastTest](../../tests/Feature/ToastTest.php)                                                  | Dat de soort melding klopt: wijzigen is niet hetzelfde als verwijderen, en een geweigerde handeling is een fout en geen mededeling.                                                                    |
| [ErrorPageTest](../../tests/Feature/ErrorPageTest.php)                                          | Dat een ingelogde gebruiker de foutpagina van het portaal krijgt met de juiste statuscode, en dat een gast en een API-verzoek die niet krijgen.                                                        |
| [AppearanceTest](../../tests/Feature/AppearanceTest.php)                                        | Dat donker de standaard is, dat licht wordt onthouden, en dat een oude of onzinnige cookiewaarde donker oplevert.                                                                                      |
| [PasswordConfirmationTest](../../tests/Feature/Security/PasswordConfirmationTest.php)           | Dat het slotje naast "Beveiliging" klopt: dicht bij een verse sessie, open na bevestiging, en weer dicht als die verlopen is.                                                                          |
| [PublicSiteTest](../../tests/Feature/PublicSiteTest.php)                                        | Dat een bezoeker geen gebruiker in de gedeelde props krijgt, en dat uitloggen de geschiedenis van de browser wist.                                                                                     |
| [TranslationsTest](../../tests/Feature/TranslationsTest.php)                                    | Dat de woordenlijst met de frontend wordt gedeeld, dat elke sleutel in `resources/js` én elke `__()` in `app/` een Engelse vertaling heeft, en dat er geen kale Nederlandse zin in een sjabloon staat. |
| [RolesAndPermissionsSeederTest](../../tests/Feature/Database/RolesAndPermissionsSeederTest.php) | Dat de rollen hun rechten daadwerkelijk krijgen, ook op een lege database.                                                                                                                             |
| [PortalAccountSeederTest](../../tests/Feature/Database/PortalAccountSeederTest.php)             | Dat het account van de eigenaar altijd bestaat en dat een deploy nooit een gewijzigd wachtwoord terugdraait.                                                                                           |

Daarnaast de tests die de starter kit meelevert voor inloggen, wachtwoord
herstellen, e-mailverificatie, de 2FA-challenge en de instellingen.

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

## Elke CRUD krijgt tests

**Dit is een harde eis van dit project, geen advies.** Elk beheerscherm
waarmee de klant iets aanmaakt, wijzigt of verwijdert, wordt afgeleverd mét
tests. Een CRUD zonder tests is niet af.

De reden is praktisch: de klant beheert zijn eigen website, en wat hij
opslaat staat meteen live. Gaat er iets stuk in een formulier dat hij
wekelijks gebruikt, dan merkt hij dat op zijn eigen site en niet wij in een
foutmelding.

Deze lijst loop je bij elke CRUD af:

| Wat je test                                                                                     | Waarom                                                                                         |
| ----------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| Gast wordt naar het inlogscherm gestuurd                                                        | Elke route, niet alleen het overzicht.                                                         |
| Zonder het juiste recht een 403                                                                 | Op **elke** route apart: lijst, opslaan, wijzigen, verwijderen.                                |
| Aanmaken slaat op wat je invult                                                                 | De gewone weg, die moet gewoon kloppen.                                                        |
| Wijzigen wijzigt, en alleen het bedoelde item                                                   | Een verkeerde `where` raakt anders alles.                                                      |
| Verwijderen verwijdert                                                                          | En laat de rest staan.                                                                         |
| Ongeldige invoer wordt geweigerd                                                                | En er staat daarna **niets** in de database.                                                   |
| Bij een gevoelige actie: geen verse code, geen wijziging                                        | Zie [gevoelige acties](../security/gevoelige-acties.md).                                       |
| Wat er wordt vastgelegd in het beveiligingslogboek                                              | Bij verwijderen en bij rechtenwijzigingen.                                                     |
| Dat er niets gevoeligs in dat logboek staat                                                     | Zodra de context van de actie vrije tekst bevat.                                               |
| Is er een zoekveld: dat een jokerteken gewoon een teken is                                      | In een `LIKE` betekent `%` "wat dan ook"; zonder ontsnapping geeft "100%" de hele lijst terug. |
| Is er een detailpagina: waar je belandt na het verwijderen                                      | Die pagina bestaat dan niet meer. Terug naar waar je vandaan kwam is dan een 404.              |
| Is er een upload: dat de naam van de klant niet wordt gebruikt, en dat het oude bestand weggaat | Anders komt `../../.env` op de schijf, of blijft er van elk verwijderd item een bestand staan. |
| Is er een online/offline-schuifje: dat offline werk niet op de site staat                       | Inclusief de teller die bepaalt of het onderdeel nog inhoud heeft.                             |

Twee dingen die makkelijk worden overgeslagen:

**De bevestigingsvensters zijn geen beveiliging.** Dat de klant twee keer op
"weet je het zeker" moet klikken gebeurt in de browser. De server hoort zich
daar niets van aan te trekken en moet zelf valideren en autoriseren. Test
dus altijd het verzoek zelf, niet het scherm.

**Test ook de openbare kant.** Wat de klant opslaat moet daadwerkelijk op de
site verschijnen. Een CRUD die netjes wegschrijft maar niets toont, is
evengoed stuk.

Zodra inhoud tweetalig wordt, hoort daar een test bij die bewijst dat beide
talen worden opgeslagen en teruggegeven.

### Een voorbeeld dat er al staat

[`UserManagementTest`](../../tests/Feature/Admin/UserManagementTest.php) is
de eerste CRUD in dit project en loopt bovenstaande lijst af: rechten per
route, de verse code bij wijzigen en verwijderen, wat er wordt gelogd, en de
twee vangnetten. Gebruik die als vorm voor de volgende.

## Wat je toevoegt bij een wijziging

- Nieuwe route achter rechten → een test dat iemand zonder dat recht een 403
  krijgt.
- Nieuwe gevoelige actie → een test dat hij zonder verse code niet doorgaat.
- Nieuw veld dat gevoelig kan zijn → zet de sleutel in
  `config/security.php` **en** in de dataprovider van `SecurityLoggerTest`.
- Nieuw formulier → een test voor honeypot en rate limiting.
- Nieuwe tekst die iemand leest → in `$t()`, `t()` of `__()`, én een regel
  in `lang/en.json`. Dat geldt voor **alles**: het portaal, de publieke
  site, een toast, een validatiemelding, het label van een enum en een
  `sr-only`-tekst voor een schermlezer. `TranslationsTest` valt anders om,
  en die kijkt ook of er een kale Nederlandse zin in een sjabloon staat.
  Zie [vertalingen](../architecture/vertalingen.md).
- Nieuwe geplande taak → een test die bewijst dat hij doet wat hij belooft
  **en** dat hij niet te veel weghaalt. Zie
  [onderhoudstaken](../operations/onderhoudstaken.md).
- Nieuw onderdeel van de landingspagina → een test dat het onderdeel van de
  site verdwijnt zodra er niets in staat, en dat het er weer op komt zodra
  er wél iets in staat. Die tweede is geen overbodige spiegel: zonder hem
  slaagt de eerste ook als het onderdeel nooit verschijnt. Zie
  [pagina-indeling](../architecture/pagina-indeling.md).
- Nieuw tweetalig veld → een test dat **beide** talen worden opgeslagen en
  teruggegeven, en een die bewijst wat er gebeurt als het Engels leeg is:
  een verplicht veld valt terug op het Nederlands, een optioneel veld wordt
  weggelaten. Zie [vertalingen](../architecture/vertalingen.md).
- Nieuw optioneel tekstveld → een test dat een lege invoer `null` wordt en
  geen `""`. Dat verschil bepaalt of de website er een kopje boven zet.
- Nieuwe handeling met een melding → een test op de **soort** melding, niet
  alleen dat er iets verschijnt. Zie
  [meldingen](../architecture/meldingen.md).
- Nieuw model waarvan de klant de inhoud beheert → `use LogsActivity;` en
  een test dat aanmaken, wijzigen en verwijderen in het logboek komen. Zie
  [activiteitenlogboek](../security/activiteitenlogboek.md).
- **Nieuwe CRUD → de volledige lijst hierboven.** Niet een enkele test dat
  opslaan werkt, maar ook de rechten per route, de ongeldige invoer en wat
  er wordt gelogd.
