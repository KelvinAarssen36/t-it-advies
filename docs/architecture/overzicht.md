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
| Slepen        | @formkit/drag-and-drop    | 0.6                       |
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

| Pad                                     | Wat er staat                                                         |
| --------------------------------------- | -------------------------------------------------------------------- |
| `app/Console/Commands`                  | De geplande taken: opruimen en alarmeren.                            |
| `app/Enums`                             | Vaste waardenlijsten: gebeurtenistypen, mailstatussen.               |
| `app/Http/Controllers/Admin`            | Het beveiligde gedeelte.                                             |
| `app/Http/Controllers/Security`         | Bevestiging van gevoelige acties.                                    |
| `app/Http/Controllers/Website`          | Waarmee de klant zijn eigen site inricht.                            |
| `app/Http/Controllers/Webhooks`         | Inkomende webhooks van externe diensten.                             |
| `app/Http/Middleware`                   | Onder andere `RequireTwoFactorConfirmation`.                         |
| `app/Listeners`                         | Koppeling van auth- en mailevents aan de logging.                    |
| `app/Support/Maintenance`               | Opruimen van oude rijen in blokken.                                  |
| `app/Support/Page`                      | Welke onderdelen de landingspagina heeft en of er inhoud in zit.     |
| `app/Support/Security`                  | SecurityLogger, Turnstile, handtekeningcontrole, de anomaliescanner. |
| `app/Support/Translation`               | Het automatisch vertalen; alleen MyMemoryVertaler kent de dienst.    |
| `resources/js/components/site`          | De bouwstenen van de publieke site.                                  |
| `resources/js/components/site/sections` | De onderdelen die de klant kan verslepen.                            |
| `resources/js/layouts`                  | `PublicLayout`, `AppLayout`, `AuthLayout`.                           |
| `resources/js/lib/motion.ts`            | De animatielaag (GSAP + Lenis).                                      |
| `routes/console.php`                    | De geplande taken.                                                   |
| `routes/web.php`                        | Publiek en ingelogd.                                                 |
| `routes/website.php`                    | Het inrichten van de website van de klant.                           |
| `routes/admin.php`                      | Het beveiligde gedeelte.                                             |
| `routes/webhooks.php`                   | Endpoints voor externe diensten.                                     |
| `docs/`                                 | Deze documentatie.                                                   |

## Eigen tabellen

Naast de tabellen van Laravel, Fortify en spatie/laravel-permission:

- **`security_events`** -- append-only logboek van beveiligingsgebeurtenissen.
  Wordt nooit bijgewerkt, alleen opgeruimd volgens het retentiebeleid. Zie
  [logging](../security/logging.md).
- **`mail_logs`** -- metadata van verstuurde mail plus de statusgeschiedenis
  van de provider. Zie [mail en queues](mail-en-queues.md).
- **`activity_entries`** -- append-only logboek van wat er aan de inhoud van
  de website is veranderd, met de oude waarde erbij. Zie
  [activiteitenlogboek](../security/activiteitenlogboek.md).
- **`page_sections`** -- de volgorde van de onderdelen op de landingspagina
  en of ze aanstaan. Wélke onderdelen er bestaan staat níet hier maar in
  code; zie [pagina-indeling](pagina-indeling.md).
- **`experiences`** -- de tijdlijn met functies en organisaties, met een
  kolom per taal voor wat vertaald wordt, een vlag of hij online staat, en
  het pad naar een geüpload logo. Zie [de module](modules/ervaring.md).
- **`experience_stats`** -- de drie cijfers boven die tijdlijn, voor zover
  de klant ze zelf invult. Leeg betekent "uitrekenen op basis van de
  tijdlijn", en dat is de normale toestand. Wélke cijfers er bestaan staat
  net als bij `page_sections` in code.

- **`certificates`** -- de behaalde certificaten, met de uitgever, de
  datums, een certificaatnummer en het pad naar het logo van die
  uitgever. Zie [de module](modules/certificaten.md).
- **`educations`** -- de opleidingen die onder die certificaten staan.
  Bewust kaler: geen logo, geen nummer, en een volgorde die zichzelf op
  periode regelt.
- **`statistics`** -- de vaardigheden en kengetallen, met per rij de vorm
  waarin ze op de site komen (balk, ring of teller) en een vrij
  groepsveld waarop ze worden ingedeeld. Dat groepsveld is ook de
  **sleutel** van de groep en niet alleen het kopje; in het
  indelingsvenster is elke groep een vak. Zie
  [de module](modules/statistieken.md).

- **`section_headings`** -- het opschrift, de titel en de zin boven élk
  onderdeel, met een regel per `PageSectionKey`. Dit waren drie bijna
  identieke tabellen (`hero_headings`, `experience_headings`,
  `service_headings`); zie [kopteksten](kopteksten.md).

Geüploade bestanden staan **niet** in de database maar op de `public`-schijf
uit `config/filesystems.php`, met alleen hun pad in de tabel. Verhuizen ze
ooit naar een andere opslag, dan verandert er één regel in de configuratie
en geen rij in de database. Wel moet `php artisan storage:link` op elke
omgeving hebben gedraaid; zie [deployment](../operations/deployment.md).

**De grenzen aan wat de klant mag uploaden staan óók niet in de database**
maar in [`config/media.php`](../../config/media.php): hoe groot een bestand
mag zijn en hoeveel pixels het hoogstens mag tellen. Dat is met opzet geen
instelling die de klant zelf kan verzetten -- hij hangt samen met wat de
browser vooraf doet én met wat PHP op de server toestaat, en die drie
horen bij elkaar te blijven. Zie
[de PHP-instellingen voor uploads](../operations/deployment.md#de-php-instellingen-voor-uploads).

## Wat er bewust niet in zit

- **Three.js.** Alleen toevoegen wanneer er echt 3D nodig is. Het is een
  zware afhankelijkheid en de meeste "3D-achtige" effecten kunnen met GSAP en
  CSS. Zie [frontend en animatie](frontend-en-animatie.md).
- **Een aparte API.** Komt er een mobiele app of externe integratie, dan pas.
- **Redis.** MySQL doet nu cache, sessies en queue. Bij groei is Redis de
  eerste stap, en dat is een configuratiewijziging, geen verbouwing.
