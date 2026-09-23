# Authenticatie en tweestapsverificatie

## Fortify

Authenticatie loopt volledig via [Laravel Fortify](https://laravel.com/docs/fortify).
Fortify registreert de routes en controllers; wij leveren alleen de schermen
(Inertia-pagina's) en de instellingen. Zie
[`app/Providers/FortifyServiceProvider.php`](../../app/Providers/FortifyServiceProvider.php).

Ingeschakelde functies (`config/fortify.php`):

- wachtwoord herstellen,
- e-mailverificatie,
- tweestapsverificatie met bevestiging,
- passkeys,
- wachtwoordbevestiging bij gevoelige schermen.

## Registratie staat uit

Deze site heeft één gebruiker: de eigenaar. Een open registratieformulier zou
vreemden een account geven op een applicatie die verder alleen voor hem is,
en elk account dat je niet nodig hebt is een ingang die je wel moet bewaken.

`Features::registration()` staat daarom niet in de lijst hierboven. De route
`/register` bestaat niet, de pagina is weg en de inloglink ernaartoe ook.
[`RegistrationTest`](../../tests/Feature/Auth/RegistrationTest.php) faalt
zodra iemand de feature terugzet -- zo blijft het een bewuste beslissing en
kan hij niet ongemerkt terugkomen bij het bijwerken van de starter kit.

Er staat ook **geen inlogknop op de publieke site**. Zie
[frontend en animatie](../architecture/frontend-en-animatie.md).

## Een account aanmaken

```bash
php artisan user:create
php artisan user:create --name="De Klant" --email=klant@example.com --role=admin
```

Het commando vraagt om de naam, het e-mailadres en twee keer het wachtwoord,
en controleert alles met dezelfde regels als de rest van de applicatie.

**Het wachtwoord kan bewust geen optie zijn.** Opties belanden in de
shell-geschiedenis en zijn op een gedeelde server zichtbaar in `ps`. Vragen
is de enige manier waarop het nergens blijft staan. Het komt ook niet in het
beveiligingslogboek; er is een test die dat afdwingt.

Het account wordt meteen als geverifieerd aangemerkt. Zonder geverifieerd
e-mailadres komt de eigenaar niet in het beheergedeelte, en op het moment dat
je dit commando draait staat de mailprovider vaak nog niet ingesteld.

Zet er daarna direct tweestapsverificatie op.

## Tweestapsverificatie

### Hoe het werkt

2FA gebruikt TOTP: een authenticator-app (Google Authenticator, 1Password,
Bitwarden, Aegis) berekent elke dertig seconden een zescijferige code uit een
gedeeld geheim. Bij het inschakelen toont de applicatie een QR-code met dat
geheim; daarna moet de gebruiker één geldige code invoeren om te bevestigen
dat het scannen is gelukt (`'confirm' => true`).

Dat bevestigen is belangrijk. Zonder die stap kun je 2FA aanzetten met een
verkeerd gescande QR-code en jezelf buitensluiten.

### Opslag

Drie kolommen op `users`:

| Kolom                       | Inhoud                                                                                      |
| --------------------------- | ------------------------------------------------------------------------------------------- |
| `two_factor_secret`         | Het TOTP-geheim, **versleuteld**.                                                           |
| `two_factor_recovery_codes` | De recovery codes, **versleuteld** opgeslagen als JSON.                                     |
| `two_factor_confirmed_at`   | Wanneer de gebruiker een geldige code heeft ingevoerd. Is dit leeg, dan is 2FA niet actief. |

De versleuteling doet Fortify zelf. Gebruik altijd
`Fortify::currentEncrypter()` om te ontsleutelen, niet de `Crypt`-facade
rechtstreeks: Fortify kan zijn encrypter vervangen (bijvoorbeeld tijdens
sleutelrotatie) en dan lopen de twee uiteen.

Alle drie de kolommen staan in de `#[Hidden]`-lijst van het User-model, zodat
ze nooit per ongeluk in een JSON-respons of Inertia-prop belanden.

### Bij het inloggen

Na een geldig wachtwoord stuurt Fortify een gebruiker met bevestigde 2FA naar
de challenge. Daar mag **wel** een recovery code worden gebruikt: dat is
precies waar recovery codes voor zijn, namelijk weer binnenkomen als je je
authenticator kwijt bent.

Wordt een recovery code gebruikt, dan wordt hij verbruikt en vervangen door
een nieuwe. Dat gebeurt automatisch, en het wordt gelogd.

### Bij gevoelige acties

Daar gelden andere regels. Zie [gevoelige acties](gevoelige-acties.md).

## Rate limiting

| Limiter            | Grens                    | Sleutel                      |
| ------------------ | ------------------------ | ---------------------------- |
| `login`            | 5 per minuut             | e-mailadres + IP             |
| `two-factor`       | 5 per minuut             | de sessie van de inlogpoging |
| `sensitive-action` | 5 per minuut             | gebruiker-ID                 |
| `contact`          | 3 per minuut, 20 per dag | IP                           |
| `webhook`          | 120 per minuut           | IP                           |

`login` en `two-factor` staan in `FortifyServiceProvider` omdat Fortify die
routes registreert. De rest staat in `AppServiceProvider`.

Let op het verschil in sleutel: gevoelige acties worden begrensd per
**gebruiker**, niet per IP. Een aanvaller met meerdere IP-adressen schiet daar
niets mee op.

## Passkeys

Passkeys staan aan. Ze zijn een alternatief voor wachtwoord + 2FA en zijn
bestand tegen phishing, omdat de sleutel aan het domein vastzit. De inrichting
komt volledig uit Fortify; wij hebben er niets aan toegevoegd.

Voor een gevoelige actie vragen we bewust een TOTP-code en geen passkey, zodat
er één duidelijke, testbare route is. Wil je passkeys ook daar toestaan, pas
dan `RequireTwoFactorConfirmation` en `ConfirmTwoFactorController` aan, breid
de tests uit en werk [gevoelige acties](gevoelige-acties.md) bij.

## Wachtwoordeisen

In productie: minimaal twaalf tekens, hoofd- en kleine letters, cijfers,
symbolen, en niet voorkomend in bekende datalekken (`uncompromised()`).
Lokaal gelden geen eisen, zodat testen niet onnodig omslachtig wordt. Zie
`AppServiceProvider::configureDefaults()`.

## Wat wordt er gelogd

Alles, behalve de geheimen zelf. Zie [logging](logging.md).
