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

Drie groepen, en die indeling volgt waar iemand naar op zoek is:

| Groep          | Wat erin staat                                           |
| -------------- | -------------------------------------------------------- |
| _(geen kopje)_ | Het dashboard. Eén regel, dus een kopje erboven is ruis. |
| **Website**    | Alles waarmee de eigenaar zijn eigen site vult.          |
| **Beheer**     | De logboeken, de mail en de gebruikers.                  |

De volgorde is geen alfabet maar frequentie: **Website staat boven Beheer**,
want daar moet de eigenaar dagelijks zijn. Het beheergedeelte kijk je na, dat
gebruik je niet de hele dag.

**Meer kopjes dan dit worden het niet.** Een zijbalk met zeven secties is een
inhoudsopgave, en daar zoek je langer in dan in een lijst. Komt er een module
bij, dan hoort die in een van deze drie en niet in een nieuwe groep.

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
