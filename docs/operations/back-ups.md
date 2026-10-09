# Back-ups van de website-inhoud

De klant vult zijn website over de jaren zelf. Zonder deze voorziening is
er geen enkele manier om een stand vast te leggen: wist hij iets per
ongeluk, dan typt hij het opnieuw. De seeders bevatten alleen de beginstand
van 2026, niet wat hij er daarna van gemaakt heeft.

Onder **Beheer → Back-ups** legt hij die stand vast, zet hij er een terug,
en downloadt hij het bestand zodat het een serverramp overleeft.

## Wat dit wel en niet dekt

Dit is **geen vervanging van de back-up van je hostingpakket.** Het legt de
_inhoud van de website_ vast, niet de hele database.

| Ramp                                      | Gedekt door                                                                                             |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Je hebt zelf iets weggegooid of verprutst | **Deze back-up**                                                                                        |
| Je wilt terug naar hoe het vorig jaar was | **Deze back-up**                                                                                        |
| De database is stuk, of de hosting is weg | Een **gedownloade** back-up voor de website; voor het account en de aanvragen de back-up van de hosting |

## De vorm in het kort

|                      |                                                                       |
| -------------------- | --------------------------------------------------------------------- |
| Tabel                | `backups` (zelf niet in een back-up; zie `Inhoudsregister::VERBODEN`) |
| Bestanden            | `storage/app/private/backups` -- buiten de webroot                    |
| Register             | `App\Support\Backup\Inhoudsregister`                                  |
| Maken                | `BackupMaker`                                                         |
| Lezen en controleren | `BackupLezer`                                                         |
| Terugzetten          | `BackupTerugzetter`                                                   |
| Opruimen             | `Opruimer`                                                            |
| Scherm               | `admin/Backups.vue` + `admin/BackupCodeDialoog.vue`                   |
| Tests                | `tests/Feature/Admin/Backup*Test.php` (46 stuks)                      |

## Geen gegenereerde PHP

Het idee begon als "schrijf een nieuwe seeder weg". Dat gedrag klopt, de
vorm niet. Een back-up die een **PHP-bestand** in `database/seeders/`
wegschrijft heeft drie problemen die geen van alle op te lossen zijn:

1. **het is code-uitvoering** -- wie ooit in die map kan schrijven, voert
   code uit op de server;
2. **die map staat in git** -- bij de eerstvolgende deploy is het bestand
   weg, en dat is net het moment waarop je hem nodig hebt;
3. **draaien vereist de opdrachtregel** -- `db:seed --class=` vanuit een
   webverzoek is traag en op gedeelde hosting onbetrouwbaar.

Het is daarom een **zip met data**: `manifest.json`, `data.json` en de
beelden. Voor de klant gedraagt het zich zoals bedoeld; er wordt nooit code
weggeschreven of uitgevoerd.

## Wat erin gaat

Alles uit `Inhoudsregister::tabellen()`: de indeling, de koppen, de
loopbaan, de diensten, de certificaten, de opleidingen, de statistieken, de
kerngegevens, de vragen, Over mij, de projecten, de werkwijze en het
contactformulier. Plus
de bestanden achter `logo_path`, `photo_path` en `image_path`.

### En wat er nooit in gaat

| Wat                                                               | Waarom niet                                                                                                                                                                                                                                                                  |
| ----------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `users`, `passkeys`, 2FA, `email_changes`                         | **Het account.** Zou een terugzetting daaraan komen, dan logt de eigenaar zichzelf uit met een oud wachtwoord of een verdwenen passkey. Eén account; dat is onherstelbaar                                                                                                    |
| `contact_submissions`                                             | **Berichten van bezoekers.** Die hebben een bewaartermijn van een jaar en kunnen op verzoek gewist zijn. Een kopie zonder klok maken, en bij terugzetten gewiste berichten tot leven wekken, mag niet. Zie [verzoeken van bezoekers](../security/verzoeken-van-bezoekers.md) |
| `security_events`, `activity_entries`, `mail_logs`, bezoekcijfers | Dat is wat er gebeurd is. Dat zet je niet terug; dan herschrijf je de geschiedenis                                                                                                                                                                                           |
| `roles`, `permissions` en hun koppeltabellen                      | Komen uit `RolesAndPermissionsSeeder` en horen bij de code. Zouden ze meegaan, dan kan een oude back-up het recht terugzetten zoals het toen was -- en zo werk je jezelf uit je eigen beheerscherm                                                                           |

Twee tests bewaken dit, en ze vullen elkaar aan:

- **`test_no_secrets_and_no_visitor_data_end_up_in_the_file`** leest de
  zip als platte tekst en zoekt op die kolomnamen. Met opzet grof, zodat
  hij blijft gelden als er later een tabel bij komt die iemand per ongeluk
  in het register zet.
- **`test_every_table_in_the_database_is_classified`** loopt álle tabellen
  in de database langs en eist dat elke tabel óf in het register staat óf
  op de verboden lijst. Komt er een module bij, dan valt deze test om tot
  er een keuze is gemaakt. **Dit is de test die voorkomt dat er ooit iets
  wordt vergeten** -- een vergeten tabel merk je anders pas als de
  eigenaar iets terugzet en een deel van zijn website weg is.

## Voldoet dit aan de norm?

De gangbare regel is **3-2-1-1-0**: drie kopieën, twee soorten media, één
offsite, één onveranderbaar, en nul fouten -- aantoonbaar via een
teruglees-test.

| Regel                | Hoe                                                                                                                          |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| **3** kopieën        | Live, de back-up op de server, en het gedownloade bestand                                                                    |
| **2** media          | De download ís het tweede medium                                                                                             |
| **1** offsite        | Alleen als de eigenaar downloadt. Zolang de nieuwste back-up `gedownload_op` leeg heeft, staat er een melding boven de lijst |
| **1** onveranderbaar | **Vastzetten**: telt niet mee in het maximum en wordt nooit automatisch opgeruimd. Verwijderen vraagt een authenticator-code |
| **0** fouten         | Elke back-up wordt **meteen na het maken** teruggelezen en geverifieerd, en er is een knop voor een **proefterugzetting**    |

### Wat we niet halen, en dat hoort hier te staan

- **Echte onveranderbaarheid bestaat niet op gedeelde hosting.** Geen
  WORM-opslag, geen air gap. Wie het portaal binnenkomt kan back-ups
  verwijderen; vastzetten en de code maken dat lastiger, niet onmogelijk.
- **Offsite is handwerk.** We kunnen de download niet afdwingen, alleen
  onmogelijk maken te vergeten.

## De nul: verifiëren

Een back-up die nooit is teruggelezen is een aanname. Twee momenten:

**Meteen na het maken.** `BackupMaker::maak()` roept
`BackupLezer::controleer()` aan: de zip opnieuw openen, het manifest lezen,
de SHA-256 van `data.json` en van elk beeld narekenen, en de aantallen per
tabel tellen. Lukt dat niet, dan wordt het bestand **weggegooid** en de rij
ook. Een stil kapot bestand in de lijst is erger dan geen bestand, want
daar reken je op.

**Op verzoek: _Controleren_.** Dezelfde controle plus
`metProef: true`: de back-up wordt echt teruggezet binnen een transactie
die daarna wordt teruggedraaid.

> Dat is de teruglees-test die de norm vraagt, en hij kan hier omdat alles
> in één transactie past. `BackupTerugzetter` heeft daarvoor één vlag
> `$proef` -- dezelfde code, één pad. Zou de proef een eigen route hebben,
> dan toets je iets anders dan je straks uitvoert.
>
> `BackupMakenTest::test_the_trial_restore_changes_nothing` legt vast dat
> de database er na een proef onveranderd bij ligt.

## Bijwerken, niet wissen

De voor de hand liggende aanpak -- elke tabel leegmaken en opnieuw vullen
-- heeft één echt probleem. `contact_submissions.subject_id` wijst met
`nullOnDelete` naar `contact_subjects`. Alle onderwerpen weggooien zet dus
bij **elke bestaande aanvraag** het onderwerp op `null`, ook bij aanvragen
die blijven staan. De eigenaar zet zijn projecten terug en is ongemerkt het
onderwerp van al zijn aanvragen kwijt.

Daarom vergelijkt het terugzetten per tabel, met behoud van de `id`:

- rij in allebei → bijwerken;
- rij alleen in de back-up → invoegen, met dezelfde `id`;
- rij alleen in de database → verwijderen.

Alles in één transactie, met `Model::withoutEvents()` eromheen -- anders
schrijft `LogsActivity` honderden regels in het activiteitenlogboek voor
wat de eigenaar als één handeling deed.

**De bestanden gaan ná de transactie.** Die kennen geen rollback; zou het
ertussen zitten, dan had een mislukte terugzetting de beelden al
overschreven.

## Beelden: elk beeld één keer

Beelden staan in één gedeelde map onder hun eigen inhoud-hash:

```
storage/app/private/backups/
    media/<sha256>.webp        ← de pool, gedeeld door alle back-ups
    2026-10-08-1423.zip        ← data, met verwijzingen
```

Een beeld dat niet verandert staat er **één keer**, hoeveel back-ups er ook
naar wijzen. Dat is wat de groei opvangt.

Bij het opruimen worden alleen de poolbestanden verwijderd waar geen enkele
overgebleven back-up meer naar wijst. `Opruimer::ruimBeeldenOp()` leest
daarvoor de manifesten van álle overgebleven back-ups; bij hoogstens zeven
bestanden is dat goedkoop en veel betrouwbaarder dan een teller die ooit
uit de pas loopt.

> **Kan dit niet uit de hand lopen?** Uploads worden bijgesneden (logo 256
> pixels, foto en projectbeeld 640) en als WebP bewaard: 8 tot 36 KB per
> stuk. Een volle site met 180 beelden is 3 tot 7 MB -- in totaal, niet per
> back-up. De gedownloade zip bevat de beelden wél gewoon; anders is het
> geen back-up maar een halve.

## De sloten op het terugzetten

Vier drempels, oplopend:

1. `can:manage portal` op de route;
2. **een verse authenticator-code, in het verzoek zelf**;
3. **vier cijfers overtypen** die bij deze back-up horen;
4. een automatische veiligheidskopie, vlak ervoor.

Die vierde is de belangrijkste: hij werkt ook als de andere drie zijn
genegeerd. Mislukt het maken van die kopie, dan gaat de terugzetting
**niet** door.

> **Waarom niet `2fa.confirm`?** Die middleware kan een POST niet
> onthouden: ze stuurt je naar het codescherm en gooit je verzoek weg. De
> code zit daarom in het verzoek, gecontroleerd door
> `App\Support\Security\Authenticator` -- dezelfde klasse die dat
> codescherm gebruikt. Zie
> [gevoelige acties](../security/gevoelige-acties.md#de-middleware-werkt-niet-overal).

### Waarom vier cijfers en niet de naam

Eerst moest de volledige naam worden overgetypt: `2026-10-08-1130`.
Vijftien tekens met streepjes erin is een drempel, maar een onhandige --
en vervelend is niet hetzelfde als zorgvuldig. Wie geïrriteerd raakt, let
minder op.

Elke back-up krijgt daarom bij het aanmaken vier cijfers
(`backups.bevestigcode`), uniek onder de bestaande back-ups. Ze staan
groot in het venster; je typt ze over in het veld ernaast.

**Cijfers en geen woord**, want een woord zou Nederlands of Engels zijn en
dan klopt het in één van de twee talen niet. **Uniek**, zodat je nooit de
ene back-up kunt bevestigen met de code van de andere -- dat is geen
beveiliging maar het voorkomt dat je het verkeerde bestand terugzet omdat
je het verkeerde venster openhad.

**De cijfers worden vóór de authenticator-code getoetst.** Een TOTP-code
werkt één keer; een typefout in het overtypen mag er geen opbranden.

## Als het schema is verschoven

Een back-up van jaren terug mag gewoon terug. In het manifest staat de
laatst gedraaide migratie. Bij het terugzetten:

- een veld in de back-up dat niet meer bestaat → overslaan, en melden;
- een veld dat erbij is gekomen → standaardwaarde, en melden;
- een onderdeel dat niet meer bestaat → overslaan, en melden;
- een onderdeel dat er toen nog niet was → ongemoeid laten, en melden.

Het venster waarschuwt vooraf als het schema of de site-vingerafdruk
afwijkt.

> **Let op na een terugzetting gevolgd door een deploy.**
> `ExperienceSeeder` voegt ontbrekende rijen per stuk toe. Heeft de
> eigenaar een geseede ervaring verwijderd en zet hij die stand terug, dan
> zet de eerstvolgende `db:seed` hem er weer bij. Dat gedrag bestaat al en
> staat los van deze voorziening.

## Uploaden

Een geüploade zip wordt niet vertrouwd, ook niet als de eigenaar hem zelf
heeft gedownload:

- alleen `.zip`, hoogstens 64 MB (`BackupUploadRequest::MAX_KB`);
- `manifest.json` met een formaatversie die we kennen;
- de checksum moet kloppen;
- **elk onderdeel moet in ons register staan**; een onbekende tabel is geen
  reden om iets over te slaan maar om het hele bestand te weigeren;
- beelden alleen als de naam `[a-f0-9]{64}` is, de inhoud naar die naam
  hasht, én `getimagesizefromstring` zegt dat het een afbeelding is;
- **er wordt nooit een pad uit de zip gevolgd** -- de bestandsnaam wordt
  opnieuw opgebouwd uit de hash, dus zip-slip bestaat hier niet.

De upload komt als gewone back-up in de lijst. Terugzetten is daarna
dezelfde knop met dezelfde sloten: er is geen tweede, kortere weg naar het
overschrijven van de database.

## Het draaiboek

### "Ik heb iets verprutst"

1. Beheer → Back-ups.
2. Kies de laatste back-up van vóór de vergissing en druk op
   **Controleren**. Dat kost een paar seconden en verandert niets.
3. Druk op **Terugzetten**, lees wat er verandert, typ de naam over en vul
   je code in.
4. Bevalt het resultaat niet, dan staat de stand van vlak ervoor als
   automatische kopie in de lijst.

### "De hele site is weg"

1. Zet de applicatie opnieuw neer volgens [deployment](deployment.md),
   inclusief `php artisan migrate` en `db:seed`.
2. Log in met het account dat `PortalAccountSeeder` aanmaakt en stel 2FA
   in.
3. Beheer → Back-ups → **Bestand terugplaatsen**, kies het gedownloade
   zip-bestand.
4. Terugzetten.

Stap 2 is de reden dat het account niet in de back-up zit: zonder een
werkend account kom je nergens, en een account uit een oud bestand zou je
met een oud wachtwoord opzadelen.

## Wat er in het logboek komt

| Soort                  | Wanneer                                   |
| ---------------------- | ----------------------------------------- |
| `backup.gemaakt`       | een back-up gemaakt of geüpload           |
| `backup.gecontroleerd` | de teruglees-test, geslaagd of niet       |
| `backup.teruggezet`    | mét de naam van de veiligheidskopie erbij |
| `backup.verwijderd`    |                                           |

## Ruimte en onderhoud

Er is geen opruimtaak in `routes/console.php`. Zie _De grenzen_
hieronder voor wat er blijft staan; het scherm toont het totaal aan
ruimte.

Voor de server:

- **`ext-zip` is vereist.** Hij staat in `composer.json`, zodat een
  hostingpakket zonder zip bij `composer install` faalt in plaats van bij
  het eerste gebruik.
- **`storage/app/private` moet buiten de webroot blijven.** Een back-up die
  je met een geraden adres kunt downloaden is geen back-up maar een lek.

## De grenzen

| Soort                                      | Maximum | Wat er gebeurt als het vol is                                       |
| ------------------------------------------ | ------- | ------------------------------------------------------------------- |
| Zelf gemaakt + geüpload (`MAXIMUM_EIGEN`)  | 5 samen | **Het portaal vraagt welke er weg mag.** Er verdwijnt niets vanzelf |
| Veiligheidskopieën (`MAXIMUM_AUTOMATISCH`) | 3       | De oudste verdwijnt vanzelf                                         |
| Vastgezet (`MAXIMUM_VAST`)                 | 2       | Telt niet mee in de vijf, verdwijnt nooit vanzelf                   |

> **Dit is een reparatie.** De grens gold eerst **per soort**: vijf
> handmatige én vijf geüploade én twee automatische. Op het scherm stond
> "je bewaart er hoogstens vijf", en in de praktijk konden er zeven staan.
> Een getal op het scherm dat de code niet waarmaakt is erger dan geen
> getal.

### En niets verdwijnt meer vanzelf

Eerst verdween bij de zesde de oudste, zonder vragen. Dat is precies het
soort hulpvaardigheid waar je spijt van krijgt: je maakt even een back-up
voor de zekerheid en raakt daarmee de back-up kwijt die je eigenlijk
wilde bewaren.

Nu opent het scherm een venster met de lijst, de oudste bovenaan en
aangewezen. `BackupController::maakPlaatsIndienNodig()` weigert bovendien
op de server als er geen keuze is meegestuurd -- een grens die alleen in
de browser bestaat is geen grens.

**De veiligheidskopieën zijn de uitzondering.** Die ontstaan midden in een
terugzetting, en daar hoort geen vraag doorheen te komen. Ze ruimen
zichzelf op en tellen niet mee.

### "Automatisch" betekent hier niet "op vaste tijden"

Er draait **geen geplande back-up**. Niets per nacht, niets per week. De
kopieën die `automatisch` heten ontstaan op precies één moment: vlak
vóórdat de eigenaar iets terugzet.

Dat onderscheid is belangrijk genoeg om op het scherm te staan, want
"automatisch" laat zich makkelijk lezen als "er wordt voor me gezorgd" --
en dan maakt hij zelf geen back-up meer. Op `admin/Backups.vue` staan ze
daarom in een **eigen blok onder** de eigen back-ups, met die zin erbij.

> Ze stonden eerst door elkaar in één lijst. Dan telt de eigenaar zeven
> regels terwijl er vijf mogen staan: het getal klopte, de lijst vertelde
> iets anders. Dat was de aanleiding voor de splitsing.

Wil je ooit wél een geplande back-up, dan is dat één regel in
`routes/console.php` plus een commando. Zie _Bewust niet gedaan_ voor
waarom dat er nu niet staat.

### De datum waarop gesorteerd wordt

`backups.vastgelegd_op` -- het moment waarop de **inhoud** is vastgelegd,
en níet wanneer de rij is aangemaakt.

Die twee lopen uiteen bij een teruggeplaatst bestand: de rij is van
vandaag, de inhoud van januari. Stond de volgorde op `created_at`, dan
kreeg het oudste bestand het merkje "Nieuwste" en stond het bovenaan --
terwijl de naam januari zei.

Voor een verse back-up is het gewoon "nu", en datzelfde moment gaat als
`gemaakt` het manifest in. Bij een upload wordt het daar weer uit gelezen.
Zo houdt een back-up zijn datum, waar hij ook heen gaat.

Op het scherm staat die datum **voluit** en niet alleen als "twee dagen
geleden": als je moet kiezen welke back-up je kwijt wilt, is een vage
aanduiding niet genoeg.

### Op het scherm

De nieuwste eigen back-up krijgt het merkje **Nieuwste**, en de oudste
**Oudste** -- altijd, zodra er twee of meer staan.

> Dat laatste was eerst alleen zo bij een volle lijst. Dan staat er bij
> een duidelijk oude back-up geen merkje, en dat leest als een fout, ook
> al klopt de regel erachter.

Staan er te veel, dan krijgen de **oudste zoveel als er weg moeten** dat
merkje -- moeten er twee weg, dan zie je welke twee.

Vastgezette back-ups en veiligheidskopieën krijgen het nooit: die komen
niet in aanmerking om weg te gaan, en een label "oudste" zou de eigenaar
de verkeerde kant op sturen.

### Als er toch te veel staan

Dat hoort niet te kunnen en het kan toch: zet er twee vast terwijl je er
vijf hebt -- vastgezette tellen niet mee -- en laat ze daarna los, dan
staan er zeven die meetellen. Een installatie van vóór deze regels kan er
ook zo bij staan.

Dan opent het opruimvenster **meteen bij het openen van de pagina**, en
blijft er een melding boven de lijst staan tot het is rechtgezet. Stil
laten staan zou betekenen dat de eigenaar een grens leest die niet geldt.

`Backup::teveel()` rekent het uit, `BackupController::opruimen()` handelt
het af. Die vraagt om evenveel keuzes als er weg moeten, en om een
authenticator-code.

### Elke verwijdering vraagt de code

Ook het weggooien om plaats te maken voor een nieuwe. Zonder dat is "een
nieuwe back-up maken" een sluiproute om de beveiliging op verwijderen te
omzeilen.

## Bewust niet gedaan

- **Geen `mysqldump`.** Bestaat op gedeelde hosting vaak niet, en zou het
  account en de logboeken meenemen -- precies wat hier niet mee moet.
- **Geen automatische back-up per nacht.** Kan later in één regel bij de
  geplande taken. Eerst moet de knop vertrouwd zijn; een automatische
  back-up die stilletjes faalt is erger dan geen.
- **Geen gedeeltelijk terugzetten.** Verdubbelt het aantal toestanden
  waarin de database kan belanden.
- **Geen versleuteling.** De norm vraagt erom, en toch niet: er staan geen
  persoonsgegevens en geen geheimen in -- daar is een test voor -- het
  bestand ligt buiten de webroot en de download gaat over HTTPS. Een
  wachtwoord op de zip dat de eigenaar kwijtraakt maakt zijn back-up
  waardeloos, en dat risico is groter dan wat het afdekt.
- **Geen back-up per mail.** Dat zou offsite automatisch maken, maar een
  bijlage van tien tot twintig megabyte loopt bij veel mailservers vast --
  en dan faalt stil juist de kopie waarop je rekent. Wil je echt
  automatisch offsite, dan is een opslagdienst de juiste weg.
- **Geen back-up van de code.** Die staat in git.

## Zie ook

- [Gevoelige acties](../security/gevoelige-acties.md)
- [Verzoeken van bezoekers](../security/verzoeken-van-bezoekers.md)
- [Deployment](deployment.md)
