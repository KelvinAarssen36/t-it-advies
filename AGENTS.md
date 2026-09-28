# Werkafspraken voor AI-assistenten

Dit bestand geldt voor elke AI die aan dit project werkt (Claude Code, Codex,
Copilot, Junie, Cursor). `CLAUDE.md` verwijst hiernaar; er is één bron.

## Regel 1: documentatie gaat mee met de code

**Bij iedere wijziging bepaal je zelf welke Markdown-documentatie moet worden
toegevoegd of bijgewerkt, en je doet dat in dezelfde wijziging.**

Vraag daar geen toestemming voor en stel het niet uit. De tabel die zegt wat
je waar bijwerkt staat in
[`docs/development/documentatieregels.md`](docs/development/documentatieregels.md).

Staat er geen passend document, maak er dan een en zet hem in de index van
[`docs/README.md`](docs/README.md).

## Regel 2: nooit geheimen vastleggen

Er gaat **nooit** een wachtwoord, TOTP-code, recovery code, secret of token
in een log, test, foutmelding, commentaar of document. Niet ingekort, niet
gehasht.

Voeg je een veld toe dat gevoelig kan zijn:

1. zet de sleutel in `config/security.php` onder `logging.redacted_keys`;
2. breid de dataprovider in `tests/Feature/Security/SecurityLoggerTest.php`
   uit met die sleutel.

Raak `.env` niet aan zonder dat er expliciet om gevraagd wordt. Nieuwe
variabelen komen altijd óók in `.env.example`, met een lege waarde.

## Regel 3: lees eerst

Voordat je code wijzigt, lees het document dat bij het gebied hoort:

| Gebied                              | Lees eerst                                                                                           |
| ----------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Structuur, routes, nieuwe pakketten | [docs/architecture/overzicht.md](docs/architecture/overzicht.md)                                     |
| Wat er nog open staat               | [docs/openstaand.md](docs/openstaand.md)                                                             |
| Meldingen na een handeling          | [docs/architecture/meldingen.md](docs/architecture/meldingen.md)                                     |
| Wie heeft wat gewijzigd             | [docs/security/activiteitenlogboek.md](docs/security/activiteitenlogboek.md)                         |
| Landing of portaal: wat is wat?     | [docs/README.md](docs/README.md)                                                                     |
| Vue, Tailwind, GSAP, Lenis          | [docs/architecture/frontend-en-animatie.md](docs/architecture/frontend-en-animatie.md)               |
| Licht én donker in het portaal      | [docs/architecture/huisstijl-en-kleuren.md](docs/architecture/huisstijl-en-kleuren.md)               |
| Kleuren, gradients, het logo        | [docs/architecture/huisstijl-en-kleuren.md](docs/architecture/huisstijl-en-kleuren.md)               |
| Keuzevelden en schuifbalken         | [docs/architecture/formulieren-en-schuifbalken.md](docs/architecture/formulieren-en-schuifbalken.md) |
| Talen en vertalingen                | [docs/architecture/vertalingen.md](docs/architecture/vertalingen.md)                                 |
| Mail, queues, webhooks              | [docs/architecture/mail-en-queues.md](docs/architecture/mail-en-queues.md)                           |
| Inloggen en 2FA                     | [docs/security/authenticatie-en-2fa.md](docs/security/authenticatie-en-2fa.md)                       |
| Acties die een verse code vragen    | [docs/security/gevoelige-acties.md](docs/security/gevoelige-acties.md)                               |
| Rollen en rechten                   | [docs/security/rollen-en-rechten.md](docs/security/rollen-en-rechten.md)                             |
| Logging                             | [docs/security/logging.md](docs/security/logging.md)                                                 |
| Spam en bots                        | [docs/security/spam-en-botbescherming.md](docs/security/spam-en-botbescherming.md)                   |
| Geplande taken, bewaartermijnen     | [docs/operations/onderhoudstaken.md](docs/operations/onderhoudstaken.md)                             |
| Tests                               | [docs/development/testen.md](docs/development/testen.md)                                             |
| Code-conventies                     | [docs/development/werkwijze.md](docs/development/werkwijze.md)                                       |

## Regel 4: controleer je werk

Zeg niet dat je klaar bent voordat dit slaagt:

```bash
composer ci:check
```

Dat draait Pint, PHPStan (niveau 7), vue-tsc en de tests. Loopt een test vast
op `Vite manifest not found`, draai dan eerst `npm run build`.

## Regel 5: elke CRUD krijgt tests

Bouw je een scherm waarmee de klant iets aanmaakt, wijzigt of verwijdert,
dan lever je dat af **mét** tests. Een CRUD zonder tests is niet af, en dat
is een afspraak met de opdrachtgever en geen richtlijn.

De volledige lijst van wat zo'n test moet afdekken staat in
[`docs/development/testen.md`](docs/development/testen.md). De kern: rechten
per afzonderlijke route, de gewone weg, ongeldige invoer, en bij een
gevoelige actie dat hij zonder verse code niet doorgaat.

Let op één ding dat makkelijk wordt overgeslagen: de bevestigingsvensters in
de browser zijn geen beveiliging. Test het verzoek, niet het scherm.

## Regel 6: schrijf Nederlands

Commentaar, documentatie en commitberichten in het Nederlands. Klassenamen,
methodenamen en variabelen blijven Engels. Gebruikersteksten in `__()`.

Commentaar legt uit **waarom**, niet wat. Vooral bij beveiliging: wie niet
weet waarom recovery codes bij gevoelige acties geweigerd worden, "repareert"
dat vroeg of laat.

## Valkuilen in dit project

Dingen die niet vanzelfsprekend zijn en waar je op zult stuklopen als je ze
niet weet:

- **Listeners worden automatisch ontdekt.** Laravel registreert elke klasse in
  `app/Listeners` met een methode die met `handle` begint. Meld je die
  daarnaast ook aan in een service provider, dan draait hij twee keer. Daarom
  heten de methoden van `RecordSecurityEvents` bewust `record*`.
- **Fortify heeft zijn eigen encrypter.** Ontsleutel `two_factor_secret`
  altijd met `Fortify::currentEncrypter()`, nooit met de `Crypt`-facade.
- **Wayfinder heeft form-varianten nodig.** Draai je `wayfinder:generate` met
  de hand, gebruik dan `--with-form`, anders faalt `npm run types:check`.
- **Lenis en GSAP moeten gekoppeld blijven.** Zie
  `resources/js/lib/motion.ts`; haal de ticker-koppeling niet weg.
- **Animaties ruimen zichzelf op.** Inertia vervangt de pagina zonder
  herladen. Roep de opruimfunctie aan in `onBeforeUnmount`.
- **`prefers-reduced-motion` mag geen lege pagina opleveren.** Zet elementen
  op hun eindtoestand in plaats van de animatie over te slaan.
- **De tests draaien op SQLite in het geheugen**, niet op MySQL.
- **PHPUnit 12 kent `@dataProvider` niet meer.** Gebruik het attribuut
  `#[DataProvider]`.

## Wat je niet doet

- Validatie overslaan omdat de frontend het al controleert.
- De rechtencontrole alleen in de frontend doen; `auth.permissions` in Inertia
  is uitsluitend cosmetisch.
- Een pakket toevoegen zonder in het architectuuroverzicht op te schrijven
  waarom.
- Rechtstreeks op `main` committen.
- Een PHPStan-melding onderdrukken zonder commentaar dat uitlegt waarom de
  analyse het hier mis heeft.
