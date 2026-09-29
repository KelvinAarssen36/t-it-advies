# Werkafspraken voor AI-assistenten

Dit bestand geldt voor elke AI die aan dit project werkt (Claude Code, Codex,
Copilot, Junie, Cursor). `CLAUDE.md` verwijst hiernaar; er is één bron.

## Regel 1: documentatie gaat mee met de code -- voor ons én voor de klant

Er zijn **twee** lezers, en ze lezen niet hetzelfde.

### 1a. Voor ons: `docs/`

**Bij iedere wijziging bepaal je zelf welke Markdown-documentatie moet worden
toegevoegd of bijgewerkt, en je doet dat in dezelfde wijziging.**

Vraag daar geen toestemming voor en stel het niet uit. De tabel die zegt wat
je waar bijwerkt staat in
[`docs/development/documentatieregels.md`](docs/development/documentatieregels.md).

Staat er geen passend document, maak er dan een en zet hem in de index van
[`docs/README.md`](docs/README.md).

### 1b. Voor de klant: de handleiding in het portaal

**Elk afgerond onderdeel krijgt óók een kaart in
[`settings/Documentatie.vue`](resources/js/pages/settings/Documentatie.vue),
in dezelfde wijziging waarin het onderdeel af is.**

Dat is de handleiding die de eigenaar zelf opzoekt, onder Instellingen ->
Documentatie. Hij leest `docs/` nooit; die map gaat over code en keuzes, en
deze pagina gaat over knoppen: wat er gebeurt als je erop drukt, en wat zijn
bezoekers daarna zien.

Een module die de eigenaar niet kan terugvinden is geen module. "Afgerond"
betekent hier: de klant kan er iets mee. Een migratie zonder scherm telt
niet; een beheerscherm waarop hij kan toevoegen, wijzigen of verwijderen
wel.

Drie onderdelen, en die volgen de zijbalk: **Basis** (alles wat overal
hetzelfde werkt), **Website** (één kaart per module) en **Beheer**. Hoe je
zo'n kaart schrijft, welk pictogram je kiest en waar je niet over schrijft,
staat in
[`docs/architecture/uitleg-voor-de-eigenaar.md`](docs/architecture/uitleg-voor-de-eigenaar.md).

Vergeet de Engelse kant niet: elke zin gaat door `$t()` en hoort in
`lang/en.json`. `TranslationsTest` valt om als je dat overslaat.

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

Gebruik voor die vensters
[`bevestig()`](resources/js/lib/bevestiging.ts) en verzin er geen eigen. Het
aantal vragen ligt vast -- bewerken twee keer, aanmaken en verwijderen één
keer -- en dat zit in die functie, juist zodat niemand het per scherm anders
invult. Zie [meldingen](docs/architecture/meldingen.md).

## Regel 6: verplichte velden krijgen een sterretje

Elk formulierveld dat verplicht is, draagt een sterretje bij zijn label.
Dat geldt overal in de applicatie -- portaal én publieke site -- en is een
afspraak met de opdrachtgever, geen smaakkwestie. Een veld zonder sterretje
leest de klant als optioneel, dus vergeten is niet slordig maar onjuist.

Doe het met de prop op het labelcomponent en niet met een tekentje dat je
zelf achter de tekst typt:

```vue
<Label for="role_nl" verplicht>{{ $t('Functie') }}</Label>
```

Hoe je weet welke velden het zijn: loop de `rules()` van de bijbehorende
FormRequest langs en zet er een bij alles met `required`. Eén grens: een
keuzelijst die al een waarde heeft kún je niet leeglaten, en daar is een
sterretje dus ruis. Zie
[formulieren](docs/architecture/formulieren-en-schuifbalken.md#verplichte-velden).

**Let op: er is een tweede sterretje in dit project, en dat betekent iets
anders.** In de zijbalk markeert een klein blauw sterretje de hoofdpagina
van een groep (`hoofd: true` op het menu-item). Dat is bewust een andere
kleur en een andere maat dan het rode sterretje bij een veld. Hang er geen
derde betekenis aan; zie
[frontend en animatie](docs/architecture/frontend-en-animatie.md#het-sterretje-bij-de-hoofdpagina-van-een-groep).

## Regel 7: aanmaken, bewerken en verwijderen hebben elk hun eigen kleur

Drie handelingen, drie kleuren, overal in het portaal dezelfde:

| Handeling   | Kleur               | `variant`                           |
| ----------- | ------------------- | ----------------------------------- |
| Aanmaken    | merkblauw `#0787E8` | `aanmaken` / `aanmaken-zacht`       |
| Bewerken    | oker (`--bewerken`) | `bewerken` / `bewerken-zacht`       |
| Verwijderen | rood `#D94A4A`      | `verwijderen` / `verwijderen-zacht` |

De volle vorm is voor de knop die het echt doet: de knop bovenaan een
scherm, de opslaan-knop in een venster, de bevestigknop. De `-zacht` vorm is
voor een knop die in een rij naast tien soortgenoten staat.

Zet dus nooit een kale `<Button>` of een `variant="outline"` op iets wat
aanmaakt, bewerkt of verwijdert -- dan is het voor de klant een knop als
alle andere. Zie
[huisstijl](docs/architecture/huisstijl-en-kleuren.md#de-drie-handelingen)
voor de kleuren en
[formulieren](docs/architecture/formulieren-en-schuifbalken.md#de-knop-van-een-handeling)
voor waar welke vorm heen gaat.

## Regel 8: schrijf Nederlands

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
- **Alles op de publieke site dat zelf moet schuiven, krijgt
  `data-lenis-prevent`.** Lenis vangt anders het muiswiel af en scrolt de
  pagina erachter in plaats van wat je voor je hebt. Het portaal heeft er
  geen last van; daar draait Lenis niet.
- **Animaties ruimen zichzelf op.** Inertia vervangt de pagina zonder
  herladen. Roep de opruimfunctie aan in `onBeforeUnmount`.
- **`prefers-reduced-motion` mag geen lege pagina opleveren.** Zet elementen
  op hun eindtoestand in plaats van de animatie over te slaan.
- **De tests draaien op SQLite in het geheugen**, niet op MySQL.
- **Een formulier met een bestand erin gaat als `FormData` de deur uit.**
  Een wijziging gebruikt dan `POST` met `_method: 'put'`; Laravel leest die
  omweg niet uit JSON. En zet `Storage::fake()` in de test, anders schrijf
  je in `storage/app/public`.
- **Een zoekveld dat op `LIKE` draait moet `%` en `_` ontsnappen.** Zonder
  dat geeft "100%" de hele lijst terug. Zet de `ESCAPE`-clausule er
  expliciet bij, want MySQL en SQLite gaan er anders mee om.
- **De componenten in `components/ui/` komen Engels geleverd.** Loop een
  nieuwe na op `sr-only`, `aria-label`, `title`, `alt` en `placeholder`;
  daar zit vertaalde tekst in die je niet ziet staan. Zie
  [vertalingen](docs/architecture/vertalingen.md).
- **Een kruimelpad met een detailpagina erin hoort een derde kruimel te
  krijgen.** De laatste is nooit aanklikbaar, dus zonder die derde kun je
  vanaf een detailpagina niet terug naar het overzicht. Inertia roept
  `layout` als functie aan met de paginaprops, dus de naam van het item
  kan erin.
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
