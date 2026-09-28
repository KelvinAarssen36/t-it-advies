# Vertalingen

De site en het portaal zijn tweetalig: Nederlands en Engels. Nederlands is
de standaard.

> **Stand van zaken.** Het fundament en de drie wisselknoppen staan en zijn
> getest. De teksten zelf zijn nog grotendeels Nederlands in de code; die
> verhuizen naar de taalbestanden zodra we daaraan toe zijn. Het plan voor
> inhoud die de klant zelf invoert staat onderaan en is nog niet gebouwd.

## Eén plek beslist

[`SetLocale`](../../app/Http/Middleware/SetLocale.php) bepaalt bij elk
verzoek de taal, in deze volgorde:

1. `$user->locale` -- de voorkeur van de ingelogde gebruiker
2. `session('locale')` -- voor bezoekers zonder account
3. `config('app.locale')` -- en anders de standaard

Met bij elke stap een toets tegen de witte lijst in
`config('app.available_locales')`.

Dat is de kern van het ontwerp: **nergens anders staat een
`App::setLocale()`**. Een knop schrijft alleen naar de bronnen hierboven, en
deze middleware leest ze. Zo kan er geen scherm zijn dat zijn eigen taal
kiest en van de rest afwijkt.

**De volgorde is betekenisvol.** Ben je ingelogd, dan wint je profiel altijd
van de sessie. Anders zou iemand die op een gedeelde computer even naar
Engels wisselt daarmee de voorkeur van de eigenaar overschrijven.

De middleware hangt in de `web`-groep, ná die van Laravel zelf. Hij leest de
sessie en de ingelogde gebruiker, en die bestaan pas nadat `StartSession`
heeft gedraaid -- met `prepend` krijg je op elke pagina een 500. Wel vóór
`HandleInertiaRequests`, want die deelt de gekozen taal met de frontend.

## Wisselen

Eén route voor alle knoppen:
[`LocaleController`](../../app/Http/Controllers/LocaleController.php), als
`POST taal/{locale}`.

Hij doet drie dingen:

1. **Toetsen tegen de witte lijst.** De taal komt uit de URL; zonder die
   toets zet je een waarde uit een verzoek rechtstreeks in de applicatie.
2. **In de sessie zetten** -- dat geldt voor deze browser, ook zonder
   account.
3. **En in het profiel**, als er iemand is ingelogd. Dat detail verbindt de
   twee helften: wisselt de eigenaar op de publieke site terwijl hij is
   ingelogd, dan staat het portaal er straks ook in.

Daarna `back()`. Een herlading is hoe dan ook nodig, want vertaalde tekst
wordt bij het **renderen** bepaald: alleen de taal omzetten laat de rest van
het scherm in de oude taal staan.

## Vier plekken, drie vormen

| Waar                      | Component               | Vorm         |
| ------------------------- | ----------------------- | ------------ |
| Kop van de publieke site  | `site/LocaleToggle.vue` | Segmentknop  |
| Onder het inlogscherm     | `site/LocaleToggle.vue` | Segmentknop  |
| Accountmenu in de zijbalk | `LocaleSwitcher.vue`    | Omwisselknop |
| Instellingen → profiel    | `LocaleSelect.vue`      | Keuzelijst   |

Dat de knop ook onder het inlogscherm staat is geen sierraad: wie de
voordeur in de verkeerde taal krijgt, kan die nog nergens omzetten -- de
instellingen liggen achter het inloggen.

**De segmentknop** is de etalageversie: een pil met een vakje per taal en
een gloeiende indicator die ertussen glijdt. Beide talen staan er dus
altijd, dus je ziet in één oogopslag waar je staat én wat je kunt kiezen.
Zie [de segmentknop](#de-segmentknop) hieronder.

**De omwisselknop** in het portaal toont waar je nu staat en waar je heen
gaat: de vlag van de huidige taal, een pijl, en de vlag van de andere. De
doelvlag staat op `opacity-60`, de huidige op vol. Dat ene verschil maakt
zonder tekst duidelijk welke van de twee je _hebt_. Het portaal is
gereedschap en geen etalage, dus daar blijft het bij een rustige knop.

**De keuzelijst** hoort in de instellingen, want daar staat taal tussen de
andere voorkeuren en verwacht je een lijst. Er is geen opslaan-knop: de
keuze is meteen de handeling. Wel een poortje vooraan dat niets schrijft als
er niets veranderd is.

Allebei lezen ze de huidige taal uit de gedeelde Inertia-props (`locale` en
`locales`) en niet uit een eigen toestand. De server beslist; de knop vraagt
het alleen.

## De segmentknop

[`site/LocaleToggle.vue`](../../resources/js/components/site/LocaleToggle.vue).
Een pil met twee vakken; de indicator eronder heeft het merkverloop en een
cyaan halo, en glijdt naar het vak dat je kiest.

Vier dingen die het verschil maken:

**De indicator wordt gemeten, niet berekend.** De vakken zijn zo breed als
hun tekst, en "Nederlands" is langer dan "English". Het component leest
`offsetLeft` en `offsetWidth` van het actieve vak en zet die als `--vak-x`
en `--vak-w`; de CSS doet de rest. Een vaste breedte zou bij een derde taal
meteen misstaan. Een `ResizeObserver` hermeet als het lettertype binnenkomt
of de taal wisselt.

**De eerste meting animeert niet.** Pas na twee frames krijgt de pil de
klasse `is-ready`, en pas dan staan de overgangen aan. Zonder dat schuift de
indicator bij het laden van elke pagina even vanaf links naar zijn plek.

**De indicator loopt vooruit op de server.** Wisselen is een nieuw verzoek;
zou de indicator daarop wachten, dan lijkt de knop een tel lang kapot. Gaat
het verzoek mis, dan valt hij terug op wat de server zegt.

**De curve schiet een klein stukje door en veert terug**
(`cubic-bezier(0.34, 1.35, 0.45, 1)`). Dat is het verschil tussen "de knop
reageert" en "de knop leeft"; zonder die overshoot voelt exact dezelfde
beweging traag. Er trekt tegelijk één lichte veeg over de indicator.

Bij `prefers-reduced-motion` springt de indicator gewoon naar zijn plek en
blijft de veeg weg. De knop werkt dan precies hetzelfde; alleen de show gaat
eraf.

### Waarom hier geen GSAP zit

GSAP is op de publieke site beschikbaar, maar dit component staat ook onder
het inlogscherm -- en dat zit in de hoofdbundel. Zou er GSAP in zitten, dan
haalt iemand die alleen wil inloggen ruim honderd kilobyte animatielaag op
voor één knop. De beweging is een verschuiving en een breedte, en dat doet
een CSS-overgang net zo goed. De hele knop kost nu zo'n 2 kB.

De stijl staat in `app.css` onder `.brand-locale-*`, omdat er keyframes en
pseudo-elementen bij horen die niet in Tailwind-klassen passen.

## Datums en tijden

Een taal is meer dan woorden. `2026-09-28 10:14:03` is het formaat van een
database, niet van een mens: het jaar staat vooraan en er staan seconden in
die niemand nodig heeft. In Nederland komt de dag eerst.

Alles loopt daarom via [`App\Support\Datum`](../../app/Support/Datum.php),
met drie vormen:

| Aanroep             | Nederlands            | Engels               | Waarvoor                        |
| ------------------- | --------------------- | -------------------- | ------------------------------- |
| `Datum::tijdstip()` | `28 sep. 2026, 10:14` | `28 Sep 2026, 10:14` | Een regel in een logboek.       |
| `Datum::dag()`      | `28 sep. 2026`        | `28 Sep 2026`        | Iets dat een dag betreft.       |
| `Datum::geleden()`  | `2 uur geleden`       | `2 hours ago`        | Meestal wat je écht wilt weten. |

**De maand staat er met letters, in allebei de talen.** `28-09-2026` is voor
een Nederlander duidelijk, maar een Engelstalige leest daar gemakkelijk een
maand in die er niet staat -- en andersom net zo. Met "28 sep" bestaat die
verwarring niet, en het scant sneller ook.

**`Datum::geleden()` en `Datum::tijdstip()` sluiten elkaar niet uit.** In het
activiteitenlogboek staan ze onder elkaar: "2 uur geleden" is wat je wilt
weten, het exacte tijdstip is de tweede vraag.

### Carbon heeft zijn eigen taal

Dat is de valkuil. `App::setLocale()` zet de taal van de vertalingen, maar
niet die van Carbon -- en dan blijft het "2 hours ago" in een Nederlands
portaal, met "Sep" in plaats van "sep".
[`SetLocale`](../../app/Http/Middleware/SetLocale.php) zet ze allebei, op
dezelfde plek waar de taal wordt bepaald.

### Niet in de browser

Datums worden op de **server** opgemaakt en als kant-en-klare tekst
meegegeven. Niet met `toLocaleDateString()` in Vue: dan hangt de uitkomst af
van de taalinstelling van het besturingssysteem van de bezoeker, en niet van
de taal die hij in het portaal heeft gekozen. Dat zijn twee verschillende
dingen, en op een Engelstalige laptop met een Nederlands portaal zie je het
verschil meteen.

## De vlaggetjes

Handgetekende SVG's in `public/flags/`, geen icoonpakket en geen emoji.
Twee bestanden, samen zo'n 700 bytes.

`nl.svg` is drie rechthoeken, waarvan er maar twee getekend worden: de
middelste baan is gewoon de witte ondergrond. `gb.svg` is zes paden, en
gebruikt dezelfde truc met lijnen in plaats van vlakken -- elke rode lijn
ligt op een bredere witte, waardoor de witte rand vanzelf ontstaat. Geen
enkel pad heeft coordinaten voor een omlijning nodig; je stapelt dun op dik.

Het is een **vereenvoudigde** Union Jack: de echte heeft versprongen
diagonalen. Op vijf pixels breed ziet niemand dat, en het scheelt de helft
van de code.

Waarom zo:

- **Geen icoonpakket.** `flag-icons` en dergelijke slepen tweehonderdvijftig
  landen mee voor twee vlaggen. Bij twee talen schrijf je ze sneller zelf
  dan dat je de afhankelijkheid uitlegt.
- **Geen emoji-vlaggen.** Die tekent het besturingssysteem, dus ze zien er
  op elk apparaat anders uit -- en op Windows worden ze helemaal niet als
  vlag getoond, maar als twee letters in een hokje. Dat is precies de reden
  dat ze hier weg zijn.
- **Geen PNG.** Een vlag van vijf pixels breed die ook op een scherm met
  hoge dichtheid scherp moet zijn, is waar SVG voor bedoeld is.
- **In `public/` en niet in de bundel.** Losse plaatjes via `<img src>`, dus
  de browser cachet ze en ze kosten niets aan bundelgrootte.

### Eén component, geen ternair per scherm

Alles loopt via
[`LocaleFlag.vue`](../../resources/js/components/LocaleFlag.vue). Dat is de
enige plek waar een taalcode aan een bestand wordt gekoppeld:

```vue
<LocaleFlag :locale="current" size="md" />
```

Bij twee talen zou `locale === 'nl' ? … : …` in elk scherm ook werken, maar
dan heb je bij een derde taal net zoveel plekken om te vergeten. Het scheelt
nu niets en straks een zoektocht.

Drie maten, allemaal ongeveer 3:2 -- dezelfde verhouding als de `viewBox`,
dus er wordt niets uitgerekt:

| `size` | Klassen     | Waar                          |
| ------ | ----------- | ----------------------------- |
| `sm`   | `h-3 w-4.5` | Accountmenu                   |
| `md`   | `h-3.5 w-5` | Landingspagina en inlogscherm |
| `lg`   | `h-4.5 w-7` | Instellingen                  |

`object-cover` staat erbij als vangnet: kiest iemand later een maat die niet
klopt, dan snijdt de vlag af in plaats van scheef te trekken. De ronding is
`rounded-[2px]` -- net genoeg om de hoeken te breken zodat het een plaatje
wordt in plaats van een gekleurd blokje. Meer, en het leest als een knop.

In de instellingen staat de vlag **naast** de keuzelijst en niet erin.
Sinds die lijst van onszelf is zou een vlag per regel kunnen, maar dan staat
dezelfde vlag twee keer in beeld zodra je hem opent. Eén keer, naast het
veld, zegt genoeg. Zie
[formulieren en schuifbalken](formulieren-en-schuifbalken.md).

### De alt is de taalnaam, niet het land

`alt="Nederlands"` en niet `alt="Nederlandse vlag"`. Een schermlezer leest
dan hetzelfde als wat een ziende naast het vlaggetje ziet staan, zonder
dubbeling. In de omwisselknop staan beide vlaggen op `aria-hidden`, omdat
het `aria-label` van de knop zelf al zegt waar je heen gaat -- anders hoor
je drie keer een taalnaam achter elkaar.

### Een taal is geen land

Engels krijgt hier de vlag van het Verenigd Koninkrijk. Dat is een keuze en
geen feit. Bij Nederlands en Engels valt het niet op; bij Spaans of
Portugees kies je er een kant mee. Wie dat wil vermijden gebruikt een hokje
met de taalcode (`NL` / `EN`) in plaats van een vlag.

## De taalbestanden

`lang/nl.json` en `lang/en.json`, met de Nederlandse tekst als sleutel:

```php
__('Deze code klopt niet.')
```

Nederlands is de sleuteltaal, dus `lang/nl.json` is leeg -- er valt niets te
vertalen. In `lang/en.json` staat de Engelse kant.

### Dezelfde bestanden in de frontend

Het portaal is Vue, en `__()` is PHP. Toch is er maar één woordenlijst:
[`HandleInertiaRequests`](../../app/Http/Middleware/HandleInertiaRequests.php)
deelt de regels van de actieve taal als prop `translations`, en
[`lib/i18n.ts`](../../resources/js/lib/i18n.ts) zoekt daarin op.

In een sjabloon gebruik je de globale functie:

```vue
<Button>{{ $t('Opslaan') }}</Button>
<Input :placeholder="$t('Zoek op naam of e-mailadres')" />
```

In een `<script setup>`, bijvoorbeeld voor een kruimelpad of een
bevestigingsvenster, importeer je dezelfde functie:

```ts
import { t } from '@/lib/i18n';

breadcrumbs: [{ title: t('Beheer'), href: dashboard() }];

window.confirm(
    t('Weet je zeker dat je het account van :naam verwijdert?', {
        naam: row.name,
    }),
);
```

Let op: een lijst die met `t()` is opgebouwd hoort in een `computed` te
staan. Doe je dat niet, dan is hij één keer berekend en blijft hij bij een
taalwissel in de oude taal staan.

Vervangingen werken zoals in Laravel: `:naam` in de tekst, een sleutel
zonder dubbele punt in het object ernaast.

### Wat dit kost

In het Nederlands niets: `lang/nl.json` is leeg, dus er gaat een leeg object
over de lijn. Alleen wie Engels kiest krijgt de woordenlijst mee, een paar
kilobyte. Het is bovendien een closure, dus een gedeeltelijke herlading die
deze prop niet opvraagt leest het bestand niet eens in.

Groeit de lijst ooit tot tientallen kilobytes, dan is de volgende stap hem
als apart JS-bestand per taal te serveren in plaats van als prop. Zo ver is
het nog lang niet.

### De sleutel is de Nederlandse zin

Dat is de standaard van Laravel voor JSON-vertalingen, en de reden dat er
geen tweede woordenlijst met verzonnen namen als `portal.nav.dashboard`
bestaat: je leest in het sjabloon gewoon wat er komt te staan, en een
ontbrekende vertaling valt terug op het Nederlands -- de taal die de klant
toch al spreekt.

De keerzijde: wijzig je een Nederlandse zin, dan wijzigt de sleutel mee en
valt de Engelse vertaling terug op het Nederlands. Daarom bewaakt
[`TranslationsTest`](../../tests/Feature/TranslationsTest.php) dat elke
`$t('…')` en `t('…')` in `resources/js` een regel heeft in `lang/en.json`.
Verander je een zin zonder de vertaling bij te werken, dan valt die test om
en noemt hij de sleutel bij naam.

### Wat nog niet vertaald is

Vertaald: het hele portaal -- de zijbalk, het accountmenu, het dashboard, de
instellingen en het beheergedeelte -- en de inlogschermen. De statuslabels
van mail en beveiliging komen uit enums die al `__()` gebruikten, dus die
liepen vanzelf mee.

De koppen van de inlogschermen staan in `defineOptions({ layout: … })` en
worden vertaald door
[`AuthSimpleLayout.vue`](../../resources/js/layouts/auth/AuthSimpleLayout.vue),
om dezelfde reden als bij de kruimelpaden: de moduleruimte heeft nog geen
taal.

Nog niet vertaald is de publieke site. Dat heeft een reden: die teksten
worden inhoud die de klant zelf beheert, en daarvoor geldt het plan
hieronder.

Voeg je een taal toe, dan hoort daar een bestand bij, een regel in
`config('app.available_locales')`, een SVG in `public/flags/` en een regel in
de tabel in `LocaleFlag.vue`. Overweeg bij een derde taal meteen of een vlag
nog klopt, of dat de taalcode eerlijker is.

## Wat er nog moet gebeuren

### De vaste teksten

De meeste teksten staan nu als Nederlands in de Vue-componenten. Die moeten
door een vertaallaag heen. Twee wegen:

- **Een pakket**, bijvoorbeeld `laravel-vue-i18n`, dat de bestanden uit
  `lang/` naar de frontend brengt zodat je daar ook `__()` kunt schrijven.
- **Zelf doen**: de vertalingen als prop meegeven via Inertia en een kleine
  eigen helper.

Het eerste is minder werk en beter onderhouden. Kies dat pas als we de
teksten daadwerkelijk gaan omzetten, en leg de keuze vast in
[het beslislogboek](../decisions/README.md).

### De inhoud die de klant invoert

Dit is het deel dat met de CRUD's meekomt, en het plan is:

De klant vult een item in -- een dienst, een tijdlijnpunt -- in het
Nederlands. Klikt hij door, dan volgt **een tweede stap**: "en nu de Engelse
teksten". Daar staan dezelfde velden, maar dan voor het Engels.

Vult hij die niet in, dan wordt de tekst **automatisch vertaald**, en
krijgt hij daar een duidelijke waarschuwing bij. Automatisch vertaald Engels
is beter dan geen Engels, maar de klant moet weten dat het er staat en dat
het niet zijn eigen woorden zijn.

Wat daarbij nog beslist moet worden:

- **Waar de vertaling vandaan komt.** Een vertaaldienst kost geld per teken
  en is een externe afhankelijkheid; dat is een beslissing voor het
  beslislogboek, niet iets om er stilletijn in te schuiven.
- **Hoe je ziet dat iets automatisch vertaald is.** In de database hoort
  daar een vlag bij, zodat het overzicht kan tonen wat nog nagelopen moet
  worden.
- **Hoe het wordt opgeslagen.** Kolommen per taal (`titel_nl`, `titel_en`)
  zijn het voorstel: saai, makkelijk te valideren, en het formulier met twee
  tabbladen volgt er vanzelf uit.

Zodra de eerste CRUD er is, hoort daar een test bij die bewijst dat beide
talen worden opgeslagen en teruggegeven. Zie
[testen](../development/testen.md).
