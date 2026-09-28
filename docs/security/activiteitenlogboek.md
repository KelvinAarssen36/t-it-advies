# Activiteitenlogboek

Wie heeft wat aan de website veranderd, en wat stond er eerst.

Dit is het logboek van de **beheerder**. Het beantwoordt één vraag, en die
is anders dan die van het [beveiligingslogboek](logging.md): niet "wie
probeerde binnen te komen", maar "wie heeft dit aangepast".

## Twee logboeken, en waarom ze gescheiden blijven

| Logboek                                       | De vraag                                        | Publiek             |
| --------------------------------------------- | ----------------------------------------------- | ------------------- |
| [Beveiliging](logging.md) (`security_events`) | Wie probeerde binnen te komen, en lukte dat?    | Wij, en de eigenaar |
| Activiteit (`activity_entries`)               | Wie heeft de inhoud veranderd, en wat stond er? | De eigenaar zelf    |

Ze staan allebei achter hetzelfde recht, `manage portal`. Dit portaal heeft
één gebruiker en één rol; zie [rollen en rechten](rollen-en-rechten.md).

De scheiding zit dus niet in wie erbij mag, maar in **wat er in staat**. Twee
logboeken die je apart kunt doorlezen zijn bruikbaarder dan één lange lijst
waarin een inlogpoging en een tekstwijziging door elkaar staan.

De grens loopt bij het onderwerp, niet bij de ernst:

- **Beveiliging:** inloggen, 2FA, wachtwoorden, rollen, geblokkeerde bots,
  geweigerde webhooks, gevoelige acties.
- **Activiteit:** aanmaken, wijzigen en verwijderen van inhoud.

Iets dat allebei raakt -- een account dat wordt verwijderd, rollen die
veranderen -- staat in het **beveiligingslogboek**. Dat gaat over toegang, en
toegang is geen inhoud.

## Waarom een tabel en geen logbestand

Een veelgebruikte aanpak is per logboek een bestand in `storage/logs/`. Hier
is het een tabel, om drie redenen:

1. **De klant leest het.** Een logbestand is gereedschap voor een
   ontwikkelaar. "Wat heb ik vorige week aan die pagina veranderd" is een
   vraag van de eigenaar, en die hoort hij te kunnen filteren en doorzoeken
   in een scherm.
2. **Er staat al een logboek in de database.** Het beveiligingslogboek werkt
   zo, met een viewer, filters, een opruimtaak en alarmering. Een tweede,
   andersoortig mechanisme voor dezelfde soort vraag zou een tweede standaard
   zijn -- precies waar de rest van dit project op let.
3. **Je kunt er iets mee.** De oude waarde staat als JSON in de rij. Daarmee
   kun je later "zet terug" bouwen. Uit een tekstbestand kan dat niet.

Voor uitzonderingen en stacktraces blijft `storage/logs/laravel.log` wat het
is. Dat is geen audit trail maar foutopsporing, en dat hoort niet in een
scherm voor de klant.

## Hoe een wijziging erin komt

Met de trait
[`LogsActivity`](../../app/Models/Concerns/LogsActivity.php) op het model:

```php
class Dienst extends Model
{
    use LogsActivity;

    public static function activityName(): string
    {
        return __('Dienst');
    }

    public function activityLabel(): string
    {
        return $this->titel;
    }
}
```

Meer is het niet. Vanaf dat moment komt elke `created`, `updated` en
`deleted` in het logboek, ook vanuit een commando, een seeder of een scherm
dat er nu nog niet is.

**Waarom een trait en niet met de hand in de controller.** Loggen dat je
handmatig doet, vergeet je een keer -- en juist dán wil je weten wat er is
gebeurd. Dit project stelt om dezelfde reden tests verplicht bij elke CRUD:
liever een garantie dan discipline.

**Waarom het tóch een keuze blijft.** De trait staat er niet vanzelf op elk
model. Een technische tabel -- een wachtrij, een cache, een maillogboek --
hoort niet in een logboek dat de klant leest. Je zet hem er dus bewust op.

### De drie dingen die een model mag invullen

| Methode            | Waarvoor                                                                  |
| ------------------ | ------------------------------------------------------------------------- |
| `activityName()`   | Hoe de soort heet, in enkelvoud: "Dienst", niet "Service".                |
| `activityLabel()`  | Waaraan je déze rij herkent: een titel, een naam, een onderwerp.          |
| `activityHidden()` | Velden die niets toevoegen, zoals een sorteervolgorde die steeds wijzigt. |

`activityHidden()` is **geen beveiliging.** Gevoelige waarden worden hoe dan
ook geschoond; zie hieronder. Zet er dus geen wachtwoordveld in met het idee
dat je daarmee iets afschermt, want dan is er één grendel die iemand kan
vergeten in plaats van twee die altijd werken.

## Wat er in een regel staat

```
wie     Erik Aarssen (of "Systeem" bij een geplande taak)
wat     Gewijzigd
waarop  Dienst — "Netwerkbeheer"
details { "titel": { "van": "Netwerk", "naar": "Netwerkbeheer" } }
wanneer 2026-09-25 10:14:03
```

Twee dingen daarin zijn met opzet een **momentopname** en geen verwijzing:
de naam van wie het deed, en het label van wat het was. Wordt het onderdeel
later verwijderd of het account opgeheven, dan is de regel nog steeds te
lezen. Een logboek dat leegloopt zodra het onderwerp verdwijnt, is precies op
het verkeerde moment nutteloos.

De `van`/`naar` is het verschil tussen een logboek dat zegt dát er iets
veranderde en een dat zegt wát.

## Nooit iets geheims

De oude en nieuwe waarden gaan door dezelfde `redact()` als het
beveiligingslogboek, met dezelfde lijst uit
[`config/security.php`](../../config/security.php). Dat is geen dubbelop: een
model kan een gevoelig veld krijgen zonder dat iemand daaraan denkt, en dan
hoort het logboek niet de plek te zijn waar dat alsnog uitlekt.

Dat is extra belangrijk hier, want dit logboek is bedoeld om **lang bewaard
te blijven en door mensen te worden gelezen**. Een geheim dat hierin belandt,
belandt op de slechtst denkbare plek. Regel 2 uit [`AGENTS.md`](../../AGENTS.md)
geldt dus onverkort, en
[`ActivityLoggerTest`](../../tests/Feature/Activity/ActivityLoggerTest.php)
bewaakt hem.

## Wat er níet in komt

- **Een opslag zonder wijziging.** Alleen een tijdstempel die opschuift is
  geen wijziging; zonder die grens vult het logboek zich met lege regels
  waarin de echte verdwijnen.
- **`created_at`, `updated_at` en `remember_token`.** Die veranderen bij elke
  opslag en zeggen niets.
- **Het IP-adres bij een commando.** In een geplande taak is er geen
  bezoeker, en dan is het adres van de server geen informatie maar ruis.

## Waar je het ziet

`/admin/activiteit`, achter `manage portal` net als de rest van het
beheergedeelte. Filteren kan op handeling, op soort onderdeel en op een
zoekterm in het label. Klap een regel open en je ziet per veld wat er stond
en wat er nu staat.

**Er staat geen kolom "wie".** Het portaal heeft één gebruiker, dus daar zou
op elke regel dezelfde naam staan. De naam wordt wél vastgelegd en is te
zien zodra je een regel openklapt; komt er ooit een tweede gebruiker, dan is
die kolom een kwestie van terugzetten. In de plaats daarvan staat er iets
nuttigers: **welke velden er zijn gewijzigd**, en hoe lang geleden. Daarmee
scan je de lijst zonder elke regel te hoeven openen.

De lijst met soorten in het filter komt **uit de tabel zelf** en niet uit een
lijst die iemand moet bijhouden. Komt er een module bij, dan staat hij er
vanzelf in zodra er één regel van is; verdwijnt een module, dan valt hij uit
het filter maar blijft de geschiedenis leesbaar.

## Bewaartermijn

`ACTIVITY_LOG_RETENTION_DAYS`, standaard een jaar. Het commando
`activity:prune` draait elke nacht om 03:25; zie
[onderhoudstaken](../operations/onderhoudstaken.md).

Die termijn staat los van die van het beveiligingslogboek. Bij onderzoek naar
een inbraak wil je maanden terug kunnen kijken; bij "wat heb ik vorige maand
aan die pagina veranderd" is een jaar ruim voldoende en daarna is het
ballast.

## Bij een nieuwe module

1. `use LogsActivity;` op het model.
2. `activityName()` en `activityLabel()` invullen, allebei met `__()`.
3. Controleer of er een veld tussen zit dat niets toevoegt, en zet dat in
   `activityHidden()`.
4. Zet in de test van die CRUD dat er een regel in het logboek komt bij
   aanmaken, wijzigen en verwijderen. Zie [testen](../development/testen.md).

Er komt géén nieuw recht bij. Alles in het beheergedeelte hangt achter
`manage portal`; zie [rollen en rechten](rollen-en-rechten.md).

## Wat er nog kan

- **"Laatst gewijzigd" op het dashboard.** De eigenaar ziet dan meteen wat er
  het laatst is aangepast, zonder naar het logboek te gaan.
- **Terugzetten.** De oude waarde staat er al; een knop "zet terug" is
  daarmee een kwestie van de wijziging omdraaien. Denk dan wel na over wat er
  gebeurt als het onderdeel intussen opnieuw is gewijzigd.
