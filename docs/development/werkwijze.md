# Werkwijze en conventies

## Taal

Code, commentaar, documentatie en commitberichten zijn in het Nederlands.
Klassenamen, methodenamen en variabelen blijven Engels: dat is de taal van
het framework, en half vertalen levert alleen verwarring op.

Gebruikersteksten staan in `__()`, zodat ze later vertaalbaar zijn zonder de
code aan te raken.

## Waar hoort wat

| Soort code                       | Plek                                         |
| -------------------------------- | -------------------------------------------- |
| Validatie                        | Een FormRequest, niet in de controller.      |
| Autorisatie                      | Route-middleware (`can:`) of een policy.     |
| Vaste waardenlijsten             | Een enum in `app/Enums`, geen losse strings. |
| Logica die niet van HTTP afhangt | `app/Support`, niet in de controller.        |
| Iets dat mag wachten             | Een queued job of een queued mailable.       |

Controllers blijven dun: verzoek aannemen, iets laten gebeuren, antwoord
teruggeven.

## Enums boven strings

Gebeurtenistypen en statussen zijn enums (`SecurityEventType`, `MailStatus`,
`SecurityOutcome`). Daardoor kunnen de filters in het beveiligde gedeelte, de
tests en de code niet uit elkaar lopen zonder dat je het merkt.

Voeg je een waarde toe, geef hem dan ook een Nederlands label in de
`label()`-methode.

## Commentaar

Schrijf op **waarom**, niet wat. Dit soort commentaar hoort erbij:

```php
// De status gaat alleen vooruit in de levensloop van een mail: een laat
// binnenkomend delivered-event mag een eerdere bounce niet wegpoetsen.
```

Dit soort niet:

```php
// Sla het model op
$model->save();
```

Vooral bij beveiliging is het waarom belangrijk. Iemand die niet weet waarom
recovery codes bij gevoelige acties geweigerd worden, "repareert" dat vroeg of
laat.

## Statische analyse en formattering

```bash
composer lint           # pint, formatteert
composer lint:check     # pint, controleert alleen
composer types:check    # phpstan op niveau 7
npm run types:check     # vue-tsc
composer ci:check       # alles
```

PHPStan draait op niveau 7 en moet schoon blijven. Een melding onderdrukken
mag, maar dan met een regel commentaar die uitlegt waarom de analyse het hier
mis heeft.

## Tests

Zie [testen](testen.md). De korte versie: elke beveiligingsafspraak heeft een
test, en de test die bewijst dat er niets gevoeligs gelogd wordt is niet
optioneel.

## Migraties

- Eén onderwerp per migratie.
- Altijd een werkende `down()`.
- Indexen op kolommen waarop je filtert. In `security_events` zitten die er
  al: op `event`, `outcome`, `created_at` en de combinaties daarvan.
- Geldbedragen als integer in centen, nooit als float.

## Git

- Werk op een branch, niet rechtstreeks op `main`.
- Commitberichten in het Nederlands, in de gebiedende wijs: "Voeg
  mailoverzicht toe", niet "Toegevoegd" of "toevoeging".
- Eén logische wijziging per commit, inclusief de bijbehorende documentatie.

## Wat je niet doet

- Geheimen in git. `.env` staat in `.gitignore` en dat blijft zo. Nieuwe
  variabelen komen in `.env.example`, met een lege waarde.
- Validatie overslaan omdat de frontend het al controleert.
- De rechtencontrole alleen in de frontend doen.
- Wachtwoorden, codes, secrets of recovery codes in een log, test, foutmelding
  of document zetten.
- Een pakket toevoegen zonder in [het overzicht](../architecture/overzicht.md)
  op te schrijven waarom.
