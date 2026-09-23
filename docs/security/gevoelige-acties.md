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
    ->middleware(['can:manage users', '2fa.confirm'])
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

Allebei met `can:manage users` ernaast. Zie
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
