# Architectuuroverzicht

## Uitgangspunt

Alles zit in één Laravel-project. Geen aparte frontend-app, geen losse
API-service, geen microservices. Dat is een bewuste keuze: het bedrijf is één
site met één beveiligd gedeelte, en één project betekent één deploy, één set
credentials en één plek waar je zoekt als er iets mis is. Hosting blijft
daardoor eenvoudig -- Laravel Cloud, Forge, Ploi of een gewone VPS kunnen dit
allemaal aan.

## Stack

| Laag          | Keuze                     | Versie bij aanvang        |
| ------------- | ------------------------- | ------------------------- |
| Framework     | Laravel                   | 13                        |
| PHP           | PHP                       | 8.3 of hoger (lokaal 8.5) |
| Frontend-brug | Inertia.js                | 3                         |
| UI            | Vue 3 + TypeScript        | 3.5                       |
| Styling       | Tailwind CSS              | 4                         |
| Bundler       | Vite                      | 8                         |
| Animatie      | GSAP + ScrollTrigger      | 3                         |
| Smooth scroll | Lenis                     | 1                         |
| Database      | MySQL                     | 8                         |
| Auth          | Laravel Fortify           | 1                         |
| Rechten       | spatie/laravel-permission | 8                         |
| Monitoring    | Laravel Pulse             | 1                         |
| Mail          | Resend (verwisselbaar)    | --                        |
| Tests         | PHPUnit                   | 12                        |

Waarom Inertia en niet een losse SPA met een API: met Inertia blijft de
autorisatie, validatie en routing volledig in Laravel. Je hoeft geen tweede
keer een rechtenmodel te bouwen in JavaScript, en er is geen API-oppervlak dat
je apart moet beveiligen. De frontend blijft gewoon Vue.

## De lagen

```
Browser
  │
  ├── Publieke site        resources/js/pages/Welcome.vue
  │     └── Contactformulier → rate limiting → honeypot → Turnstile → queue
  │
  ├── Authenticatie        Fortify (login, registratie, 2FA, passkeys)
  │
  └── Beveiligd gedeelte   /admin, achter rol en recht
        ├── Mailoverzicht      wat is verstuurd, wat meldt de provider
        ├── Beveiligingslogboek geslaagde en mislukte pogingen
        ├── Gebruikers         rollen en accounts, wijzigen vraagt 2FA
        └── Pulse              prestaties en achtergrondtaken

Scheduler
  ├── security:report        elk uur: meldt pieken per mail
  └── prune-taken            's nachts: ruimt de logboeken op
```

De geplande taken staan in [`routes/console.php`](../../routes/console.php)
en zijn beschreven in
[onderhoudstaken](../operations/onderhoudstaken.md). Ze draaien alleen als de
cronregel op de server staat.

## Belangrijke mappen

| Pad                             | Wat er staat                                                         |
| ------------------------------- | -------------------------------------------------------------------- |
| `app/Console/Commands`          | De geplande taken: opruimen en alarmeren.                            |
| `app/Enums`                     | Vaste waardenlijsten: gebeurtenistypen, mailstatussen.               |
| `app/Http/Controllers/Admin`    | Het beveiligde gedeelte.                                             |
| `app/Http/Controllers/Security` | Bevestiging van gevoelige acties.                                    |
| `app/Http/Controllers/Webhooks` | Inkomende webhooks van externe diensten.                             |
| `app/Http/Middleware`           | Onder andere `RequireTwoFactorConfirmation`.                         |
| `app/Listeners`                 | Koppeling van auth- en mailevents aan de logging.                    |
| `app/Support/Maintenance`       | Opruimen van oude rijen in blokken.                                  |
| `app/Support/Security`          | SecurityLogger, Turnstile, handtekeningcontrole, de anomaliescanner. |
| `resources/js/lib/motion.ts`    | De animatielaag (GSAP + Lenis).                                      |
| `routes/console.php`            | De geplande taken.                                                   |
| `routes/web.php`                | Publiek en ingelogd.                                                 |
| `routes/admin.php`              | Het beveiligde gedeelte.                                             |
| `routes/webhooks.php`           | Endpoints voor externe diensten.                                     |
| `docs/`                         | Deze documentatie.                                                   |

## Eigen tabellen

Naast de tabellen van Laravel, Fortify en spatie/laravel-permission:

- **`security_events`** -- append-only logboek van beveiligingsgebeurtenissen.
  Wordt nooit bijgewerkt, alleen opgeruimd volgens het retentiebeleid. Zie
  [logging](../security/logging.md).
- **`mail_logs`** -- metadata van verstuurde mail plus de statusgeschiedenis
  van de provider. Zie [mail en queues](mail-en-queues.md).

## Wat er bewust niet in zit

- **Three.js.** Alleen toevoegen wanneer er echt 3D nodig is. Het is een
  zware afhankelijkheid en de meeste "3D-achtige" effecten kunnen met GSAP en
  CSS. Zie [frontend en animatie](frontend-en-animatie.md).
- **Een aparte API.** Komt er een mobiele app of externe integratie, dan pas.
- **Redis.** MySQL doet nu cache, sessies en queue. Bij groei is Redis de
  eerste stap, en dat is een configuratiewijziging, geen verbouwing.
