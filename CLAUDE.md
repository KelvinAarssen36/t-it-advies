# CLAUDE.md

De werkafspraken voor dit project staan in [`AGENTS.md`](AGENTS.md). Lees dat
bestand voordat je iets wijzigt; er is bewust één bron voor alle
AI-assistenten.

De twee regels die je in elk geval moet kennen:

1. **Documentatie gaat mee met de code.** Bij iedere wijziging bepaal je zelf
   welke Markdown in `docs/` moet worden toegevoegd of bijgewerkt, en je doet
   dat in dezelfde wijziging.
2. **Nooit geheimen vastleggen.** Geen wachtwoord, TOTP-code, recovery code,
   secret of token in een log, test, foutmelding of document.

Controleer je werk met `composer ci:check` voordat je zegt dat je klaar bent.
