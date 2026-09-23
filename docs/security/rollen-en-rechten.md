# Rollen en rechten

Via [spatie/laravel-permission](https://spatie.be/docs/laravel-permission).
Het `User`-model gebruikt de trait `HasRoles`.

## Rechten

| Recht               | Geeft toegang tot             |
| ------------------- | ----------------------------- |
| `view mail log`     | `/admin/mail`                 |
| `view security log` | `/admin` en `/admin/security` |
| `view pulse`        | `/pulse`                      |
| `manage users`      | `/admin/users`                |

## Rollen

| Rol         | Rechten                                                                    |
| ----------- | -------------------------------------------------------------------------- |
| `admin`     | Alle bovenstaande rechten.                                                 |
| `developer` | `view mail log`, `view security log`, `view pulse`. Geen gebruikersbeheer. |

Vastgelegd in
[`RolesAndPermissionsSeeder`](../../database/seeders/RolesAndPermissionsSeeder.php).
Die seeder is idempotent: opnieuw draaien voegt alleen toe wat ontbreekt. Je
kunt hem daarom veilig bij een deploy draaien als je een recht toevoegt.

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

## Hoe het wordt gecontroleerd

Per route, niet per groep:

```php
Route::get('mail', [MailLogController::class, 'index'])
    ->middleware('can:view mail log')
    ->name('mail.index');
```

Zo kun je iemand toegang geven tot alleen het mailoverzicht zonder het
beveiligingslogboek open te zetten.

Pulse kent spatie/laravel-permission niet en gebruikt een eigen gate. Die is
gedefinieerd in `AppServiceProvider::configureAuthorization()`:

```php
Gate::define('viewPulse', fn (User $user) => $user->can('view pulse'));
```

## In de frontend

De rechten van de ingelogde gebruiker worden gedeeld via Inertia
(`auth.permissions`) en bepalen welke menu-items zichtbaar zijn. Zie
`AppSidebar.vue`.

**Dat is uitsluitend cosmetisch.** Wie de URL raadt wordt alsnog door de
middleware tegengehouden. Vertrouw nooit op het verbergen van een knop als
enige bescherming; de controle op de server is de echte.

## Een recht toevoegen

1. Zet het in `RolesAndPermissionsSeeder::PERMISSIONS`.
2. Ken het toe aan de juiste rollen in `::ROLES`.
3. Zet `can:<recht>` op de route.
4. Voeg een test toe in
   [`AdminAccessTest`](../../tests/Feature/Admin/AdminAccessTest.php) die
   bewijst dat iemand zonder dat recht een 403 krijgt.
5. Werk deze tabel bij.

## Gevoelige acties

Een recht zegt wat iemand mág. Het zegt niet dat hij het op dit moment zelf
is. Voor handelingen die je niet wilt terugdraaien combineer je `can:` met de
middleware `2fa.confirm`. Zie [gevoelige acties](gevoelige-acties.md).

## Gebruikersbeheer

Op `/admin/users` deel je rollen uit en verwijder je accounts. Kijken heeft
genoeg aan `manage users`; wijzigen vraagt daarnaast om een verse
authenticator-code.

Twee vangnetten zitten in
[`UserController`](../../app/Http/Controllers/Admin/UserController.php). Ze
zijn geen autorisatie -- de uitvoerder mág het -- maar voorkomen een
onherstelbaar ongeluk:

- **Je eigen account kun je hier niet aanpassen of verwijderen.** Wie zichzelf
  zijn rol afneemt, kan het niet meer terugdraaien.
- **De laatste beheerder blijft staan.** Zonder die grens maakt één verkeerde
  klik de applicatie onbeheerbaar, en er is geen scherm om dat te herstellen;
  dan moet je de seeder of de database in.

Beide leveren een melding op het scherm op, en beide wijzigingen komen in het
[beveiligingslogboek](logging.md).

## Het lokale beheerdersaccount

`DatabaseSeeder` maakt in `local` een account aan:

- e-mail: `admin@t-it-advies.test`
- wachtwoord: `password`
- rol: `admin`

Alleen in `local`. Een vast wachtwoord hoort nergens anders thuis.
