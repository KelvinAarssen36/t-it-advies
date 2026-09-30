# De kop boven een onderdeel

Elk blok op de landingspagina heeft dezelfde drie teksten erboven: een
**opschrift** in kleine hoofdletters, een **titel**, en een **zin**
eronder. Ze staan allemaal in één tabel, `section_headings`, met een
regel per `PageSectionKey`.

> **Dit was drie tabellen.** `hero_headings`, `experience_headings` en
> `service_headings` waren bijna identiek, en elke module met een
> beheerbare kop maakte er een bij. Bij de certificaten zou het de
> vierde worden; dat was het afgesproken moment om ze samen te voegen.
> Zie [openstaand](../openstaand.md).

## Wat de samenvoeging oplevert

| Was                         | Is                                                                                            |
| --------------------------- | --------------------------------------------------------------------------------------------- |
| Drie tabellen               | `section_headings`, één rij per onderdeel                                                     |
| Drie modellen               | [`SectionHeading`](../../app/Models/SectionHeading.php)                                       |
| Drie `kop()`-methoden       | De trait [`BewaartKoptekst`](../../app/Http/Controllers/Website/Concerns/BewaartKoptekst.php) |
| Drie seeders                | [`SectionHeadingSeeder`](../../database/seeders/SectionHeadingSeeder.php)                     |
| Twee bijna gelijke vensters | [`KoptekstDialoog.vue`](../../resources/js/components/website/KoptekstDialoog.vue)            |

En, belangrijker dan het aantal bestanden: **elk toekomstig onderdeel
heeft nu gratis een beheerbare kop.** Er is geen migratie, geen model en
geen venster meer voor nodig -- alleen een regel in `standaard()` en een
`sectie()` op de controller.

## De kolommen

| Kolom                   | Waarom zo                                                                                                                                            |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `section` (uniek)       | De waarde van `PageSectionKey`. Uniek, want één onderdeel heeft één kop; zonder die index levert een dubbele opslag twee rijen op en wint de oudste. |
| `eyebrow_nl` / `_en`    | 60, **nullable**                                                                                                                                     |
| `title_nl` / `_en`      | 120                                                                                                                                                  |
| `intro_nl` / `_en`      | 300                                                                                                                                                  |
| `machine_translated_at` | Of het Engels van de vertaaldienst komt                                                                                                              |

**Waarom het opschrift nullable is terwijl het bij de kop en de diensten
verplicht is.** De kolom moet het slapste geval aankunnen, en dat is de
tijdlijn: die heeft nooit een opschrift gehad. Wát verplicht is verschilt
per onderdeel, en dat oordeel hoort in de FormRequest en niet in het
schema. `BewaartKoptekst::opschriftVerplicht()` zegt het per controller.

De lengtes zijn krap met reden: dit is een kop en geen alinea. Een titel
van tweehonderd tekens breekt het ontwerp op een telefoon.

## De terugval tussen de talen

Dezelfde regel als overal (zie [vertalingen](vertalingen.md)):

| Veld        | Engels leeg                                                                  |
| ----------- | ---------------------------------------------------------------------------- |
| Opschrift   | Terugvallen op het Nederlands -- het hoort bij de titel.                     |
| Titel       | Terugvallen -- een blok zonder kop is stuk.                                  |
| Zin eronder | Weglaten -- half Nederlands op een Engelse pagina is slordiger dan geen zin. |

Die regels staan op het model en niet in Vue. Zou elk scherm dat zelf
beslissen, dan staat er vroeg of laat half Nederlands op een Engelse
pagina.

## De standaardtekst staat op één plek

`SectionHeading::standaard()` is de enige bron. In de oude opzet stond
die tekst **twee keer** -- één keer in het model als vangnet voor een
vergeten seeder, één keer in de seeder om de rij echt neer te zetten --
met een commentaar dat dat met opzet dubbel was. Met één model hoeft dat
niet meer: de seeder haalt zijn tekst daar op, dus de twee kunnen niet
uit elkaar lopen.

**Er staat geen `__()` omheen, en dat is geen vergeten vertaling.** Dit
zijn de waarden van de kolommen `_nl` en `_en`, en die staan allebei
vast. Zou het Nederlandse veld door de vertaalfunctie gaan, dan komt er
bij een Engelse bezoeker Engelse tekst in het Nederlandse veld terecht --
en die schrijft de eigenaar over in zijn beheerscherm. `SectionHeadingTest`
houdt dat tegen.

## Waar de kop bewerkt wordt

**Vanaf het scherm van de module zelf.** Je gaat naar Diensten en klikt
daar op "Kop erboven"; de route hoort dus bij die module
(`website.diensten.kop`) en de methode bij die controller. Eén
`SectionHeadingController` zou één route opleveren waar de sectie als
parameter in moet, en dan is elk beheerscherm aan het uitleggen welk
onderdeel het is aan een adres dat het al weet.

Vandaar een trait en geen eigen controller. Een controller die hem
gebruikt zegt twee dingen: welk onderdeel het is, en wat er in de melding
komt te staan als het gelukt is.

De **tijdlijn is de uitzondering**: die slaat zijn kop samen met de
cijfers op, want op de website is dat één blok. Hij gebruikt daarom de
losse onderdelen van de trait (`koptekstRegels()` en `bewaarKoptekst()`)
in plaats van `verwerkKoptekst()`.

## Wat je moet weten bij een nieuw onderdeel

1. Een regel in `SectionHeading::standaard()`, met de Nederlandse én de
   Engelse tekst.
2. Het onderdeel toevoegen aan `SectionHeadingSeeder::MET_KOP`.
3. `use BewaartKoptekst` op de controller, plus een `sectie()`.
4. Een route `kop` in de groep van die module, **vóór** eventuele routes
   met een parameter -- het zijn allebei een PUT, en de eerste die past
   wint.
5. In het scherm een `KoptekstDialoog` met de route, de titels en een
   eigen `sleutel` voor de `id`-attributen.

## Wat waar staat

| Bestand                                                                                      | Wat het doet                                                   |
| -------------------------------------------------------------------------------------------- | -------------------------------------------------------------- |
| [`SectionHeading`](../../app/Models/SectionHeading.php)                                      | De rij, de terugval per veld, de standaardtekst, het logboek.  |
| [`BewaartKoptekst`](../../app/Http/Controllers/Website/Concerns/BewaartKoptekst.php)         | De validatie en het opslaan, gedeeld door alle modules.        |
| [`SectionHeadingSeeder`](../../database/seeders/SectionHeadingSeeder.php)                    | De starttekst per onderdeel.                                   |
| [`KoptekstDialoog.vue`](../../resources/js/components/website/KoptekstDialoog.vue)           | Het bewerkvenster, gedeeld door alle schermen.                 |
| [de migratie](../../database/migrations/2026_09_30_160000_create_section_headings_table.php) | De verhuizing van de drie oude tabellen, heen én terug.        |
| [`SectionHeadingTest`](../../tests/Feature/Website/SectionHeadingTest.php)                   | Eén rij per onderdeel, dat ze elkaar niet raken, en de seeder. |

## De verhuizing

De migratie maakt de tabel, zet de drie bestaande rijen over met hun
sleutel erbij, en laat de oude tabellen daarna vallen. Hier staat tekst
in die de eigenaar zelf heeft geschreven en die op zijn voorpagina
staat; een migratie die dat weggooit en de seeder er daarna overheen
laat gaan, zet zijn eigen woorden terug naar de onze.

Om diezelfde reden zet `down()` alles echt terug en doet hij niet alleen
een `dropIfExists`. Een teruggedraaide migratie die zijn tekst kwijtraakt
is erger dan de migratie zelf.

`experience_headings` had geen opschrift. De verhuizing vangt dat op met
een `??`; zonder die regel valt hij om op een kolom die daar niet
bestaat.

## Wat er níet veranderd is

**De tijdlijn heeft nog steeds geen opschrift.** De kolom bestaat nu wel,
maar het bewerkvenster laat hem niet zien en de seeder vult hem niet.
Dit samenvoegen was een verhuizing en geen herontwerp van de voorpagina;
wil de klant er later een, dan is dat één veld erbij. Het staat als open
punt in [openstaand](../openstaand.md).
