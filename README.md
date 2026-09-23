# @T IT Advies

De website en het beveiligde gedeelte van @T IT Advies. Eén Laravel-applicatie:
publieke site, authenticatie met tweestapsverificatie, en een beheeromgeving
waarin verzonden mail en beveiligingsgebeurtenissen terug te zien zijn.

## Stack

Laravel 13 · Inertia 3 · Vue 3 + TypeScript · Tailwind CSS 4 · Vite 8 ·
MySQL 8 · Fortify (2FA/TOTP + passkeys) · spatie/laravel-permission ·
Laravel Pulse · GSAP + Lenis · Resend

## Aan de slag

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
# zet DB_USERNAME en DB_PASSWORD in .env

php artisan migrate --seed
valet link
npm run dev
```

De site draait daarna op `http://t-it-advies.test`.

Werk je met Valet Linux onder WSL, dan heeft Windows ook een regel in het
hosts-bestand nodig — `valet link` alleen is niet genoeg, want Windows lost
`.test` niet op. Voeg toe aan `C:\Windows\System32\drivers\etc\hosts`:

```
127.0.0.1	t-it-advies.test
```

Uitleg en het commando daarvoor staan in
[docs/development/setup.md](docs/development/setup.md).

Lokaal beheerdersaccount: `admin@t-it-advies.test` / `password`.

## Commando's

```bash
composer dev            # server, queue, logs en Vite tegelijk
php artisan test        # tests
composer ci:check       # pint + phpstan + vue-tsc + tests
npm run build           # productiebuild
```

## Documentatie

Alles staat in [`docs/`](docs/README.md). Begin bij de index.

Belangrijk voor iedereen die hier code schrijft, mens of AI:
**documentatie gaat mee met de code, in dezelfde wijziging.** Zie
[docs/development/documentatieregels.md](docs/development/documentatieregels.md)
en [`AGENTS.md`](AGENTS.md).
