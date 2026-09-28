# Authenticatie en tweestapsverificatie

## Fortify

Authenticatie loopt volledig via [Laravel Fortify](https://laravel.com/docs/fortify).
Fortify registreert de routes en controllers; wij leveren alleen de schermen
(Inertia-pagina's) en de instellingen. Zie
[`app/Providers/FortifyServiceProvider.php`](../../app/Providers/FortifyServiceProvider.php).

Ingeschakelde functies (`config/fortify.php`):

- wachtwoord herstellen,
- e-mailverificatie,
- tweestapsverificatie met bevestiging,
- passkeys,
- wachtwoordbevestiging bij gevoelige schermen.

## Registratie staat uit

Deze site heeft één gebruiker: de eigenaar. Een open registratieformulier zou
vreemden een account geven op een applicatie die verder alleen voor hem is,
en elk account dat je niet nodig hebt is een ingang die je wel moet bewaken.

`Features::registration()` staat daarom niet in de lijst hierboven. De route
`/register` bestaat niet, de pagina is weg en de inloglink ernaartoe ook.
[`RegistrationTest`](../../tests/Feature/Auth/RegistrationTest.php) faalt
zodra iemand de feature terugzet -- zo blijft het een bewuste beslissing en
kan hij niet ongemerkt terugkomen bij het bijwerken van de starter kit.

Er staat ook **geen inlogknop op de publieke site**. Zie
[frontend en animatie](../architecture/frontend-en-animatie.md).

## Een account aanmaken

Het account van de eigenaar komt uit `PortalAccountSeeder` en bestaat in
elke omgeving; zie [rollen en rechten](rollen-en-rechten.md). Heb je
daarnaast nog een account nodig:

```bash
php artisan user:create
php artisan user:create --name="De Klant" --email=klant@example.com --role=admin
```

Het commando vraagt om de naam, het e-mailadres en twee keer het wachtwoord,
en controleert alles met dezelfde regels als de rest van de applicatie.

**Het wachtwoord kan bewust geen optie zijn.** Opties belanden in de
shell-geschiedenis en zijn op een gedeelde server zichtbaar in `ps`. Vragen
is de enige manier waarop het nergens blijft staan. Het komt ook niet in het
beveiligingslogboek; er is een test die dat afdwingt.

Het account wordt meteen als geverifieerd aangemerkt. Zonder geverifieerd
e-mailadres komt de eigenaar niet in het beheergedeelte, en op het moment dat
je dit commando draait staat de mailprovider vaak nog niet ingesteld.

Zet er daarna direct tweestapsverificatie op.

## Tweestapsverificatie is verplicht

Fortify levert de machinerie; wij leggen de plicht op. Er is geen knop "zet
2FA aan" die je kunt overslaan: bij je eerste bezoek word je naar de
instelpagina gestuurd en kom je daar pas vanaf met een werkende code.

Het scharnier is `'confirm' => true` in `config/fortify.php`. Zonder die
vlag is 2FA "aan" zodra er een geheim bestaat; mét de vlag moet je eerst één
code intypen en pas dan wordt `two_factor_confirmed_at` gevuld. Op dat ene
veld stuurt alles -- het is het verschil tussen _begonnen_ en _werkend_.

### De schakelaar

```php
'required' => (bool) env('PORTAL_TWO_FACTOR_REQUIRED', env('APP_ENV') === 'production'),
```

In productie staat het dus vanzelf aan, ook als niemand eraan denkt. Lokaal
kun je het uitzetten, bijvoorbeeld om een testbrowser erlangs te krijgen.

In `phpunit.xml` staat hij expliciet op `false`, zodat de suite niet afhangt
van wat er in de `.env` van een ontwikkelaar staat. Een test die de
verplichting nodig heeft, zet hem zelf aan met `config()`.

### De volgorde van de poortjes

[`EnsureTwoFactorIsConfigured`](../../app/Http/Middleware/EnsureTwoFactorIsConfigured.php),
als alias `two-factor.required`, staat op de routegroepen van het portaal --
**niet** globaal, want dan zou hij ook over het inlogscherm en de
foutpagina's lopen.

1. **Eis staat uit?** Doorlaten.
2. **Niemand ingelogd, of e-mail nog niet geverifieerd?** Doorlaten. Anders
   vechten twee verplichtingen om dezelfde omleiding en beland je in een lus.
3. **`two_factor_confirmed_at` gevuld?** Doorlaten. Klaar.
4. **Vraag je een route op die je nodig hébt om het in te stellen?**
   Doorlaten: de instelpagina, wachtwoordbevestiging, de Fortify-routes voor
   2FA en e-mailverificatie, en uitloggen. Zonder die uitzondering sluit de
   middleware je buiten van de pagina waar hij je naartoe stuurt.
5. **Anders:** `redirect()->guest(route('security.two-factor.setup'))`.

Dat `guest()` in plaats van een gewone redirect doet er één ding bij: het
onthoudt waar je heen wilde, zodat je ná het instellen op je oorspronkelijke
bestemming landt in plaats van op het dashboard.

### De eerste keer

```
inloggen (e-mail + wachtwoord)
        |
e-mail nog niet geverifieerd?  ->  verificatiepagina
        |
je vraagt /dashboard op
        |
two_factor_confirmed_at is leeg
        |
redirect()->guest(security.two-factor.setup)   <- /dashboard wordt onthouden
        |
QR-code scannen + een code intypen
        |
two_factor_confirmed_at gevuld
        |
recovery codes verschijnen
        |
"doorgaan" -> laadscherm -> de pagina die je wilde + begroeting
```

### Geen deur die je niet mag openen

Op de publieke site staat geen inlogknop, en de link naar het portaal
verschijnt alleen voor wie al is ingelogd. Er is één gebruiker en die kent
zijn eigen adres; een knop naar het beheergedeelte wijst bezoekers alleen
maar op een deur die niet voor hen is.

Dat zit op drie plekken vast, en het is belangrijk om te weten welke plek
wat doet:

1. **De weergave.** `SiteHeader.vue` heeft één waarde, `magPortaalZien`, die
   allebei de plekken aanstuurt waar die link staat (de kop op desktop en
   het uitklapmenu op mobiel). Eén benoemde waarde, zodat er maar één regel
   is om te vergeten zodra er een derde plek bij komt.
2. **De grendel.** `/dashboard` staat achter `auth`, `verified` en
   `two-factor.required`. Wie het adres raadt of intypt komt er evengoed
   niet in. Dít is de beveiliging; punt 1 is alleen netheid.
3. **De terugknop.** Bij het uitloggen roept de `LogoutResponse` in
   `FortifyServiceProvider` `Inertia::clearHistory()` aan. Zonder dat blijven
   de pagina's die je als ingelogde gebruiker bekeek in de geschiedenis van
   de browser staan, met hun props erbij, en zet één klik op terug de oude
   kop weer neer -- inclusief de portaallink. Er komt geen nieuwe informatie
   vrij, maar op een gedeelde computer is het precies wat een bezoeker niet
   hoort te zien.

[`PublicSiteTest`](../../tests/Feature/PublicSiteTest.php) bewaakt punt 1 en
3: dat een bezoeker geen gebruiker in de gedeelde props krijgt, dat de
eigenaar dat wél krijgt, en dat uitloggen de geschiedenis wist.

### De begroeting

Het inloggen zet een vlag in de sessie (`portal.welcome`), in de
`LoginResponse` in
[`FortifyServiceProvider`](../../app/Providers/FortifyServiceProvider.php).
Die wordt opgehaald in [`HandleInertiaRequests`](../../app/Http/Middleware/HandleInertiaRequests.php)
en als gedeelde prop `welcome` meegegeven, waarna
[`AppSidebarLayout`](../../resources/js/layouts/app/AppSidebarLayout.vue)
het venster toont.

Dat het in de gedeelde props zit en niet in de controller van het dashboard
is geen detail: na het inloggen land je op de pagina die je open had staan,
en dat is lang niet altijd het dashboard. Stond de begroeting daar, dan
kreeg je hem pas te zien wanneer je een keer langs het dashboard liep --
soms uren later.

De vlag wordt met `pull` opgehaald en meteen gewist, dus je wordt één keer
begroet en niet bij elke verversing. Twee plekken laten hem met rust:

- **Het laadscherm zelf.** Dat scherm staat tussen het inloggen en het
  portaal; zou het de vlag opnemen, dan was hij op voordat er iets te zien
  was.

    Datzelfde scherm staat óók tussen de website en het portaal, en dat is de
    reden dat de vlag bij het inloggen wordt gezet en niet daar. Stond hij op
    het scherm, dan kreeg je "Welkom terug" telkens als je even op je eigen
    site had gekeken.

- **Alles buiten het portaal**, herkenbaar aan de middleware
  `two-factor.required`. Kom je eerst langs het instellen van 2FA of een
  wachtwoordbevestiging, dan blijft de vlag staan tot je echt binnen bent.

### De instelpagina heeft twee stappen

Fortify vraagt om je wachtwoord voordat 2FA aangezet mag worden
(`confirmPassword => true`). Daarvoor onderschept hij het verzoek -- en
voert het daarna **niet** opnieuw uit. Je komt dus gewoon terug op de
instelpagina.

Zonder maatregel ziet die er dan precies hetzelfde uit als ervoor, inclusief
dezelfde knop en de link terug naar het inlogscherm, en lijkt het alsof je
niets hebt gedaan. De controller geeft daarom `passwordConfirmed` mee, en de
pagina toont dan stap twee: bevestiging dat het wachtwoord klopte, een
andere kop, een knop "Verder met instellen", en géén weg terug meer -- die
hoort bij het begin en niet halverwege.

Die vlag gebruikt dezelfde grens als Fortify zelf (`auth.password_timeout`),
zodat we niet iets anders "bevestigd" noemen dan de middleware even
verderop.

Stond er al een geheim van een eerdere poging, dan gaat het venster meteen
open in plaats van dat je op een knop moet drukken die werk overdoet.

### Waarom er geen doorverwijzing is na het bevestigen

De instelpagina staat bewust **buiten** `two-factor.required`, en de
controller stuurt je na het bevestigen **niet** meteen door. Dat laatste is
geen slordigheid: Fortify keert na het intypen van de code terug naar deze
pagina, en dat is het enige moment waarop de recovery codes getoond worden.
Wie daar doorverwijst, laat de eigenaar zonder weg terug achter als hij zijn
telefoon kwijtraakt.

### Elke volgende keer

Fortify ziet `two_factor_confirmed_at`, geeft geen sessie maar de challenge,
en pas na een geldige code kom je binnen. De middleware valt dan al bij het
derde poortje door en doet niets meer. Het afdwingen bij het inloggen is
Fortify's werk; wij zorgen alleen dat je er niet omheen kunt zonder het ooit
ingesteld te hebben.

### Halverwege gestopt

Open je de instelpagina en sluit je de browser vóór het intypen van de code,
dan staat er wel een `two_factor_secret` maar geen `two_factor_confirmed_at`.
Je bent dus **niet** beveiligd, en precies daarom stuurt de middleware op dat
tweede veld. Bij je volgende bezoek kom je opnieuw op de instelpagina, met
hetzelfde geheim, dus je QR-code blijft geldig.

### Het laadscherm bij binnenkomst

Na het inloggen ga je niet rechtstreeks naar het dashboard maar langs
`/portaal/binnenkomen`
([`PortalEntryController`](../../app/Http/Controllers/Security/PortalEntryController.php)).
Dat duurt ongeveer een seconde en is geen vertraging om de show: het portaal
haalt op dat moment zijn eigen bundel op, en zonder tussenscherm kijk je naar
een halve pagina die zich nog aan het opbouwen is.

Zowel `LoginResponse` als `TwoFactorLoginResponse` zijn hiervoor vervangen in
`FortifyServiceProvider` -- zonder 2FA loopt het inloggen via de eerste, met
2FA via de tweede.

**De bestemming wordt aan de serverkant getoetst.** Hij komt uit
`url.intended` in de sessie en wordt in de browser opgevolgd: precies de
vorm waarin een open omleiding ontstaat. De controle kijkt eerst naar de
**host** en pas daarna naar het pad, en die volgorde is niet willekeurig.
Bij `//kwaadaardig.example/phishing` haalt `parse_url` de host eruit en houd
je een onschuldig ogend `/phishing` over, terwijl een browser het als een
volledig adres leest. Alleen op het pad toetsen laat die dus door.

Het scherm weigert ook zichzelf als bestemming. Dat is geen theorie: wie
zonder 2FA binnenkomt wordt hiervandaan naar de instelpagina gestuurd, en
dan staat dit adres als onthouden bestemming klaar. Zonder die grens blijf
je rondjes draaien.

De omleiding in de browser gebruikt `replace`, zodat de terugknop je niet op
het laadscherm zet dat je meteen weer vooruit stuurt. Er staat ook een link
"nu doorgaan": werkt JavaScript niet, dan is het scherm geen doodlopende weg.

Alles staat in
[`PortalEntryTest`](../../tests/Feature/Security/PortalEntryTest.php),
inclusief de weigering van een vreemde host.

### Uitzetten kan niet

Staat de verplichting aan, dan verbergt het instellingenscherm de
uitzetknop. Die zou wel werken, maar de middleware stuurt je er direct daarna
weer naartoe -- een knop die je meteen ongedaan moet maken is geen keuze maar
een valkuil. Nieuwe telefoon? Zet hem daar eerst op met een recovery code.

## De schermen

| Scherm                     | Waar                                        |
| -------------------------- | ------------------------------------------- |
| Verplicht instellen        | `auth/TwoFactorSetup.vue`                   |
| Aanzetten (QR + sleutel)   | `TwoFactorSetupModal.vue`, via instellingen |
| Tweede stap bij inloggen   | `auth/TwoFactorChallenge.vue`               |
| Bevestigen van een actie   | `auth/ConfirmTwoFactor.vue`                 |
| Recovery codes             | `TwoFactorRecoveryCodes.vue`                |
| Laadscherm bij binnenkomst | `auth/PortalEntry.vue`                      |

De recovery codes staan op twee plekken en gedragen zich daar anders. Bij
het verplicht instellen staan ze meteen open en is er geen knop om nieuwe
te maken -- je hebt ze net gekregen. In de instellingen beginnen ze
verborgen, want daar zijn ze een naslagwerk. Op beide plekken zit een knop
om alle codes in één keer te kopiëren.

Alle drie de plekken waar je een code invult gebruiken dezelfde
zes-vakjes-invoer (`InputOTP`): plakken vult alle vakjes in één keer, en
**zodra het zesde cijfer staat wordt het formulier automatisch verstuurd**.
Dat laatste gaat via een verborgen submitknop. De zichtbare knop blijft
staan voor wie met het toetsenbord werkt.

Er staat ook een knopje **Code plakken**
([`PasteCodeButton.vue`](../../resources/js/components/PasteCodeButton.vue))
dat de code uit het klembord haalt, de cijfers eruit vist -- zodat "123 456"
ook werkt -- en meteen verstuurt.

**Dat knopje zie je lokaal niet, en dat is geen fout.** Het klembord _lezen_
mag alleen in een secure context: https, of `localhost`. Op
`http://t-it-advies.test` bestaat `navigator.clipboard` domweg niet, dus
daar staat in plaats daarvan de tip dat Ctrl+V alle zes de vakjes in één
keer vult -- wat óók meteen verstuurt, want de invoer meldt zichzelf als
compleet. Op productie (https) verschijnt de knop vanzelf.

Wil je hem lokaal ook, dan kun je de site met `valet secure t-it-advies`
op https zetten. Dat is een wijziging in je Valet-omgeving, geen
projectinstelling.

Zonder die automatische verzending is de laatste handeling van een code
invullen het zoeken van een knop, en dat is bij een handeling die je elke
dag doet er één te veel.

### De QR-code moet scanbaar blijven

Twee eigenschappen bepalen of een scanner de code leest, en allebei gingen
ze hier ooit mis. Het beeld waaraan je het herkent: **met de camera-app van
je telefoon lukt het wel, met de scanner in de authenticator-app niet.** Die
eerste is slim en vergevingsgezind, de tweede niet.

**De stille zone.** Fortify genereert de SVG met marge 0, dus de code raakt
de rand. De QR-standaard schrijft rondom vier modules witruimte voor.
`User::twoFactorQrCodeSvg()` overschrijft daarom de versie uit Fortify; in
de uitvoer zie je het verschil aan `translate(4,4)` in plaats van
`translate(0,0)`.

**De kleurrichting.** Donkere modules op licht, nooit omgekeerd. De starter
kit legde in het donkere thema een `filter: invert(1) brightness(1.5)` over
de code heen zodat hij bij het thema paste -- en maakte hem daarmee voor
strengere scanners onleesbaar. Die staat er niet meer; de code staat nu
altijd op een witte plaat, ook in het donker.

[`TwoFactorQrCodeTest`](../../tests/Feature/Security/TwoFactorQrCodeTest.php)
bewaakt allebei. Pas je de weergave aan, laat de code dan met rust: geen
filter eroverheen en geen donkere achtergrond eronder.

### De herinnering in het portaal

Staat 2FA uit, dan toont het portaal een balk op elke pagina
([`TwoFactorNudge.vue`](../../resources/js/components/TwoFactorNudge.vue)).
Die is bewust **niet** weg te klikken: hij zou één keer worden weggeklikt en
daarna nooit meer terugkomen, terwijl het wachtwoord dan het enige slot
blijft.

Of 2FA aanstaat komt als `auth.twoFactor` via Inertia mee -- een boolean, en
niet de datum uit het model. `two_factor_confirmed_at` is een
implementatiedetail van Fortify en hoort niet als losse datum in de frontend
rond te gaan.

## Tweestapsverificatie

### Hoe het werkt

2FA gebruikt TOTP: een authenticator-app (Google Authenticator, 1Password,
Bitwarden, Aegis) berekent elke dertig seconden een zescijferige code uit een
gedeeld geheim. Bij het inschakelen toont de applicatie een QR-code met dat
geheim; daarna moet de gebruiker één geldige code invoeren om te bevestigen
dat het scannen is gelukt (`'confirm' => true`).

Dat bevestigen is belangrijk. Zonder die stap kun je 2FA aanzetten met een
verkeerd gescande QR-code en jezelf buitensluiten.

### Opslag

Drie kolommen op `users`:

| Kolom                       | Inhoud                                                                                      |
| --------------------------- | ------------------------------------------------------------------------------------------- |
| `two_factor_secret`         | Het TOTP-geheim, **versleuteld**.                                                           |
| `two_factor_recovery_codes` | De recovery codes, **versleuteld** opgeslagen als JSON.                                     |
| `two_factor_confirmed_at`   | Wanneer de gebruiker een geldige code heeft ingevoerd. Is dit leeg, dan is 2FA niet actief. |

De versleuteling doet Fortify zelf. Gebruik altijd
`Fortify::currentEncrypter()` om te ontsleutelen, niet de `Crypt`-facade
rechtstreeks: Fortify kan zijn encrypter vervangen (bijvoorbeeld tijdens
sleutelrotatie) en dan lopen de twee uiteen.

Alle drie de kolommen staan in de `#[Hidden]`-lijst van het User-model, zodat
ze nooit per ongeluk in een JSON-respons of Inertia-prop belanden.

### Bij het inloggen

Na een geldig wachtwoord stuurt Fortify een gebruiker met bevestigde 2FA naar
de challenge. Daar mag **wel** een recovery code worden gebruikt: dat is
precies waar recovery codes voor zijn, namelijk weer binnenkomen als je je
authenticator kwijt bent.

Wordt een recovery code gebruikt, dan wordt hij verbruikt en vervangen door
een nieuwe. Dat gebeurt automatisch, en het wordt gelogd.

### Bij gevoelige acties

Daar gelden andere regels. Zie [gevoelige acties](gevoelige-acties.md).

## Rate limiting

| Limiter            | Grens                    | Sleutel                      |
| ------------------ | ------------------------ | ---------------------------- |
| `login`            | 5 per minuut             | e-mailadres + IP             |
| `two-factor`       | 5 per minuut             | de sessie van de inlogpoging |
| `sensitive-action` | 5 per minuut             | gebruiker-ID                 |
| `contact`          | 3 per minuut, 20 per dag | IP                           |
| `webhook`          | 120 per minuut           | IP                           |

`login` en `two-factor` staan in `FortifyServiceProvider` omdat Fortify die
routes registreert. De rest staat in `AppServiceProvider`.

Let op het verschil in sleutel: gevoelige acties worden begrensd per
**gebruiker**, niet per IP. Een aanvaller met meerdere IP-adressen schiet daar
niets mee op.

## Passkeys

Passkeys staan aan. Ze zijn een alternatief voor wachtwoord + 2FA en zijn
bestand tegen phishing, omdat de sleutel aan het domein vastzit. De inrichting
komt volledig uit Fortify; wij hebben er niets aan toegevoegd.

Voor een gevoelige actie vragen we bewust een TOTP-code en geen passkey, zodat
er één duidelijke, testbare route is. Wil je passkeys ook daar toestaan, pas
dan `RequireTwoFactorConfirmation` en `ConfirmTwoFactorController` aan, breid
de tests uit en werk [gevoelige acties](gevoelige-acties.md) bij.

## Wachtwoordeisen

In productie: minimaal twaalf tekens, hoofd- en kleine letters, cijfers,
symbolen, en niet voorkomend in bekende datalekken (`uncompromised()`).
Lokaal gelden geen eisen, zodat testen niet onnodig omslachtig wordt. Zie
`AppServiceProvider::configureDefaults()`.

## Wat wordt er gelogd

Alles, behalve de geheimen zelf. Zie [logging](logging.md).
