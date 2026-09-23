# Documentatie @T IT Advies

Dit is de index. Alles wat je over dit project moet weten staat in deze map,
in Markdown, naast de code. Documentatie die niet meegroeit met de code is
erger dan geen documentatie, dus zie ook
[documentatieregels](development/documentatieregels.md) -- die regel is
bindend voor iedereen die hier werkt, mens of AI.

## Snel starten

Nieuw op dit project? Lees in deze volgorde:

1. [Lokale setup](development/setup.md) -- draaiend krijgen op WSL met Valet.
2. [Architectuuroverzicht](architecture/overzicht.md) -- hoe het in elkaar zit.
3. [Werkwijze en conventies](development/werkwijze.md) -- hoe we hier code schrijven.
4. [Documentatieregels](development/documentatieregels.md) -- wat je moet bijwerken en wanneer.

## Architectuur

- [Overzicht](architecture/overzicht.md) -- de stack, de lagen en waarom.
- [Frontend en animatie](architecture/frontend-en-animatie.md) -- Inertia, Vue, Tailwind, GSAP, Lenis, en wanneer wel of geen Three.js.
- [Mail en queues](architecture/mail-en-queues.md) -- verzending, provider, webhooks en het mailoverzicht.

## Beveiliging

- [Authenticatie en 2FA](security/authenticatie-en-2fa.md) -- Fortify, TOTP, recovery codes.
- [Gevoelige acties](security/gevoelige-acties.md) -- wanneer er opnieuw een authenticator-code nodig is.
- [Rollen en rechten](security/rollen-en-rechten.md) -- wie mag wat.
- [Spam- en botbescherming](security/spam-en-botbescherming.md) -- Turnstile, honeypot, rate limiting.
- [Logging](security/logging.md) -- wat we vastleggen en wat we nooit vastleggen.
- [E-mailauthenticatie](security/e-mailauthenticatie.md) -- SPF, DKIM en DMARC.

## Ontwikkeling

- [Lokale setup](development/setup.md)
- [Werkwijze en conventies](development/werkwijze.md)
- [Testen](development/testen.md)
- [Documentatieregels](development/documentatieregels.md)

## Beheer

- [Deployment](operations/deployment.md) -- hosting, queue workers, scheduler.
- [Monitoring](operations/monitoring.md) -- Pulse, logs, waar je kijkt als er iets mis is.

## Beslissingen

- [Beslislogboek](decisions/README.md) -- waarom bepaalde keuzes zijn gemaakt, en wat de alternatieven waren.
