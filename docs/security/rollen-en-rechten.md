# Rollen en rechten

Via [spatie/laravel-permission](https://spatie.be/docs/laravel-permission).
Het `User`-model gebruikt de trait `HasRoles`.

## Eén recht, één rol

| Recht           | Geeft toegang tot                                                                                                                                         |
| --------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `manage portal` | Alles achter de inlog: `/website`, het hele beheergedeelte (`/admin`, `/admin/activiteit`, `/admin/mail`, `/admin/security`, `/admin/users`) en `/pulse`. |

| Rol     | Rechten         |
| ------- | --------------- |
| `admin` | `manage portal` |

**En daar blijft het bij.** Dit portaal heeft één gebruiker: de eigenaar van
de website. Wij gebruiken dezelfde rol. Er is dus geen beheerder die wél bij
het ene scherm mag en niet bij het andere, en er is niets te winnen met een
fijnmazig stelsel -- alleen iets te verliezen.

Wat je ermee verliest is namelijk niet theoretisch. Een scherm achter een
nieuw recht verschijnt niet in het menu zolang dat recht nog niet in de
database staat, en dan zoek je een bug in code die het gewoon doet. Dat is
hier één keer gebeurd, met het activiteitenlogboek.

Merk je dat je een tweede recht aan het bedenken bent? Dan is dat het
signaal om te stoppen en het achter `manage portal` te hangen. Komt er ooit
een echte reden voor meerdere rollen -- een tweede medewerker bij de klant
die minder mag -- dan is dat een beslissing van de opdrachtgever en geen
technische keuze.

Vastgelegd in
[`RolesAndPermissionsSeeder`](../../database/seeders/RolesAndPermissionsSeeder.php).
Die seeder is idempotent én **gezaghebbend**: wat er niet in staat wordt uit
de database verwijderd. Haal je een recht of een rol weg, dan is hij na de
volgende seed ook echt weg, inclusief de koppelingen naar gebruikers.

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

## Hoe het wordt gecontroleerd

Op de route, met `can:`:

```php
Route::get('mail', [MailLogController::class, 'index'])
    ->middleware('can:manage portal')
    ->name('mail.index');
```

Het staat per route en niet op de groep, ook al is het overal hetzelfde
recht. Zo zie je bij elke route wat hem beschermt, in plaats van dat je
twintig regels omhoog moet scrollen -- en zo kan er nooit een route
tussenkomen die per ongeluk buiten de groep valt.

Pulse kent spatie/laravel-permission niet en gebruikt een eigen gate. Die is
gedefinieerd in `AppServiceProvider::configureAuthorization()`:

```php
Gate::define('viewPulse', fn (User $user) => $user->can('manage portal'));
```

## In de frontend

De rechten van de ingelogde gebruiker worden gedeeld via Inertia
(`auth.permissions`) en bepalen welke menu-items zichtbaar zijn. Zie
`AppSidebar.vue`.

**Dat is uitsluitend cosmetisch.** Wie de URL raadt wordt alsnog door de
middleware tegengehouden. Vertrouw nooit op het verbergen van een knop als
enige bescherming; de controle op de server is de echte.

## Een nieuw beheerscherm

1. Zet `->middleware('can:manage portal')` op de route.
2. Zet het menu-item in `AppSidebar.vue` binnen het bestaande blok dat
   achter `magBeheren` hangt.
3. Voeg de route toe aan de test
   [`AdminAccessTest`](../../tests/Feature/Admin/AdminAccessTest.php), zowel
   bij "een gast wordt naar het inlogscherm gestuurd" als bij "één recht
   opent alles".

Meer is het niet. Geen nieuw recht, geen seed, geen cache legen -- precies
de bedoeling van één recht.

### En als er toch ooit een recht bij komt

Dan geldt deze val, en hij kost je een kwartier als je hem niet kent. Een
recht dat je aan de seeder toevoegt bestaat nog nergens in de database.
`can('…')` geeft dan `false`, het menu-item verschijnt niet en de route
geeft een 403 -- zonder dat er ergens een fout staat. Het ziet er precies
uit alsof je het vergeten bent te bouwen. Draai dan:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan permission:cache-reset
```

Bij een deploy gebeurt dat vanzelf: `php artisan db:seed --force` staat in
de deploystappen. Dat tweede commando is voor de zekerheid; de seeder leegt
de cache zelf ook, maar een draaiende queue-worker houdt zijn eigen
exemplaar vast.

## Gevoelige acties

Een recht zegt wat iemand mág. Het zegt niet dat hij het op dit moment zelf
is. Voor handelingen die je niet wilt terugdraaien combineer je `can:` met de
middleware `2fa.confirm`. Zie [gevoelige acties](gevoelige-acties.md).

## Gebruikersbeheer

Op `/admin/users` deel je rollen uit en verwijder je accounts. Kijken heeft
genoeg aan `manage portal`; wijzigen vraagt daarnaast om een verse
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

## Een account aanmaken

Registratie via de website staat uit, dus accounts maak je met
`php artisan user:create --role=admin`. Het commando weigert een rol die niet
bestaat, zodat je niet per ongeluk een account zonder rechten aanmaakt en
daar pas bij het inloggen achter komt. Zie
[authenticatie en 2FA](authenticatie-en-2fa.md).

## Het account van de eigenaar

Deze applicatie heeft **één** gebruiker: de eigenaar. Dat account wordt
aangemaakt door
[`PortalAccountSeeder`](../../database/seeders/PortalAccountSeeder.php) en
hoort in elke omgeving te bestaan, ook in productie. Het is dus geen
testdata, en de seeder draait daarom mee bij elke deploy.

```bash
php artisan db:seed --class=PortalAccountSeeder
```

Naam en e-mailadres staan in `config/security.php` -- die zijn geen geheim.
De naam is de volledige naam ("Erik Aarssen"); het portaal groet met alleen
de voornaam, want "Welkom terug, Erik Aarssen" klinkt als een brief van de
gemeente.
**Het wachtwoord staat uitsluitend in `.env`**, onder
`PORTAL_ACCOUNT_PASSWORD`. Een wachtwoord in een bestand dat in git staat is
geen wachtwoord meer; zie regel 2 in [`AGENTS.md`](../../AGENTS.md).
Ontbreekt die waarde terwijl het account nog niet bestaat, dan stopt de
seeder met een duidelijke melding.

Dat account kun je **niet via het portaal verwijderen**. De starter kit
levert daar een scherm voor mee, maar dat is hier weggehaald: er is één
account en registratie staat uit, dus wie dat account weghaalt sluit
zichzelf buiten en er is daarna niemand meer die kan inloggen -- ook wij
niet. De route `profile.destroy` bestaat niet meer, en
[`ProfileUpdateTest`](../../tests/Feature/Settings/ProfileUpdateTest.php)
bewaakt dat hij niet bij een update van de starter kit ongemerkt terugkomt.

Een beheerder kan **andere** accounts wél verwijderen, via het
beheergedeelte. Daar gelden de vangnetten die verderop in dit document
staan: niet jezelf, en niet de laatste beheerder.

Twee dingen die de seeder bewust **niet** doet:

- **Een bestaand wachtwoord bijwerken.** Anders draait elke deploy het
  wachtwoord terug dat toevallig in `.env` van die server staat, ook als de
  eigenaar het zelf heeft gewijzigd -- en dat merk je pas wanneer hij niet
  meer binnenkomt.
- **Een tweede account aanmaken.** Heb je er toch een nodig, bijvoorbeeld
  voor een collega, gebruik dan `php artisan user:create`.

Het e-mailadres wordt in kleine letters opgeslagen. SQLite vergelijkt
hoofdlettergevoelig, dus een adres met een hoofdletter zou in de tests wel
geseed worden maar niet teruggevonden bij het inloggen.

Rol en e-mailverificatie worden wél elke keer geborgd: die kunnen wegvallen
bij een verse database of een gewijzigde rollenlijst, en zonder geverifieerd
adres komt de eigenaar het beheergedeelte niet in.

Het account krijgt bewust **geen** tweestapsverificatie van de seeder mee.
Dat kan ook niet zinnig: het geheim hoort alleen op de telefoon van de
eigenaar te staan, en een geheim dat wij aanmaken en doorgeven is geen
tweede factor meer. Bij de eerste keer inloggen wordt hij verplicht naar de
instelpagina gestuurd; zie
[authenticatie en 2FA](authenticatie-en-2fa.md).
