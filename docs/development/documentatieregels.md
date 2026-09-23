# Documentatieregels

Dit is de belangrijkste projectafspraak. Hij geldt voor iedereen die aan dit
project werkt, mens of AI.

## De regel

> **Bij iedere wijziging bepaal je zelf welke Markdown-documentatie moet
> worden toegevoegd of bijgewerkt, en je doet dat in dezelfde wijziging.**

Niet achteraf, niet "als er tijd is", niet in een apart ticket. Documentatie
die achterloopt is erger dan geen documentatie: hij wekt vertrouwen dat niet
terecht is, en de volgende die hem leest neemt een beslissing op verouderde
informatie.

Je hoeft niet te wachten tot iemand erom vraagt. Het is jouw verantwoordelijk-
heid om te bepalen wat er bijgewerkt moet worden.

## Wanneer werk je wat bij

| Je verandert                          | Werk bij                                                                        |
| ------------------------------------- | ------------------------------------------------------------------------------- |
| Een route, controller of de structuur | [architecture/overzicht.md](../architecture/overzicht.md)                       |
| Iets aan Vue, Tailwind, GSAP of Lenis | [architecture/frontend-en-animatie.md](../architecture/frontend-en-animatie.md) |
| Mailverzending, queues of webhooks    | [architecture/mail-en-queues.md](../architecture/mail-en-queues.md)             |
| Inloggen, 2FA of Fortify-instellingen | [security/authenticatie-en-2fa.md](../security/authenticatie-en-2fa.md)         |
| Welke acties een verse code vragen    | [security/gevoelige-acties.md](../security/gevoelige-acties.md)                 |
| Een rol of recht                      | [security/rollen-en-rechten.md](../security/rollen-en-rechten.md)               |
| Wat er wordt gelogd of geredigeerd    | [security/logging.md](../security/logging.md)                                   |
| Turnstile, honeypot of rate limiting  | [security/spam-en-botbescherming.md](../security/spam-en-botbescherming.md)     |
| Afzenders of DNS-records voor mail    | [security/e-mailauthenticatie.md](../security/e-mailauthenticatie.md)           |
| Deploy, hosting of achtergrondtaken   | [operations/deployment.md](../operations/deployment.md)                         |
| Een variabele in `.env`               | `.env.example` **en** de doc waar die variabele bij hoort                       |
| Een nieuw pakket                      | [architecture/overzicht.md](../architecture/overzicht.md), en zeg waarom        |
| Een keuze met een reëel alternatief   | [decisions/](../decisions/README.md)                                            |

Staat jouw wijziging er niet bij en is er geen passend document? Maak er een,
en zet hem in de index van [docs/README.md](../README.md).

## Hoe ziet goede documentatie er hier uit

- **Nederlands.** De code en de commentaren in de code ook.
- **Leg het waarom uit, niet alleen het wat.** Wat er gebeurt kun je in de
  code lezen. Waarom het zo is, en wat de afweging was, staat nergens anders.
- **Noem de valkuil.** Als iets op een voor de hand liggende manier fout kan
  gaan, schrijf dat dan op. Dat is vaak het waardevolste deel.
- **Link naar de code**, met een relatief pad. Dan is het in één klik te
  controleren.
- **Geen documentatie van wat de code al zegt.** Een opsomming van alle
  methoden van een klasse veroudert meteen en helpt niemand.
- **Wat er bewust niet in zit, is ook documentatie.** "Three.js is niet
  geïnstalleerd, en dit is waarom" voorkomt dat iemand het er voor de zekerheid
  bij zet.

## Voor AI-assistenten

De volledige werkafspraken staan in [`AGENTS.md`](../../AGENTS.md) in de
hoofdmap. Samengevat:

1. Lees eerst de relevante documenten in `docs/` voordat je code wijzigt.
2. Bepaal zelf welke documentatie meemoet met je wijziging en werk die bij in
   dezelfde wijziging. Vraag daar niet eerst toestemming voor.
3. Raak geen geheimen aan en zet nooit een wachtwoord, code, secret of
   recovery code in een log, test of document.
4. Draai `composer ci:check` voordat je zegt dat je klaar bent.
