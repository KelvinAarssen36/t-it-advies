# Frontend en animatie

## Opbouw

Vue 3 met TypeScript, via Inertia aan Laravel gekoppeld. Pagina's staan in
`resources/js/pages` en komen overeen met wat de controller aan
`Inertia::render()` meegeeft. Layouts worden centraal toegewezen in
`resources/js/app.ts`:

- `Welcome` en alles onder `public/` krijgt `PublicLayout`.
- Alles onder `auth/` krijgt `AuthLayout`.
- Alles onder `settings/` krijgt `AppLayout` plus de instellingen-layout.
- De rest krijgt `AppLayout`, inclusief het beveiligde gedeelte onder `admin/`.

Een nieuwe openbare pagina zet je dus in `resources/js/pages/public/` en die
heeft meteen de kop, de voet en de animatielaag.

`PublicLayout` wordt **asynchroon** geladen met `defineAsyncComponent`. Dat is
geen stijlkeuze: de layout trekt GSAP en Lenis mee, en bij een gewone import
boven aan `app.ts` belandt die hele animatielaag in de hoofdbundel. Dan
betaalt ook het beheergedeelte ruim honderd kilobyte voor animaties die het
niet gebruikt. Zo staat het in een eigen chunk die alleen de publieke site
ophaalt.

## De publieke site

| Onderdeel                            | Waarvoor                                                |
| ------------------------------------ | ------------------------------------------------------- |
| `layouts/PublicLayout.vue`           | Kop, voet, smooth scrolling en de scroll-reveals.       |
| `components/site/SiteHeader.vue`     | Navigatie. Krijgt pas een achtergrond zodra je scrollt. |
| `components/site/SiteFooter.vue`     | Voet met accentlijn.                                    |
| `components/site/SiteSection.vue`    | Eén breedte en één verticale ruimte voor alle secties.  |
| `components/site/SectionHeading.vue` | Bovenschrift, titel en inleiding.                       |
| `components/site/FeatureCard.vue`    | Kaart op donker, met glow op hover.                     |
| `components/site/ScrollProgress.vue` | De voortgangsbalk bovenaan.                             |
| `components/site/sections/`          | De onderdelen zelf: kop, diensten, werkwijze, contact.  |

**De landingspagina bepaalt niet meer welke onderdelen er staan.** Dat doet
de klant, in het portaal. `Welcome.vue` is een `v-for` over de sleutels die
de server meegeeft, met een getypte kaart van sleutel naar component; de kop
staat er los boven omdat die niet te verslepen is. Wil je er een onderdeel
bij, lees dan [pagina-indeling](pagina-indeling.md) -- een component
toevoegen zonder de vier stappen daar levert een sectie op die nergens
verschijnt.

Daarnaast zijn er componenten die op beide helften worden gebruikt, zoals
[`CopyButton.vue`](../../resources/js/components/CopyButton.vue) voor alles
wat te kopiëren valt. Die staan in `components/` en niet in
`components/site/`.

Gebruik `SiteSection` ook als je denkt dat je maar één keer afwijkt. Zodra
elke pagina zijn eigen padding kiest, staat niets meer op één lijn, en dat is
achteraf niet meer recht te trekken.

**Er staat bewust geen inloglink op de publieke site.** Er is één gebruiker,
de eigenaar, en die kent zijn eigen adres. Een inlogknop wijst bezoekers
alleen maar op een deur die niet voor hen is. De link naar het portaal
verschijnt alleen voor wie al is ingelogd. Zet er dus ook geen terug in een
nieuwe openbare pagina.

De reveals worden opnieuw gescand na elke Inertia-navigatie. De layout blijft
namelijk staan, dus `onMounted` draait maar één keer; zonder die herscan zou
de inhoud van een volgende pagina op `opacity: 0` blijven hangen.

## De zijbalk van het portaal

Vier groepen, en die indeling volgt waar iemand naar op zoek is:

| Groep             | Wat erin staat                                           |
| ----------------- | -------------------------------------------------------- |
| _(geen kopje)_    | Het dashboard. Eén regel, dus een kopje erboven is ruis. |
| **Website**       | Alles waarmee de eigenaar zijn eigen site vult.          |
| **Administratie** | De zakelijke kant. Nu alleen de mail.                    |
| **Beheer**        | De logboeken en de gebruikers.                           |

De volgorde is geen alfabet maar frequentie: **Website staat bovenaan**,
want daar moet de eigenaar dagelijks zijn. Het beheergedeelte kijk je na,
dat gebruik je niet de hele dag, dus dat staat onderaan.

**Het verschil tussen Administratie en Beheer is niet willekeurig.** Beheer
gaat over het portaal zelf -- wie wat wijzigde, wie probeerde in te loggen,
welke accounts er zijn. Administratie gaat over het bedrijf: wat eruit is
gegaan, en straks aan wie en waarvoor. Weet je van iets nieuws niet waar
het hoort, stel dan die vraag: gaat het over de website, over de zaak, of
over het portaal?

Dat Administratie nu één regel telt is geen vergissing. Het is de groep
die gaat groeien, en het is prettiger dat de plek er alvast is dan dat de
mail straks van groep verhuist terwijl de eigenaar hem net had gevonden.

**Vier is het maximum.** Een zijbalk met zeven secties is een
inhoudsopgave, en daar zoek je langer in dan in een lijst. Komt er een
module bij, dan hoort die in een van deze vier en niet in een nieuwe
groep.

**Elke module krijgt in de groep Website zijn eigen regel.** Het
beheerscherm van een onderdeel is waar de eigenaar dagelijks moet zijn, en
daar hoor je in één klik te komen -- niet via een omweg.

De [indeling](pagina-indeling.md) staat bovenaan die groep. Dat is de kaart
van de site: alle onderdelen onder elkaar, met per onderdeel ook een link
naar zijn beheerscherm. Handig om te hebben, maar het is niet de ingang.

"Bekijk de website" staat onderaan, omdat je daar vanaf élk scherm heen
wilt kunnen.

Bouw je een module, vergeet die regel dan niet.
[`AppSidebarTest`](../../tests/Feature/Website/AppSidebarTest.php) valt om
als een onderdeel wél een beheerscherm heeft maar niet in het menu staat --
precies het soort ding dat je pas merkt als de klant ernaar vraagt.

Het hele beheerblok hangt achter één recht; zie
[rollen en rechten](../security/rollen-en-rechten.md). Dat is cosmetisch,
niet de beveiliging.

### Inklappen, en waarom het kopje in de smalle balk blijft

Elke groep met een kopje kun je dichtklappen, en die keuze wordt onthouden
in `localStorage` -- per groep, per apparaat.

**In de pictogramstand verdwijnt dat kopje niet, maar krimpt het tot
alleen zijn pijltje.** Dat is de hele truc, en hij loste een klacht op die
er twee keer was. Eerst klapte in die stand alles open, omdat er geen
kopje was om op te klikken en een dichte groep dus onbereikbaar zou zijn.
Gevolg: een groep die je net had dichtgeklapt sprong weer open zodra je de
balk versmalde, en dat leest als een instelling die niet blijft hangen.

Met een knopje van één pictogram hoeft er niets voor de gebruiker beslist
te worden: zijn keuze blijft staan, en hij kan hem daar gewoon omzetten.
De naam van de groep zit in het `aria-label`, want de tekst is verborgen.

### Het sterretje bij de hoofdpagina van een groep

Achter "Indeling" en achter "Overzicht" staat een klein sterretje in de
merkkleur. Dat markeert de hoofdpagina van zijn groep: de plek om te
beginnen als je niet precies weet waar je moet zijn.

Zet het met `hoofd: true` op het item in
[`AppSidebar.vue`](../../resources/js/components/AppSidebar.vue);
[`NavMain`](../../resources/js/components/NavMain.vue) tekent het dan in
beide takken van het menu. **Per groep hoort er precies één te zijn**,
anders zegt het teken niets meer.

Het is bewust níet het rode sterretje van een verplicht veld. Dat betekent
iets anders, en twee betekenissen aan één teken hangen is vragen om
verwarring; daarom is deze klein, blauw en met een eigen uitleg in zijn
`title` en in een `sr-only`-tekst. De animatie -- langzaam pulseren, bij
het aanwijzen aanzwellen en een kwartslag draaien -- zit in
`.brand-nav-hoofd` in [`app.css`](../../resources/css/app.css) en gaat uit
bij `prefers-reduced-motion`.

### Het pijltje dat zegt dat je het portaal verlaat

"Bekijk de website" in de zijbalk en "Bekijk het resultaat" op de indeling
en bij Ervaring gaan naar de publieke site. Dat is geen scherm van het
portaal: de zijbalk is weg, het thema staat vast op donker, en terugkomen
doe je met de terugknop van de browser. Dat hoort te blijken vóórdat je
klikt, en niet pas daarna.

Daarom staat achter die links
[`VerlaatPortaal.vue`](../../resources/js/components/VerlaatPortaal.vue):
een klein pijltje naar rechtsboven, met een `title` en een `sr-only`-tekst
die het uitschrijft. In de zijbalk zet `item.verlaat` het teken erbij; in
een knop hang je het component als laatste kind achter de tekst.

**Het opent geen nieuw tabblad, en dat is een keuze.** Een link die
ongevraagd een tabblad opent neemt een beslissing over de browser van
iemand anders, en na drie keer kijken op de site staan er vier tabbladen
open. Het pijltje is dus een markering en geen belofte. Zet er om dezelfde
reden ook geen `target="_blank"` bij als je het teken ergens nieuw
gebruikt.

### Het kruimelpad bovenin

Boven elk scherm van het portaal staat het pad ernaartoe: "Website >
Ervaring > Directeur". Elke kruimel behalve de laatste is een link; de
laatste is de pagina waar je al staat en is dus geen link.

De kruimels komen van de pagina zelf, via de `layout`-optie:

```ts
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Website', href: website.index() },
            { title: 'Ervaring', href: ervaring.index() },
        ],
    },
});
```

**Hangt de laatste kruimel af van wat er op het scherm staat**, zoals de
naam van een ervaring, dan mag `layout` ook een functie zijn die de props
van de pagina krijgt en er een object van maakt:

```ts
defineOptions({
    layout: (props: { item: ErvaringRij }) => ({
        breadcrumbs: [
            { title: 'Website', href: website.index() },
            { title: 'Ervaring', href: ervaring.index() },
            { title: props.item.role_nl, href: ervaring.show(props.item.id) },
        ],
    }),
});
```

Twee valkuilen. De eerste: **elke nieuwe pagina moet dit zelf zetten**. Er
is geen automatische afleiding uit de URL, dus vergeet je het, dan staat
er niets en merk je dat pas als je het scherm opent. De tweede: de titels
gaan door `$t()` zodra ze uit de code komen, maar de naam van een ervaring
is inhoud van de klant en blijft staan zoals hij hem heeft ingevoerd.

Naast het pad staat de knop die de zijbalk in- en uitklapt. Die hoort bij
het kruimelpad en niet bij de pagina: hij moet er ook zijn op een scherm
dat verder leeg is.

### Het laadscherm tussen de twee helften

Landing en portaal zijn twee verschillende bundels. De publieke site trekt
de hele animatielaag mee -- GSAP en Lenis, ruim honderd kilobyte -- en het
portaal heeft zijn eigen schil met de zijbalk. Stap je van de een naar de
ander, dan moet die bundel eerst binnenkomen, en zonder tussenscherm kijk je
een tel tegen een pagina aan die zich nog aan het opbouwen is.

Daarom loopt **elke overgang tussen de twee helften** via een laadscherm:

| Van      | Naar    | Route          | Waar je erop klikt                          |
| -------- | ------- | -------------- | ------------------------------------------- |
| Inloggen | Portaal | `portal.enter` | Automatisch, na het inloggen                |
| Landing  | Portaal | `portal.enter` | "Dashboard" in de kop, alleen als je inlogt |
| Portaal  | Landing | `site.enter`   | "Bekijk de website" in de zijbalk           |

Het is één component,
[`BrandLoader`](../../resources/js/components/BrandLoader.vue); de pagina's
eromheen zeggen alleen waar je heen gaat en wat er te lezen valt.

#### Het scherm toont waar je heen gaat

Niet een merkscherm dat overal hetzelfde is, maar een voorproefje van de
bestemming. Dat is het verschil tussen "even wachten" en "het wordt
klaargezet".

**Naar het portaal** zie je het skelet van het portaal: de zijbalk met de
menuregels, de kop, en de vlakken van het dashboard -- in jouw eigen thema,
met dezelfde maten en dezelfde inset-indeling als de echte schil. Komt het
portaal binnen, dan staat alles al op zijn plek en voelt het alsof de pagina
zich vult in plaats van dat hij wordt vervangen. Zonder die maten klopt het
niet: een skelet dat een centimeter verspringt is erger dan geen skelet.

**Naar de website** zie je het merk op donker met de aurora, want dat is wat
daar op je wacht.

Drie details:

- **De plaatsvervangers lichten na elkaar op**, niet allemaal tegelijk
  (`--skeleton-delay`). En het is een veeg die één kant op loopt, geen
  pulserend vlak: een pulserend vlak leest als "hier is iets mis", een veeg
  als "er komt iets aan".
- **De balk bovenaan loopt snel naar tachtig procent en kruipt daarna.** Een
  balk die netjes naar honderd loopt en dan blijft staan liegt over hoe lang
  het nog duurt; een die halverwege stopt ziet eruit alsof hij vastzit.
- **`aria-busy` en een live region.** Wie met een schermlezer werkt hoort
  niet naar een stil scherm te luisteren dat vanzelf verspringt.

**De begroeting hoort bij het inloggen, niet bij het laadscherm.** Dat
onderscheid kwam met de tweede richting: de vlag `portal.welcome` stond
eerst in `PortalEntryController`, en toen dat scherm ook tussen de website
en het portaal kwam te staan, zou je begroet worden telkens als je even op
je eigen site had gekeken. Hij wordt nu gezet in de `LoginResponse` in
`FortifyServiceProvider`. Zie
[authenticatie en 2FA](../security/authenticatie-en-2fa.md).

Drie dingen die het meer maken dan een pauze:

- **De wachttijd wordt gebruikt.** Het scherm haalt de bestemming alvast op
  met `router.prefetch`, dus de overgang daarna is meteen in plaats van dat
  het wachten dán pas begint.
- **Het staat altijd in de huisstijl.** Je bent op dat moment onderweg en
  nog nergens. En het portaal kan in het lichte thema staan terwijl de
  website altijd donker is: zo val je er niet middenin, maar word je erop
  voorbereid.
- **Het is geen val.** De overgang gebruikt `replace`, dus de terugknop
  brengt je naar waar je vandaan kwam en niet terug op het laadscherm -- dat
  je dan meteen weer zou doorsturen. En er staat een gewone link onder, voor
  het geval JavaScript het niet doet.

Terug naar de website duurt korter dan naar binnen gaan (720 tegen 960 ms).
Naar binnen gaan is een moment; even naar je eigen site kijken is dat niet.

### Inklappen

Een groep **mét** kopje kun je dichtklappen, een groep zonder niet. Dat is
geen uitzondering maar dezelfde regel van twee kanten: de groep zonder kopje
is het dashboard, en één regel wegklappen levert niets op.

Het kopje ís de knop -- niet alleen het pijltje ernaast. Een doelwit van
twaalf pixels naast een tekst die er niets mee doet is een raadsel dat je
niet hoeft op te geven. Het kopje licht op bij hover, want een kopje dat
klikbaar is moet dat laten zien.

De keuze wordt onthouden in `localStorage`, per groep. Dat is precies waar
die opslag voor is: een gemak voor deze bezoeker op dit apparaat, en niets
dat ergens anders hoeft te kloppen. Lukt lezen of schrijven niet -- een
privévenster, geblokkeerde opslag -- dan staat de groep gewoon open.
Dichtklappen is het gemak; openstaan is de juiste uitkomst.

**Ingeklapt tot pictogrammen staat alles open** en verdwijnt het kopje.
Anders zou een dichtgeklapte groep onbereikbaar zijn: je ziet hem niet en je
kunt hem niet openen.

De hoogte animeert van 0 naar de echte hoogte, en dat kan alleen doordat
reka-ui die hoogte meet en als `--reka-collapsible-content-height` op het
element zet. Naar `height: auto` animeert een browser namelijk niet.
Openklappen duurt 260 ms, dichtklappen 200 ms: bij openen wil je zien wát er
verschijnt, bij sluiten heb je die beslissing al genomen en is wachten
alleen maar traag. Het pijltje draait een kwartslag mee.

Bij `prefers-reduced-motion` springt het gewoon open en dicht.

## Het lettertype

Instrument Sans, in drie diktes (400, 500, 600). Het wordt **niet** bij een
externe dienst opgehaald: `laravel-vite-plugin/fonts` haalt de bestanden bij
de build op en zet ze in `public/build/`, zodat er in productie geen verzoek
naar een ander domein gaat.

Daarnaast staat `fontaine` in de dev-dependencies. Dat pakket meet het echte
lettertype door en schrijft een `@font-face` met dezelfde metriek op basis
van een lokale Arial:

```css
@font-face {
    font-family: 'Instrument Sans fallback';
    src: local('Arial');
    ascent-override: 93.49%;
    size-adjust: 103.76%;
}
```

Die naam staat in `--font-sans` vóór de gewone terugvallers. Het effect is
dat de tekst die je in de eerste honderden milliseconden ziet precies
evenveel ruimte inneemt als de echte, en dat de pagina dus niet verspringt
op het moment dat het lettertype binnen is.

Zonder dat pakket meldt de build bij elke draai:

```
[plugin laravel:fonts] Optimized font fallbacks require the optional
"fontaine" package.
```

Dat is een tip en geen fout. Wil je die optimalisatie niet, zet dan
`optimizedFallbacks: false` op de font in `vite.config.ts`; dan is de melding
ook weg. Wij hebben voor het pakket gekozen, omdat verspringende tekst
precies het soort slordigheid is waar de rest van dit project op let.

## Afbeeldingen

Er zijn drie plekken, en het verschil is wie het bestand erin zet.

| Plek                  | URL              | Waarvoor                                                    |
| --------------------- | ---------------- | ----------------------------------------------------------- |
| `public/images/`      | `/images/<naam>` | Vaste beelden die met de code meegaan: logo, og-afbeelding. |
| `public/flags/`       | `/flags/<naam>`  | De twee taalvlaggetjes, handgetekende SVG's.                |
| `storage/app/public/` | `/storage/<pad>` | Alles wat de klant later zelf uploadt.                      |

`public/flags/` staat apart omdat het geen beeldmateriaal van het merk is
maar onderdeel van de bediening. Zie
[vertalingen](vertalingen.md#de-vlaggetjes).

**`public/images/`** staat in git en gaat dus mee bij een deploy. Zet er wat
je zelf neerzet: het logo, een og-afbeelding, vaste sfeerbeelden. Verwijs
ernaar met een gewoon pad, `<img src="/images/logo.svg">` -- niet met een
import, want deze bestanden gaan bewust niet door Vite heen.

**`storage/app/public/`** is voor uploads. Dat is een aparte map buiten git,
zichtbaar via de symlink `public/storage` die `php artisan storage:link`
aanmaakt. Die link staat in `.gitignore` en moet op elke nieuwe omgeving
opnieuw worden gelegd -- dat staat in de deploystappen. Zonder die link geeft
elke geüploade afbeelding een 404, en dat is de eerste plek om te kijken als
dat gebeurt.

Houd die twee gescheiden. Zet je uploads in `public/`, dan staan
klantbestanden in git; zet je het logo in `storage/`, dan is het weg zodra
iemand de map leegmaakt.

### Wat er nu staat

| Bestand                                  | Waarvoor                                                |
| ---------------------------------------- | ------------------------------------------------------- |
| `public/favicon.ico`                     | Het tabblad-icoon                                       |
| `public/images/favicon.ico`              | Dezelfde, en dit is de versie waar de pagina naar wijst |
| `public/apple-touch-icon.png`            | 180x180, wat iOS ophaalt                                |
| `public/images/hero-achtergrond-*.webp`  | De hero-achtergrond in vier breedtes                    |
| `public/images/achtergrond-oog-3840.png` | De 4K-bron waar die varianten uit komen                 |
| `public/images/logo-breed.webp`          | Het liggende logo, voor het inlogscherm                 |
| `public/images/logo-merk.webp`           | Het vierkante merkteken, voor de zijbalk                |
| `public/images/og-afbeelding.jpg`        | Wat sociale media tonen bij een link                    |
| `public/images/*.png`                    | De aangeleverde bronbestanden                           |

**Zet nooit een aangeleverde PNG rechtstreeks op de pagina.** De 4K-bron van
de hero is 4,2 MB. Als WebP is de grootste variant 243 kB en de mobiele
41 kB. Op een telefoon met slecht bereik is dat het verschil tussen een site
die laadt en een bezoeker die wegklikt.

Er staat geen beeldbewerking in het project. Converteren doe je met een
eenmalig script; hoe dat is gegaan staat in de commit waarin de bestanden
zijn toegevoegd.

**Een foto wordt nooit scherper dan zijn bron.** Verkleinen mag, vergroten
nooit: dan rek je alleen op wat er al is. Voor een hero die de volle breedte
vult wil je een bron van minstens 2560 px, liever 3840 px. Krijg je iets
kleiners aangeleverd, vraag dan om een grotere versie in plaats van hem op te
blazen.

**Let op banding bij donkere verlopen.** Dat is het slechtste geval voor
WebP en deze foto is precies dat. Gemeten met PSNR lijkt kwaliteit 82 prima
(41,2 dB, ruim boven wat een oog ziet), maar PSNR meet banding slecht en je
oog niet. Vandaar 85 tot 90 in plaats van de gebruikelijke 80.

### Vier maten, de browser kiest

De hero gebruikt `srcset` met breedtes en `sizes="100vw"`:

| Bestand                      | Breedte | Kwaliteit | Grootte |
| ---------------------------- | ------- | --------- | ------- |
| `hero-achtergrond-960.webp`  | 960     | 88        | 41 kB   |
| `hero-achtergrond-1600.webp` | 1600    | 90        | 92 kB   |
| `hero-achtergrond-2560.webp` | 2560    | 88        | 158 kB  |
| `hero-achtergrond-3840.webp` | 3840    | 85        | 243 kB  |

De grootste heeft de laagste kwaliteit, en dat is geen slordigheid: op een
scherm dat 3840 px nodig heeft zijn de pixels zo klein dat compressieruis
niet opvalt, terwijl elke kilobyte wel telt.

Breedtes en niet `media`-regels, want zo telt de pixeldichtheid mee. Een
telefoon met een scherm van 375 px en drievoudige dichtheid heeft 1125 px
nodig en krijgt dus de 1600, niet een opgerekte 960.

Gebruik daarom `imageSrcset` op `SiteSection` en niet één vast bestand,
zodra een foto de volle breedte vult.

Bestandsnamen zijn kebab-case zonder spaties en hoofdletters. Een spatie in
een URL moet gecodeerd worden en dat gaat vroeg of laat ergens mis.

### Waarom de favicon er twee keer staat

`public/favicon.ico` is de plek waar browsers en bots het icoon uit zichzelf
zoeken, dus die blijft staan. De pagina verwijst echter naar de kopie in
`public/images/`, en dat is een omweg om Valet Linux heen.

In `/etc/nginx/sites-available/valet.conf` staat `root /` plus een exacte
location voor `/favicon.ico`. Die exacte match wint van de rewrite naar
`server.php`, dus nginx zoekt het bestand op de filesystem-root, vindt niets
en geeft 404. De `error_page 404` stuurt het verzoek alsnog naar Valet, die
het juiste bestand teruggeeft -- maar de status blijft 404, en een browser
weigert een favicon met een foutcode.

Je herkent het hieraan: `/favicon.ico` geeft 404 met de goede bytes erin,
terwijl `/apple-touch-icon.png` gewoon 200 geeft. Hetzelfde geldt voor
`/robots.txt`.

Op productie speelt dit niet: daar wijst de nginx-root naar `public/` en is
er geen aparte favicon-regel. Wil je het lokaal echt oplossen, haal dan die
twee `location =`-regels uit `valet.conf` en herstart nginx; dat geldt dan
voor al je Valet-sites.

## Mobiel is geen bijzaak

**De publieke site moet op een telefoon net zo goed zijn als op een
desktop.** Wie dit bedrijf opzoekt doet dat waarschijnlijk op zijn telefoon,
en dat is precies het moment waarop je hem wint of kwijtraakt. Een pagina die
"ook werkt" op mobiel is niet genoeg.

Wat dat concreet betekent bij elke openbare pagina:

- Controleer bij **375 px breed**, niet alleen in een verkleind
  browservenster. Dat is de smalste breedte die er nog echt toe doet.
- **Geen horizontale scroll.** Eén element dat te breed is verpest de hele
  pagina.
- **Aanraakvlakken van minstens 44 px.** Een link in een rij tekst is op een
  muis prima en met een duim niet.
- **Elke achtergrondfoto krijgt een mobiele variant.** Zie hierboven.
- **Contrast controleren boven een foto**, juist op mobiel: daar staat de
  tekst over de volle breedte en dus over het hele beeld.
- Grote koppen krijgen op mobiel een kleinere maat. `text-4xl sm:text-6xl`,
  niet één maat voor alles.

Voor het **portaal** ligt de lat lager: het moet bruikbaar zijn op een
telefoon -- niets onbereikbaar, geen horizontale scroll -- maar het is
gemaakt om achter een scherm te gebruiken. Een tabel met zes kolommen mag
daar horizontaal schuiven; op de publieke site niet.

## Routes vanuit JavaScript

We gebruiken [Wayfinder](https://github.com/laravel/wayfinder): die genereert
TypeScript-functies uit de Laravel-routes, zodat je `mail.index()` schrijft in
plaats van een hardgecodeerde `/admin/mail`. De gegenereerde bestanden staan in
`resources/js/routes` en `resources/js/actions` en staan **niet** in git: ze
worden bij elke build opnieuw gemaakt.

Draai je `php artisan wayfinder:generate` met de hand, gebruik dan
`--with-form`. Zonder die vlag verdwijnen de `.form()`-varianten en faalt
`npm run types:check`. In de normale werkstroom hoef je dit niet te doen --
`npm run dev` en `npm run build` regelen het via de Vite-plugin, die
`formVariants: true` heeft staan.

## Kopiëren naar het klembord

**Gebruik overal [`CopyButton.vue`](../../resources/js/components/CopyButton.vue).**
Op de publieke site en in het portaal, en zonder uitzondering: kopiëren
hoort zich altijd hetzelfde te gedragen, en de valkuilen hieronder wil je
niet per plek opnieuw oplossen.

```vue
<!-- Alleen een icoon, bijvoorbeeld naast een veld -->
<CopyButton :value="sleutel" label="Kopieer de sleutel" />

<!-- Met tekst ernaast, voor een knop die op zichzelf staat -->
<CopyButton
    :value="codes"
    label="Kopieer alle codes"
    show-label
    :reset-after="1500"
/>
```

### De terugval is geen luxe

```ts
if (navigator.clipboard && window.isSecureContext) { ... }
```

`navigator.clipboard` werkt alleen op **https of localhost**. Op een gewoon
http-adres -- zoals `.test` bij Valet -- bestaat hij soms wél maar weigert
hij, dus alleen op het bestaan toetsen is niet genoeg. Daarom die tweede
voorwaarde, en daarom de klassieke terugval: een `<textarea>` buiten beeld,
selecteren, `document.execCommand('copy')`, opruimen. Met `readonly` erbij,
zodat een telefoon geen toetsenbord opent.

Dit was een echte fout in dit project: de kopieerknoppen gebruikten
`useClipboard` van VueUse zonder `legacy`, en deden lokaal dus stilletjes
niets.

Het vinkje verschijnt pas ná een geslaagde belofte. Anders bevestig je iets
wat misschien niet is gebeurd.

### Waarom het vinkje soepel oogt

Beide iconen staan er permanent, over elkaar heen; er wordt **niets**
omgewisseld in de DOM. Alleen schaal en dekking wisselen:

- Het kopieericoon krimpt naar `scale-0`, het vinkje komt op vanaf
  `scale-75`. Het ene verdwijnt dus helemaal in zichzelf terwijl het andere
  maar een klein stukje opveert -- dat leest als vervangen in plaats van als
  kruisvervaging.
- **150ms eruit, 200ms erin.** Het vinkje komt iets luier binnen dan het
  kopieericoon weggaat, waardoor ze net even overlappen.
- **De knop verandert niet van grootte**, omdat het tweede icoon `absolute`
  staat. Zou je ze omwisselen, dan springt een rij knoppen bij elke klik.

### Toegankelijkheid

Beide iconen zijn `aria-hidden`; de betekenis zit in het `aria-label` op de
knop. Een schermlezer hoort dus één knop met een naam en niet twee iconen.
De wissel naar het vinkje wordt bewust **niet** omgeroepen: het is een
bevestiging van iets wat je zelf net deed, geen nieuws.

## Geen Link-headers voor voorgeladen assets

De middleware `AddLinkHeadersForPreloadedAssets` uit de starter kit staat
**uit**. Die zet elk voorgeladen bestand in één grote `Link:`-header, en die
groeit mee met het aantal chunks.

Dat ging hier daadwerkelijk mis: zodra de header over de headerbuffer van
nginx heen ging, gaf `/login` een **502** met `upstream sent too big header`
in de nginx-log. De applicatie zelf was in orde, de tests waren groen, en in
de browser zag je alleen een kapotte pagina.

Vite zet dezelfde voorladers al als `<link rel="modulepreload">` in de
pagina, dus we verliezen er niets mee. Zet hem niet terug zonder de buffers
van de webserver te verhogen -- en bedenk dan dat dat op elke omgeving
opnieuw moet.

Herken je het aan: een 502 op een pagina die het lokaal in de tests wél
doet, en `upstream sent too big header` in de log van de webserver.

## Styling

Tailwind CSS 4, geconfigureerd in `resources/css/app.css` met `@theme`. Er is
geen `tailwind.config.js` meer; kleuren en tokens staan in het CSS-bestand.
Donkere modus loopt via de `dark`-klasse op `<html>`, aangestuurd door
`useAppearance`.

De kleuren zelf staan in [huisstijl en kleuren](huisstijl-en-kleuren.md).
Schrijf in een component `bg-background`, `text-muted-foreground` of
`bg-brand-blue` -- nooit een hexcode. De publieke site zet zelf `dark` op
zijn wortel en staat dus altijd in het donkere thema, los van de voorkeur van
de bezoeker.

De UI-componenten in `resources/js/components/ui` komen van reka-ui. Pas die
bij voorkeur niet aan: maak een eigen component eromheen als je afwijkend
gedrag nodig hebt.

Eén uitzondering, en die is bewust: `ui/button` heeft er twee varianten bij
gekregen, `brand` en `brand-outline`. Een knop in de merkgradient is geen
ander gedrag maar dezelfde knop in een andere jas; een wikkelcomponent zou
alleen maar een tweede plek opleveren waar de maten uit elkaar kunnen lopen.

## Animatie

De animatielaag zit in [`resources/js/lib/motion.ts`](../../resources/js/lib/motion.ts):

```ts
prefersReducedMotion(): boolean
startSmoothScroll():              () => void   // geeft een opruimfunctie terug
revealOnScroll(selector, scope):  () => void
revealCards(kaarten, opties):     () => void
drawTimeline(rail, punten):       () => void
followYears(groepen):             () => void
countUp(tellers):                 () => void
scrollThrough(vak, opties):       () => void
trackScrollProgress(element):     () => void
heeftMuis(): boolean
splitReveal(selector, scope):     () => void
typMachine(kop, opties):          () => void
tekenRing(ring, opties):          () => void
kaartenBinnen(kaarten):           () => void
scrollNaar(doel, opties):         boolean
volgSecties(sleutels, opActief):  () => void
parallax(element, opties):        () => void
volgDeMuis(element):              () => void
kantelKaarten(kaarten, opties):   () => void
tekenPad(pad, punt, stappen):     () => void
```

Ze delen drie afspraken, en die gelden ook voor wat je er zelf bij zet:

1. **JavaScript raakt de opmaak niet aan.** Het zet een CSS-variabele of
   een attribuut; de kleuren en de maten staan in de stylesheet. Zo blijft
   de huisstijl op één plek.
2. **Elke functie geeft een opruimfunctie terug**, en die hoort in
   `onBeforeUnmount`. Zie [Opruimen is verplicht](#opruimen-is-verplicht).
3. **Bij `prefers-reduced-motion` staat de eindtoestand er meteen**, en
   wordt de animatie niet alleen overgeslagen. Anders blijft de inhoud
   onzichtbaar wachten op iets dat nooit komt.

`countUp` laat getallen omhoogtellen zodra ze in beeld komen; het
eindgetal staat al in de HTML en komt uit `data-tot`, zodat er ook zonder
JavaScript iets goeds staat. `followYears` markeert het jaartal van de
groep waar je doorheen scrolt. Allebei worden ze gebruikt door de
[tijdlijn met ervaringen](modules/ervaring.md#de-tijdlijn-op-de-site).

`scrollThrough` laat de bezoeker met zijn muiswiel door een reeks
bladeren, maar **alleen binnen één vak**. De pagina blijft ondertussen
staan waar hij staat. Dat is iets anders dan een blok vastzetten terwijl
de pagina eronder doorloopt, en de reden is de afbakening: bij deze vorm
is het vak ook precies het gebied waar het gebeurt, en niet een lijst die
meebeweegt terwijl je er ver vandaan scrolt.

Vier dingen die daarbij makkelijk stukgaan:

- **Je moet eruit kunnen.** `onVerplaats` geeft terug of hij de beweging
  heeft gebruikt; zo niet -- je bent aan het begin of het eind -- dan
  laten we de gebeurtenis los en scrolt de pagina verder. Zonder die
  uitweg zit de bezoeker vast in het vak.
- **Het vak moet het midden van het scherm beslaan** voordat er iets
  gebeurt. Anders pakt het je scroll af terwijl je er alleen maar
  langsschuift.
- **`data-lenis-prevent` hoort op het element**, anders neemt Lenis het
  wiel als eerste af.

Dat laatste geldt breder dan deze helper: **alles op de publieke site dat
zelf moet kunnen schuiven, heeft `data-lenis-prevent` nodig.** Lenis vangt
het muiswiel af om de pagina soepel te laten lopen, ook als je muis boven
een venster of een eigen schuifgebied hangt -- en dan scrolt de pagina
erachter in plaats van wat je voor je hebt. Het venster met één ervaring
liep daar tegenaan: een lange beschrijving was niet te scrollen. Het
portaal heeft er geen last van, want daar draait Lenis niet.

- **Een vak dat alleen op de muis reageert is stuk** voor wie geen muis
  gebruikt. Geef het `tabindex` en handel de pijltjestoetsen af; de helper
  doet het wiel en de vinger, de rest hoort bij het onderdeel zelf.

### De voortgangsbalk

`trackScrollProgress` voedt de balk bovenaan de publieke site
([`ScrollProgress.vue`](../../resources/js/components/site/ScrollProgress.vue)).
De techniek is de moeite van het onthouden waard, want hij is overal
herbruikbaar:

De baan ligt vast bovenaan; het kind vult hem volledig en wordt
samengedrukt met `transform: scaleX(var(--scroll-progress))` vanaf
`transform-origin: left`. JavaScript raakt de opmaak nooit aan en zet alleen
dat ene getal.

**Schalen en niet `width` aanpassen.** `scaleX` draait op de GPU en kost
geen herberekening van de pagina. Met `width` rekent de browser bij elke
scrollstap de hele pagina door, en dan gaat het schokken -- precies op het
moment dat het vloeiend moet voelen.

Twee dingen die dit project toevoegt aan het simpelste recept:

- **Schrijven gebeurt in een animation frame.** Een scroll-event kan vaker
  vuren dan het scherm ververst; zonder die bundeling doe je meerdere keren
  per beeldje hetzelfde werk.
- **De scrollbare hoogte wordt gemeten en onthouden**, niet bij elke
  scrollstap opgevraagd. Dat opvragen dwingt de browser juist wél de opmaak
  door te rekenen. Een `ResizeObserver` houdt de maat bij als de inhoud
  verandert, bijvoorbeeld wanneer een afbeelding binnenkomt.

De waarde wordt afgekapt tussen 0 en 1: op een telefoon kun je voorbij het
begin en het einde doorveren, en dan schiet de balk anders door.

### Lenis en GSAP moeten samenwerken

Lenis neemt het scrollen over van de browser. ScrollTrigger van GSAP luistert
standaard naar de scroll van de browser. Koppel je die twee niet, dan lopen je
animaties merkbaar achter op wat de bezoeker doet. Daarom laat `motion.ts`
Lenis meelopen op de GSAP-ticker en roept het `ScrollTrigger.update()` aan bij
elke scroll. Niet weghalen.

### Opruimen is verplicht

Inertia vervangt de pagina zonder de browser te herladen. Een Lenis-instantie
of ScrollTrigger die je niet opruimt blijft draaien op een pagina die er niet
meer is. Elke functie in `motion.ts` geeft daarom een opruimfunctie terug; roep
die aan in `onBeforeUnmount`. Zie `PublicLayout.vue` voor het patroon.

Voor de publieke site hoef je dit meestal niet zelf te doen: scrollen en de
reveals zitten al in de layout. Alleen een animatie die bij één pagina hoort
-- zoals de binnenkomst van de hero in `Welcome.vue` -- zet je in die pagina,
en dan ruim je hem daar ook op.

### Een element dat al in beeld staat, komt meteen op

Dit is de stilste fout die dit project heeft gehad, en hij is twee keer
gemaakt voordat hij goed was opgelost. Lees dit voordat je een animatie
toevoegt die iets zichtbaar maakt.

Bijna elke binnenkomst werkt zo: het element begint op `opacity: 0` — die
klasse staat in het sjabloon — en een scroll-trigger haalt het op zodra je
erlangs komt. Dat klopt zolang het element **onder de vouw** wordt
aangemaakt, en dat is bij het laden van een pagina altijd zo.

Wordt het aangemaakt terwijl je er al voorbij bent, dan komt die trigger
nooit meer langs. Het element staat er wel, maar onzichtbaar. Je ziet een
gat, en na verversen is alles er ineens. Twee keer gebeurd:

- **Bij het bladeren** door de diensten: de kaarten van de tweede pagina
  verschijnen midden in beeld.
- **Bij het wisselen van taal**: `<main>` heeft de taal als sleutel, dus
  de hele pagina wordt opnieuw opgebouwd — met alle koppen op
  doorzichtigheid nul, terwijl je halverwege de pagina staat.

Er was eerst per geval een `direct`-optie voor, die de aanroeper moest
meegeven. Dat is precies de soort oplossing die je de derde keer vergeet.

**Nu vraagt elke functie het zelf**, met `alInBeeld()` uit `motion.ts`:
staat het element al in beeld, dan speelt de animatie meteen; staat het
eronder, dan hangt hij aan de scroll. `revealOnScroll`, `splitReveal`,
`revealCards`, `kaartenBinnen` en `countUp` doen dat allemaal. Wie een
nieuwe animatie toevoegt hoeft er niets voor te doen — maar als je er een
schrijft die iets van onzichtbaar naar zichtbaar brengt, stel die vraag
dan ook.

### prefers-reduced-motion

Alle beweging staat uit wanneer de bezoeker daarom vraagt. Dat is geen
vriendelijkheid maar noodzaak: voor mensen met bewegingsklachten is een
vloeiend scrollende pagina onbruikbaar tot misselijkmakend.

Let op de valkuil: elementen die met `opacity: 0` beginnen en pas door een
animatie zichtbaar worden, blijven onzichtbaar als je de animatie simpelweg
overslaat. `motion.ts` zet ze daarom direct op hun eindtoestand. Doe dat ook in
eigen animaties.

**Eén uitzondering: de voortgangsbalk blijft werken.** Die beweegt niet uit
zichzelf maar volgt de beweging die de bezoeker zelf maakt, en juist wie
langzaam en bewust scrollt heeft er iets aan. De regel gaat over animatie
die zonder jouw toedoen begint; dit is een weergave van je eigen positie.

Hetzelfde geldt voor de aurora achter het inlogscherm, maar daar andersom:
die beweegt wél uit zichzelf en staat dus stil bij reduced motion. Zie
[huisstijl en kleuren](huisstijl-en-kleuren.md).

## De landing als scène

De publieke site heeft drie momenten die bedoeld zijn om op te vallen, en
daaronder een laag die je niet hoort te merken. Die verhouding is de hele
opzet: drie keer iets, en verder rust. Een pagina waarop alles beweegt is
een pagina waarop niets opvalt.

### Welke plugins we gebruiken, en welke niet

Sinds GSAP 3.13 zitten de vroegere Club-plugins in het pakket. Ze worden
allemaal geregistreerd in [`motion.ts`](../../resources/js/lib/motion.ts)
en nergens anders -- **dat is geen netheid maar noodzaak**. Dit bestand is
het enige dat `gsap` importeert, en daardoor houdt de bundler alles in de
chunk van de publieke site. Importeer je GSAP in een component dat het
portaal ook laadt, dan sleep je er zestig kilobyte in die daar niets doet.

| Plugin        | Waarvoor                                       |
| ------------- | ---------------------------------------------- |
| ScrollTrigger | Alles wat op scrollen reageert                 |
| SplitText     | Koppen die per regel achter een masker opkomen |
| DrawSVG       | De lijn boven de werkwijze die zich tekent     |

**ScrollSmoother nemen we niet**, hoewel hij nu gratis is; zie
[besluit 017](../decisions/README.md). Samen kosten de drie erbij 13 kB
gzip.

**MotionPath stond hier ook en is eruit.** Hij zette het lichtpuntje op
de werkwijzelijn, maar meet in schermpixels terwijl die SVG met
`preserveAspectRatio="none"` ongelijk wordt uitgerekt -- en dan loopt het
puntje scheef van de lijn af, erger naarmate het scherm breder is.
`getPointAtLength` op het pad zelf geeft een punt in dezelfde coördinaten
als de lijn, en die rekt dus precies mee.

### De hero is een scène van drie lagen

[`HeroSection.vue`](../../resources/js/components/site/sections/HeroSection.vue)
bestaat uit een achtergrond, een rond portret en een tekstblok, en die drie
bewegen los van elkaar. Bij het scrollen loopt de tekst het hardst weg en
blijft de achtergrond het meest achter; daardoor lijkt er diepte te zitten
tussen dingen die allemaal even plat zijn.

**Op een telefoon staat de titel bovenaan en het portret eronder.** Dat
was eerst andersom -- gezicht eerst, want dat herken je het snelst. In de
praktijk duwde die cirkel de kop naar beneden en viel hij half buiten
beeld, en dan is het eerste wat je leest een afgekapte zin. De titel is
waarvoor iemand komt; het portret staat er een schermlengte later nog
steeds.

**De twee kolommen beginnen bij 48rem en niet bij 64rem.** Daar zat een
gat: op een tablet toonde de kop al zijn volledige menu terwijl de hero
nog in de smalle stand stond, met een cirkel over de volle breedte. Dat
leest als een vergrote telefoon in plaats van een eigen ontwerp.

**Houd het verschil klein.** Negentig pixels voor het portret en zestig de
andere kant op voor de tekst is genoeg. Parallax die opvalt is parallax die
misselijk maakt, en op een lange pagina merk je dat pas als je hem echt
doorscrolt.

#### De kop wordt ingetikt

`typMachine()` splitst de kop in losse tekens en laat die één voor één
aanspringen. **Niet door de tekst te laten groeien**, wat de voor de hand
liggende manier is: bij elk woord dat erbij komt breekt de regel opnieuw en
verschuift alles eronder. Met SplitText staat de ruimte er al en verandert
alleen de doorzichtigheid.

De tekens springen aan (`duration: 0.01`) in plaats van te vervagen. Een
letter die in twee tienden vervaagt leest als "er verschijnt tekst"; een
letter die er ineens staat leest als getypt.

`autoSplit` staat hier bewust **uit**. Hersplitsen midden in het typen zou
de animatie opnieuw laten beginnen, en een kop die twee keer wordt ingetikt
lijkt kapot. De prijs is dat de regelafbreking blijft staan zoals hij bij
het laden was; voor één kop van een paar woorden is dat de betere ruil.

#### Het ronde portret

Het medaillon is **één samengesteld beeld**: het oog-embleem uit de
huisstijl met het uitgeknipte portret erop, vierkant uitgesneden en als
`persoon-medaillon-{320,480,640,960}.webp` weggeschreven. Twee losse lagen
in CSS zou ook kunnen, maar dan verschuift de uitlijning per schermbreedte
en staat het hoofd de ene keer wel en de andere keer niet voor het oog.

De ring eromheen is een SVG-cirkel en geen `border`, want een rand kun je
niet laten tekenen. `tekenRing()` trekt hem in ruim een seconde rond met
DrawSVG.

Het medaillon is vierkant, dus zijn breedte is meteen zijn hoogte. De maat
is daarom `min(22rem, 46vh)`: op een laptop met weinig verticale ruimte zou
een cirkel van 22 rem de knoppen onder de vouw duwen, en dat is precies
waarvoor iemand op deze pagina komt.

De bronbestanden (`PersoonFoto.png` met doorzichtige achtergrond en
`achtergrond-oog-3840.png`) blijven in de map staan, maar komen niet op de
website.

#### Op een telefoon een visitekaartje, geen cirkel

De grote cirkel stond daar onder de knoppen en kostte een kwart
schermhoogte voor iets wat je pas zag als je er al voorbij was. Onder de
tabletgrens staat er nu een strook: het portret klein en rond links, de
naam en de functie ernaast.

Dat scheelt niet alleen hoogte. **De foto krijgt er een reden door om er
te staan**: als losse cirkel was het een plaatje, naast een naam is het
wie het werk doet -- en dat is precies wat een eenmanszaak onderscheidt
van een anoniem bureau.

Het opschrift boven de titel verdwijnt op die breedte, want de functie
staat dan in het kaartje; anders staat hetzelfde er twee keer.

`NAAM` in
[`HeroSection`](../../resources/js/components/site/sections/HeroSection.vue)
staat leeg tot de klant hem doorgeeft. Een naam bij de foto van een echt
persoon verzin je niet, en met een lege naam toont het kaartje netjes
alleen de foto en de functie. Zodra de kop een module wordt komen allebei
uit de database.

#### Waarom er op een telefoon geen parallax en geen draaiende ring is

Allebei uit, en om dezelfde reden. **Een element dat permanent beweegt
staat permanent in een bewegende compositielaag, en die rastert de
browser in lagere resolutie**; de scherpte komt pas terug als hij
stilstaat. De ring draait eindeloos en de parallax loopt met elke scroll
mee, dus de laag kwam nooit tot rust -- en omdat het portret in diezelfde
laag zit, zag je een foto die de hele tijd wazig was.

Op een desktop-GPU valt dat weg. Op een telefoon niet, en daar levert de
parallax op dat formaat ook nauwelijks diepte op. Allebei staan ze nu in
een `gsap.matchMedia` vanaf 50rem, zodat het meegaat als je je telefoon
draait.

**Dit is een val die terugkomt.** Zet je ooit een animatie zonder einde
op iets waar een foto of scherpe tekst in zit, reken er dan op dat het
op een telefoon zachter wordt.

### De intro bij binnenkomst

Een navy laag met de merkgloed en het logo, acht tienden van een seconde,
één keer per bezoek. Zie
[`SiteIntro.vue`](../../resources/js/components/site/SiteIntro.vue).

**Het is geen laadscherm en dat verschil is het hele punt.** De hero staat
er al volledig onder; dit is een gordijn dat opengaat voor iets wat er al
is. Gaat de animatie stuk, dan staat de site eronder klaar.

Wanneer hij niet speelt staat in
[`intro.ts`](../../resources/js/lib/intro.ts): bij `prefers-reduced-motion`,
als hij deze sessie al gezien is, of als de pagina zelf al traag binnenkwam
-- dan is acht tienden seconde erbovenop geen entree meer maar een straf.

**Die beslissing is synchroon en wordt één keer genomen**, en dat is met
opzet geen belofte waar de hero op wacht. Zou de introlaag om welke reden
dan ook niet verschijnen, dan wacht de hero anders eeuwig en blijft zijn
tekst op doorzichtigheid nul staan. Onzichtbare inhoud is een ergere fout
dan een gemiste animatie, dus beide kanten stellen de vraag los van elkaar
en krijgen gegarandeerd hetzelfde antwoord.

### Twee soorten reveals

`[data-reveal]` schuift een heel blok omhoog. `[data-split]` tilt tekst
regel voor regel achter een masker op. Allebei worden ze gescand door
[`PublicLayout.vue`](../../resources/js/layouts/PublicLayout.vue), ook
opnieuw na een Inertia-navigatie.

Gebruik `data-split` alleen op een kop. SplitText moet meten, en bij elke
maatverandering en elk later geladen lettertype opnieuw; dat is werk dat je
op drie woorden doet en niet op elke alinea.

Drie dingen bij SplitText:

- **`mask: 'lines'` doet het maskeren.** Zelf divs met `overflow: hidden`
  eromheen bouwen werkt ook, tot de tekst over andere regels verdeeld wordt.
- **De animatie hoort in `onSplit`**, niet erbuiten -- tenminste, zolang
  `autoSplit` aanstaat. Die hersplitst bij een andere breedte, en dan
  draait `onSplit` opnieuw. Zet je de animatie ernaast, dan animeert hij na
  een hersplitsing elementen die niet meer bestaan.
- **Haal het `aria-label` er niet af** dat SplitText zelf op het element
  zet. Zonder dat leest een schermlezer de zin regel voor regel voor.

**Een element met `data-split` of `data-reveal` staat op `opacity-0` in
zijn klassen.** Buiten `PublicLayout` wordt daar niets op gescand, en dan
blijft het dus onzichtbaar. Gebruik ze alleen binnen de publieke site.

### De dienstenkaarten komen één voor één binnen

`kaartenBinnen()` laat ze van onderen opkomen, iets te klein en licht van
je af gekanteld, en dan zetten ze zich recht. Die kanteling is wat het meer
maakt dan een fade: de kaart lijkt naar je toe te draaien.

**Hier zat eerst een vastgezette sectie waarin de kaarten uit een stapel
openwaaierden, en die is eruit gehaald.** Dat is een les die het opschrijven
waard is. Op papier was het het mooiste effect van de drie; in de praktijk
deed het twee dingen die je alleen ziet als je de pagina echt opent:

1. **De pin duwde de sectiekop achter de plakkende balk.** Vastzetten op
   `top top` betekent letterlijk de bovenkant van het scherm, en daar zit
   de navigatie al. Een offset repareert dat, maar dan is de sectie niet
   meer gecentreerd en klopt het opnieuw niet.
2. **Het spacer-vak liet een schermhoogte aan lege navy achter.**
   ScrollTrigger houdt bij een pin de ruimte vast die het element innam, en
   bij een korte sectie is dat een gat onder de kaarten waar niets gebeurt.

Wil je ooit alsnog pinnen op deze site: reken erop dat je de plakkende kop
en de hoogte van de sectie allebei moet oplossen, en dat het op een
telefoon sowieso uit moet.

**De kaart draagt daarom geen `data-reveal` meer.** Twee dingen die om
beurten dezelfde doorzichtigheid schrijven, laten hem knipperen. Zet je een
`FeatureCard` ergens neer zonder `kaartenBinnen()`, zorg dan dat hij
zichtbaar wordt -- `opacity-0` blijft anders staan.

### De navigatie volgt de indeling van de klant

Het menu in de kop staat **niet** in
[`SiteHeader.vue`](../../resources/js/components/site/SiteHeader.vue). Het
komt als `navigation` uit `HomeController`, uit dezelfde bron als de
onderdelen op de pagina zelf.

Dat is geen netheid maar een reparatie. Het stond er hardgecodeerd, en liep
dus niet mee met wat de klant in het portaal doet:

| Wat de klant doet       | Wat er misging                                                                                    |
| ----------------------- | ------------------------------------------------------------------------------------------------- |
| Niets                   | **Ervaring stond niet in het menu.** Die module was er wel, maar niemand had de lijst bijgewerkt. |
| Een onderdeel uitzetten | De link bleef staan en wees naar een anker dat niet meer bestond. Klikken deed niets.             |
| De volgorde omgooien    | Het menu bleef in de oude volgorde staan.                                                         |

Bouw je een module bij, dan verschijnt hij nu vanzelf in het menu zodra hij
op de pagina staat -- er is niets om te vergeten.
[`SiteNavigationTest`](../../tests/Feature/Website/SiteNavigationTest.php)
vergelijkt de twee lijsten en valt om zodra ze uiteenlopen.

**De labels komen van de server**, uit `PageSectionKey::label()`, want ze
zijn vertaald. Een tabel met sleutels naar labels in de frontend zou een
tweede bron zijn die stilletjes kan gaan afwijken.

**Op een pagina zonder `navigation` -- een foutpagina -- toont de kop geen
ankers.** Dat is met opzet: een link naar een onderdeel dat op deze pagina
niet bestaat, is een link die niets doet.

#### Wat als er tien modules zijn

De balk toont er hoogstens vijf los; de rest gaat achter **"Meer"**. Nu
zijn het er vier, dus dat lijstje bestaat niet eens -- het staat er voor
straks, want elke module die de klant erbij krijgt komt vanzelf in dit
menu, en bij acht loopt de balk over de taalknop en de contactknop heen.

Vijf en niet drie: onder de vijf is een uitklaplijstje meer werk voor de
bezoeker dan een link, en boven de zes wordt de balk te vol. Staat er iets
uit dat lijstje aan, dan kleurt de knop "Meer" mee; de schuivende
markering blijft eronder weg, want die hoort bij een onderdeel en "Meer"
is er geen.

Op mobiel is dit geen vraag: daar is het een kolom, en die kan gewoon
doorlopen.

Het merkteken staat links naast de naam, en op een scherm smaller dan
26 rem blijft alleen dat teken over. Daar heeft het menu de ruimte nodig,
en het teken alleen is herkenbaar genoeg -- het is hetzelfde beeldmerk als
de favicon.

#### Soepel naar een onderdeel

`scrollNaar()` gaat via Lenis en niet via `window.scrollTo` of
ScrollToPlugin. Lenis houdt zijn eigen scrollpositie bij en duwt die elk
beeldje naar de pagina; trekt er iets anders tegelijk aan, dan vechten de
twee en trilt het beeld.

Zeven tienden van een seconde, met een verschuiving van 72 pixels voor de
plakkende kop. Bij `prefers-reduced-motion` een directe sprong.

Drie dingen die hier bewust zo zijn:

- **Het anker blijft in de `href` staan.** De klik wordt alleen
  onderschept als het doel er echt is. Zo werkt de link ook zonder
  JavaScript, kun je hem kopiëren, en ziet een zoekmachine waar hij heen
  gaat.
- **`replaceState` en niet `pushState`.** Anders vult de terugknop zich met
  een rij ankers en kom je nooit meer terug op de pagina waar je vandaan
  kwam.
- **Ctrl- en middelklik gaan er niet doorheen.** Die horen een nieuw
  tabblad te openen, en dat is niet aan ons.

#### Waarom de kop altijd een vervaging draagt

De achtergrondvervaging van de kop staat er **altijd** op, ook bovenaan de
pagina waar je hem niet ziet. Dat lijkt verspilling en is het niet.

`backdrop-filter` laat de browser een eigen compositielaag maken. Zat die
in de klasse die bij het scrollen omklapt -- en dat zat hij -- dan wordt
die laag telkens opgebouwd en weer weggegooid, en dat ziet eruit als
trillende tekst in het menu. Hetzelfde geldt voor de onderrand: een rand
die verschijnt is een pixel die erbij komt, en dan verschuift alles
eronder. Nu staan allebei er vanaf het begin en verandert alleen hun
kleur.

Daar komt bij dat de omslag **twee grenzen** heeft: aan boven de twaalf
pixels, uit onder de vier. Met één grens klapt hij heen en weer zodra je
daar in de buurt blijft hangen -- bij het uitveren van een telefoon, of
met een muis die één regel per stap scrollt.

#### De omslag naar de bredere vorm ligt op 50rem

Niet op Tailwinds eigen `md` (48rem = 768 pixels), en dat getal is gekozen
op een apparaat: **een iPad mini staat rechtop op precies 768 pixels.** Met
`md` viel hij er net bovenop en kreeg hij het volledige menu plus de
tweekoloms hero op een breedte waar dat niet past. Nu valt hij er nét
onder en krijgt hij de telefoonvorm; een iPad Air (820) en alles daarboven
krijgt de bredere vorm.

De grens staat als `--breakpoint-tablet` in het thema, dus gebruik
`tablet:` in plaats van `md:` voor alles op de publieke site dat met die
omslag te maken heeft. Er zijn twee plekken waar hij óók als mediaquery
staat -- de hero in `app.css` en de `matchMedia` in
[`ErvaringLijst`](../../resources/js/components/site/sections/ErvaringLijst.vue).
Verander je er één, verander dan allebei: anders krijgt één schermbreedte
het menu van de ene vorm en de inhoud van de andere.

#### De markering schaalt, hij wordt niet breder

De streep onder het actieve menu-item animeerde zijn `width`. Dat is een
eigenschap waarvoor de browser de opmaak opnieuw doorrekent -- elk beeldje
van de 420 milliseconden dat hij onderweg is, en hij is onderweg telkens
als je een nieuw onderdeel in scrolt.

Op een ruime kop merk je daar niets van. Op een krappe -- een tablet, waar
het menu, de taalknop en de contactknop elkaar verdringen -- gaat de tekst
ernaast zichtbaar trillen. Dat is een melding die lang onopgelost bleef,
omdat het op een breed scherm gewoon goed werkt.

Nu is de streep één pixel breed en rekt `scale` hem op. Schalen en
verschuiven gebeuren allebei op de GPU en raken de opmaak niet aan.
`--streep-w` is daarom een **getal zonder eenheid**: het is een
vermenigvuldiging en geen maat.

**De les is algemener dan deze streep.** Animeer nooit `width`, `height`,
`top` of `left` als het ook met `transform` kan. Dat het er goed uitziet
op jouw scherm zegt niets: het gaat pas mis waar de opmaak het krapst is.

#### En hij wordt onder het wóórd gemeten, telkens opnieuw

Dezelfde streep, en opnieuw kwam de melding van een tablet: hij stond niet
recht onder het item. Twee oorzaken, allebei onzichtbaar op een breed
scherm.

**Hij werd om de knop gemeten en niet om het woord.** Een menulink heeft
ruimte om zich heen zodat je hem met een vinger kunt raken, en die ruimte
werd mee onderstreept -- links en rechts liep de streep een stuk door
onder het niets. Staan de items ver uit elkaar, dan valt dat weg tegen de
witruimte; staan ze dicht op elkaar, dan zie je dat de streep niet onder
het woord hoort. Het label zit nu in een eigen `<span>` met een
`data-woord`, en dat span wordt gemeten.

**En hij werd gemeten met `offsetLeft` en `offsetWidth`**, die allebei
afronden op hele pixels. De balk staat zelden op een hele pixel: hij zit
tussen een merk en een knoppenrij die allebei zo breed zijn als hun tekst.
Nu is het `getBoundingClientRect()`, dat wél breuken geeft.

Daarbovenop meet hij opnieuw zodra er iets verandert, en dat is meer dan
alleen het venster dat van maat gaat. Een `ResizeObserver` kijkt naar de
balk _en_ naar de rij eromheen -- het merk ernaast krimpt op een smal
scherm tot alleen het teken, waardoor de balk verschuift zonder zelf van
maat te veranderen -- en na `document.fonts.ready` wordt er nog een keer
gemeten, want het lettertype maakt elk woord een paar pixels anders breed
zodra het binnen is.

#### Eerst het menu dicht, dan pas scrollen

Klik je in het uitklapmenu op een onderdeel, dan gaat het menu dicht én
begint de scroll. Tegelijk doen is precies verkeerd: het paneel verdwijnt
nog uit de pagina en de scrollvergrendeling gaat eraf, dus de hele pagina
verspringt onderweg en de plek waar je heen ging schuift mee. Op een
telefoon leest dat als een haperende, trage sprong.

Daarom sluit de kop eerst het menu, wacht twee frames -- de eerste haalt
het paneel uit de DOM, de tweede laat de browser de nieuwe hoogte
doorrekenen -- en scrolt pas daarna.

#### Waar je bent

`volgSecties()` zet per onderdeel een ScrollTrigger en meldt welke actief
is; de markering onder het menu schuift ernaartoe. De grens ligt op een
derde van boven en niet op het midden: je leest van boven naar beneden, dus
je bent met de bovenkant van een sectie bezig terwijl het midden van het
scherm nog bij de vorige hoort.

**Het houdt een verzameling bij en geen enkele waarde.** Op het moment dat
je van het ene onderdeel in het andere scrolt vuren twee triggers vlak na
elkaar -- de ene uit, de andere aan -- en in welke volgorde dat gebeurt
ligt niet vast. Zou de uit-melding als laatste komen, dan blijft het menu
leeg terwijl je midden in een onderdeel zit.

**Verandert de lijst met onderdelen, dan moeten de triggers opnieuw.** Een
oude reeks wijst naar elementen die er niet meer zijn, en dan licht er
nooit meer iets op. De kop kijkt daarop met een `watch`.

#### Het uitklapmenu op mobiel

Vouwt open met GSAP (`height: 'auto'`, dus zonder zelf te meten) en de
regels komen er met een stagger achteraan. Verder drie dingen die er eerst
niet waren: het gaat dicht met Escape, het gaat dicht bij **elke** link --
ook Dashboard, dat eerder alleen bleef staan -- en de pagina eronder staat
stil zolang het open is. Zonder dat laatste scrol je achter een menu dat
het hele scherm vult, en sta je na het sluiten ergens anders dan waar je
was.

Op mobiel is er geen schuivende markering maar een streepje links van de
actieve regel. Een glijdende onderstreping werkt niet in een kolom, en een
tweede mechaniek bouwen voor hetzelfde idee is meer code dan het waard is.

### De lijn boven de werkwijze

Een SVG-pad dat zich tekent terwijl je scrolt, met een lichtpuntje dat
erlangs reist en stappen die oplichten zodra het punt ze passeert. Zie
`tekenPad()`.

Twee dingen:

- **De stappen worden gemeten, niet geteld.** Waar stap drie oplicht hangt
  af van waar hij staat. Zou je delen door het aantal, dan klopt het alleen
  bij gelijke afstanden -- en bij een laatste stap met een langere tekst al
  niet meer.
- **De lijn staat alleen vanaf 64rem.** Daaronder staan de stappen onder
  elkaar en zou een horizontale lijn nergens meer langs lopen.

### Wat op de muis reageert

Twee dingen, allebei alleen op een apparaat met een fijne aanwijzer
(`heeftMuis()`): de gloed achter de hero verschuift met de cursor mee, en
de dienstenkaarten kantelen maximaal vier graden met een glans die
meeloopt.

**JavaScript zet alleen variabelen**, `--muis-x`, `--muis-y`, `--glans-x`
en `--glans-y`. Wat daarmee gebeurt staat in CSS, zodat de huisstijl op één
plek blijft.

Twee dingen die hier al een keer fout zijn gegaan, allebei met hetzelfde
symptoom -- "ik zie niks bij mijn cursor":

- **GSAP animeerde die variabelen, en dat deed niets.** Een CSS-variabele
  heeft geen betrouwbare beginwaarde om vanaf te rekenen, dus `quickTo`
  gaf geen fout maar ook geen beweging. Nu schrijft `volgDeMuis()` de
  waarde rechtstreeks, één keer per beeldje, en doet een `transition` in
  CSS het naijlen. Eén regel, en hij werkt gegarandeerd.
- **De verschuiving was te klein.** Twintig pixels op een gloed die zwaar
  geblurd is en het halve scherm beslaat, is letterlijk niet te zien. Het
  staat nu op zestig. Bij een effect dat over zo'n groot vlak loopt is
  "subtiel" al snel "afwezig".

### Reduced motion, en waarom het hier geen bijzaak is

Elk effect hierboven heeft een uit-stand waarin de inhoud gewoon compleet
en meteen zichtbaar is -- niet "de animatie wordt overgeslagen", want dan
blijft een element op doorzichtigheid nul hangen en is er inhoud weg.

Twee dingen blijven bewust wél staan: de voortgangsbalk bovenaan, want die
volgt de beweging die de bezoeker zelf maakt, en de kleurverandering van
een stap die aan de beurt is, want dat is informatie en geen beweging.

## Eén variabele, en JavaScript dat hem alleen verschuift

De [statistieken](modules/statistieken.md) zijn gebouwd op een aanpak die
het onthouden waard is, en die hier nog nergens anders wordt gebruikt.

**Het zware werk staat in CSS.** De balk schaalt op de variabele
`--vulling` (0 tot 1), en de ring is een `stroke-dashoffset` die op
diezelfde variabele rekent -- met `pathLength="100"` op de cirkel, zodat
de omtrek niet uitgerekend hoeft te worden. JavaScript zet alleen die ene
variabele en schrijft het getal.

Dat levert iets op wat de andere animaties hier niet hebben: **zonder
JavaScript staat het blok meteen goed.** `--vulling` valt in `app.css`
terug op 1 en het getal staat als tekst in de HTML, dus het enige wat
JavaScript doet is het wégnemen en dan opbouwen.

De eerste opzet tekende de ring met DrawSVG, en toen viel meteen op waar
dat misgaat: in het voorbeeldvenster van het beheerscherm, waar niets
scrollt, stond de ring altijd helemaal vol.

> **De regel die daaruit volgt:** kan een effect met een CSS-variabele die
> JavaScript alleen maar verschuift, doe het dan zo. Dan werkt het ook in
> een formulier, in een voorbeeld, en bij iemand die je script niet
> binnenkrijgt.

### En een les over `scrub`

Dat blok hing eerst volledig aan de scrollpositie (`scrub`) en zette
zichzelf daarbij vast aan het scherm (`pin`). Naar beneden scrollend zag
dat er goed uit: je stuurde de animatie zelf aan.

**Het is er weer uit gehaald, en de reden is het onthouden waard.** Bij
terugscrollen liepen alle percentages terug. Je ziet dan getallen
veranderen die niets met je bezoek te maken hebben, en het blok voelt
veel langer dan het is -- je bent er al voorbij en het beweegt nog. Het
vastzetten maakte dat erger: dat eet een hele schermhoogte scroll op
voordat de pagina verdergaat.

> **Gebruik `scrub` alleen voor iets dat een positie uitdrukt en geen
> waarde.** De voortgangsbalk bovenaan mag meelopen: die zegt "hier ben
> je". Een percentage zegt "dit is het", en dat hoort niet te veranderen
> omdat iemand terugscrolt.

### En een rustanimatie voor daarna

Een animatie die één keer speelt laat iets achter wat helemaal stilstaat,
en dat viel de eigenaar op: hij vroeg om "een kleine subtiele animatie voor
als alles al gebeurd is". Bij de statistieken is dat een lichtpunt dat na
het vullen rondjes blijft lopen -- over de boog van een ring, over het
gevulde stuk van een balk, langs de randlijn van een teller.

Twee dingen daaraan zijn algemener dan dit ene blok:

- **Neem een beweging die er al was en laat die doorgaan.** Het lichtpunt
  liep tijdens het vullen met de kop van de balk mee; daarna doet het
  hetzelfde, langzamer en zachter. Een nieuw effect erbij verzinnen zou een
  tweede taal in hetzelfde blok zijn.
- **Eén tegelijk, niet allemaal.** De onderdelen zijn gelijkmatig over één
  ronde verdeeld, dus het loopt als een vuurtoren rond in plaats van dat
  alles samen knippert. Bij meer onderdelen wordt de ronde langer en niet de
  tussenpoos korter.

En het staat stil zolang je het niet ziet: een `ScrollTrigger` met
`onToggle` zet de tijdlijn op pauze als het blok uit beeld is. Een
eindeloze tijdlijn op iets dat drie schermen hoger staat kost accu en
levert niets op. Zie `rustOp()` in
[`motion.ts`](../../resources/js/lib/motion.ts).

## Three.js

Nog niet geïnstalleerd, en dat is een keuze. Three.js is een forse
afhankelijkheid die het bundelformaat flink laat groeien en op zwakkere
apparaten merkbaar is. De meeste effecten die "3D" lijken zijn met GSAP, CSS
transforms en SVG te maken.

Blijkt er echt 3D nodig te zijn, dan:

1. Installeer `three` en laad het uitsluitend met een dynamische import, zodat
   het niet in de hoofdbundel belandt.
2. Zet het in een eigen component dat zichzelf opruimt (renderer, scene,
   geometrieën en materialen moeten allemaal `dispose()` krijgen).
3. Val terug op een statische afbeelding bij `prefers-reduced-motion` en op
   apparaten zonder WebGL.
4. Werk dit document bij met wat je hebt gedaan en waarom.

## Commando's

```bash
npm run dev           # ontwikkelserver met hot reload
npm run build         # productiebuild
npm run types:check   # vue-tsc, moet schoon zijn
npm run lint          # eslint
npm run format        # prettier
```
