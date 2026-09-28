# Gevoelige acties

## Het idee

Ingelogd zijn is niet genoeg voor alles. Een sessie kan gestolen zijn, een
laptop kan openstaan, een browser kan van iemand anders zijn. Voor handelingen
die je niet wilt terugdraaien vragen we daarom opnieuw om een **verse
authenticator-code**.

De code bewijst iets wat een gestolen sessie niet heeft: fysieke toegang tot
het apparaat met de authenticator.

## Recovery codes gelden hier niet

Dit is de belangrijkste regel van dit document.

Bij het inloggen mag een recovery code worden gebruikt, want dan probeer je
weer binnen te komen nadat je je authenticator kwijt bent. Bij een gevoelige
actie ben je al binnen. Een recovery code bewijst daar niets: het is een
stukje tekst dat op een briefje kan staan, in een screenshot, of in de
mailbox die net is overgenomen.

`ConfirmTwoFactorController` weigert daarom alles wat geen zes cijfers is, en
legt die poging vast als `sensitive.recovery_code_refused`. De gebruiker krijgt
te zien waarom.

## Hoe je het gebruikt

Zet de middleware `2fa.confirm` op de route:

```php
Route::delete('admin/users/{user}', [UserController::class, 'destroy'])
    ->middleware(['can:manage portal', '2fa.confirm'])
    ->name('admin.users.destroy');
```

Let op de combinatie. `can:` controleert of iemand het recht heeft,
`2fa.confirm` controleert of hij het op dit moment zelf is. Je hebt ze
allebei nodig; ze beantwoorden verschillende vragen.

## Wat de middleware doet

[`RequireTwoFactorConfirmation`](../../app/Http/Middleware/RequireTwoFactorConfirmation.php)
loopt drie stappen af:

1. **Geen 2FA ingesteld?** De actie gaat niet door. De gebruiker gaat naar de
   beveiligingsinstellingen met de melding dat 2FA nodig is. Dit wordt gelogd
   als `sensitive.denied`. We laten de actie bewust niet stilletjes toe: dan
   zou de bescherming te omzeilen zijn door 2FA simpelweg uit te laten.
2. **Recent bevestigd?** Dan mag het verzoek door. "Recent" is
   `SENSITIVE_ACTION_TTL` seconden, standaard vijftien minuten.
3. **Anders** wordt onthouden waar de gebruiker heen wilde en gaat hij naar
   het bevestigingsscherm. Daarna komt hij terug op de plek waar hij was.

**Let op bij stap 3 en niet-GET-routes.** Na het bevestigen stuurt Laravel de
gebruiker met een GET naar de onthouden URL. Voor een `DELETE` of `PUT`
bestaat die GET niet -- hij zou op een 405 landen. De middleware onthoudt
daarom bij die methoden de pagina waar hij vandaan kwam. Daar staat de knop,
en zijn bevestiging is dan vers genoeg om hem meteen nog een keer in te
drukken. Haal dat niet weg zonder een andere oplossing: zonder die regel is
elke gevoelige actie achter een formulier stuk.

## Waar het nu op zit

| Route                          | Actie                    |
| ------------------------------ | ------------------------ |
| `PUT admin/users/{user}/roles` | Rollen van een gebruiker |
| `DELETE admin/users/{user}`    | Een account verwijderen  |

Allebei met `can:manage portal` ernaast. Zie
[rollen en rechten](rollen-en-rechten.md) voor de twee vangnetten die daar
nog bovenop zitten.

Wat er bewust **niet** achter zit: je eigen account verwijderen via
[instellingen](../../app/Http/Controllers/Settings/ProfileController.php).
Dat vraagt om je wachtwoord, niet om een authenticator-code. Zou het wel een
verse code vragen, dan kan een gebruiker zonder 2FA zijn eigen account nooit
meer opzeggen -- en dat is zijn recht, geen beheerdershandeling.

## Instellingen

In `config/security.php`:

```php
'sensitive_actions' => [
    'confirmation_ttl' => (int) env('SENSITIVE_ACTION_TTL', 900),
    'session_key' => 'security.sensitive_action_confirmed_at',
],
```

Houd de geldigheidsduur kort. Zet je hem op een uur, dan is de check nog maar
weinig waard; dan is de sessie in de praktijk net zo goed.

## Het slotje, en de weg terug

Achter de beveiligingsinstellingen zit een wachtwoordbevestiging. Dat is
precies het soort drempel waar je van schrikt als je hem niet ziet aankomen:
je klikt op een menu-item en krijgt ineens een scherm dat om je wachtwoord
vraagt. Twee dingen vangen dat op.

**Een slotje naast "Beveiliging".**
[`PasswordLock.vue`](../../resources/js/components/PasswordLock.vue) staat in
de instellingennavigatie en is dicht wanneer er nog om je wachtwoord wordt
gevraagd, en open wanneer je zo doorloopt. Open is het blauw van de knoppen
met een zachte gloed; dicht is gedempt en rustig. Bewust géén rood of
oranje: er is niets mis, er komt alleen nog een vraag.

Of het slot open staat komt uit de gedeelde prop `auth.passwordConfirmed`.
Die maakt in
[`HandleInertiaRequests`](../../app/Http/Middleware/HandleInertiaRequests.php)
dezelfde som als de middleware: het tijdstip uit de sessie afgezet tegen
`config('auth.password_timeout')`. Staat die som er niet, dan blijft het
slotje open terwijl je wél opnieuw moet bevestigen -- en dan is het erger
dan geen slotje.

**Dit is weergave, geen beveiliging.** Wie die prop in zijn browser omzet
krijgt een open slotje te zien en verder niets: de middleware doet de
controle opnieuw, op de sessie waar de browser niet bij kan.

**Inloggen telt als bevestigen.** De `LoginResponse` zet
`auth.password_confirmed_at`, dus binnen de bewaartermijn (`password_timeout`,
drie uur) na het inloggen gaat de beveiligingspagina open zonder extra vraag.

Zonder die regel vraagt Laravel opnieuw om je wachtwoord, ook als je dertig
seconden eerder hebt ingelogd. Dat voelt niet als zorgvuldigheid maar als een
fout -- en erger: het leert iemand zijn wachtwoord klakkeloos in te tikken
zodra het gevraagd wordt, precies het gedrag dat die bevestiging moest
voorkomen.

Wat we ervoor inleveren is te overzien. De bevestiging beschermt tegen een
sessie die onbeheerd openstaat, en die grens schuift hiermee op naar drie uur
na het inloggen. De handelingen die je écht niet wilt terugdraaien -- rollen
wijzigen, een account verwijderen -- zitten achter `2fa.confirm` en vragen
sowieso om een verse code uit de authenticator, ongeacht wanneer je hebt
ingelogd.

**Een weg terug op het bevestigingsscherm.** Onder het formulier staat
"Toch niet, terug naar het dashboard". Zonder die link is dat scherm een
doodlopende straat: je komt er ongevraagd terecht, en uitloggen zou de enige
uitweg zijn. Dat is buiten verhouding voor iemand die zich bedenkt.

Bewust een link naar het dashboard en niet de terugknop van de browser: die
brengt je terug op de pagina die je juist niet mag zien, waarna je meteen
weer op het bevestigingsscherm staat.

## Rate limiting

Het bevestigingsscherm zit achter `throttle:sensitive-action`: vijf pogingen
per minuut, per gebruiker. Zonder die grens kun je een zescijferige code
brute-forcen. Elke keer dat de grens wordt geraakt komt er een regel
`throttle.limited` in het logboek.

## Wat wordt vastgelegd

| Gebeurtenis                       | Wanneer                                    |
| --------------------------------- | ------------------------------------------ |
| `sensitive.challenged`            | Het bevestigingsscherm is getoond.         |
| `sensitive.confirmed`             | Een geldige code is ingevoerd.             |
| `sensitive.failed`                | Een verkeerde code.                        |
| `sensitive.recovery_code_refused` | Er is iets ingevuld dat geen TOTP-code is. |
| `sensitive.denied`                | De gebruiker heeft geen bevestigde 2FA.    |

De ingevoerde code zelf komt **nooit** in het logboek; `code` staat op de
redactielijst in `config/security.php`. Er is een test die dat afdwingt.

## Tests

[`tests/Feature/Security/SensitiveActionTest.php`](../../tests/Feature/Security/SensitiveActionTest.php)
legt alle afspraken hierboven vast, inclusief het weigeren van recovery codes
en de rate limiting. Verander je iets aan dit gedrag, dan hoort die test mee
te veranderen -- en dit document ook.
