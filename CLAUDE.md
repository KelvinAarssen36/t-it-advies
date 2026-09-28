# CLAUDE.md

De werkafspraken voor dit project staan in [`AGENTS.md`](AGENTS.md). Lees dat
bestand voordat je iets wijzigt; er is bewust één bron voor alle
AI-assistenten.

De drie regels die je in elk geval moet kennen:

1. **Documentatie gaat mee met de code.** Bij iedere wijziging bepaal je zelf
   welke Markdown in `docs/` moet worden toegevoegd of bijgewerkt, en je doet
   dat in dezelfde wijziging.
2. **Nooit geheimen vastleggen.** Geen wachtwoord, TOTP-code, recovery code,
   secret of token in een log, test, foutmelding of document.
3. **Elke CRUD krijgt tests.** Een beheerscherm waarmee de klant iets
   aanmaakt, wijzigt of verwijdert is niet af zonder tests. De lijst van wat
   die moeten afdekken staat in
   [`docs/development/testen.md`](docs/development/testen.md).

Controleer je werk met `composer ci:check` voordat je zegt dat je klaar bent.
