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

| Onderdeel                            | Waarvoor                                                                                                              |
| ------------------------------------ | --------------------------------------------------------------------------------------------------------------------- |
| `layouts/PublicLayout.vue`           | Kop, voet, lichtveld, smooth scrolling en de scroll-reveals. Zet `brand-site` op de wortel.                           |
| `components/site/SiteHeader.vue`     | Navigatie: een zwevende glazen balk met een lichtmarkering; mobiel een menu als diafragma.                            |
| `components/site/SiteFooter.vue`     | De voettekst: de afsluitende compositie van elke publieke pagina.                                                     |
| `components/site/SiteSection.vue`    | Eén breedte en één verticale ruimte voor alle secties; open veld of plaat.                                            |
| `components/site/SectionHeading.vue` | Opschrift, titel en inleiding; de titel kiest zijn maat op lengte.                                                    |
| `components/site/SiteIntro.vue`      | De welkomstanimatie bij het eerste bezoek: een lens stelt scherp en landt in de iris van de hero. Zie `lib/intro.ts`. |
| `components/site/ScrollProgress.vue` | De voortgangsbalk bovenaan.                                                                                           |
| `components/site/sections/`          | De onderdelen zelf, één component per `PageSectionKey`, plus hun deelcomponenten.                                     |
| `resources/css/site.css`             | De stijl van de publieke site, één bestand per onderdeel onder `resources/css/site/`.                                 |

**De landingspagina bepaalt niet meer welke onderdelen er staan.** Dat doet
de klant, in het portaal. `Welcome.vue` is een `v-for` over de sleutels die
de server meegeeft, met een getypte kaart van sleutel naar component; de kop
staat er los boven omdat die niet te verslepen is. Wil je er een onderdeel
bij, lees dan [pagina-indeling](pagina-indeling.md) -- een component
toevoegen zonder de stappen daar levert een sectie op die nergens
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

**Twee helften, twee lettertypen.** Het portaal en de inlogschermen staan
in Instrument Sans (400, 500, 600). De publieke site staat in **Geist**
(400 tot 700) voor alle tekst en **Geist Mono** (400, 500) voor alles wat
maat, tijd of telling is: jaartallen, perioden, stapnummers, statistiek en
kerngegevens. Waarom de site een eigen letter kreeg staat in
[besluit 021](../decisions/README.md).

Geen van drieën wordt bij een externe dienst opgehaald:
`laravel-vite-plugin/fonts` haalt de bestanden bij de build op en zet ze in
`public/build/`, zodat er in productie geen verzoek naar een ander domein
gaat.

**Welke pagina welke letter laadt staat in `app.blade.php`**, bij
`@fonts($isPublicPage ? null : 'instrument-sans')`. Zonder die filter laadt
elke portaalpagina ook de Geist-bestanden vooraf, en die gebruikt hij
nergens. Van Geist worden alleen 400 en 600 vooraf geladen (de lopende
tekst en de koppen boven de vouw); Geist Mono komt pas als hij nodig is.

De plugin zet de families klaar als `--font-geist` en `--font-geist-mono`.
Op de site lees je ze via `.brand-site` (de klasse op de wortel van
PublicLayout), of met de Tailwind-klassen `font-display` en `font-data`.
Die vallen terug op Instrument Sans en de systeem-mono, zodat een
component dat ook in het portaal staat daar niet zonder letter komt.

**Let op bij een venster.** Een dialoog van reka-ui staat in een portaal
aan het eind van `<body>`, buiten `.brand-site`. Daar erft hij de letter
dus niet; `resources/css/site/vensters.css` zet hem op `.brand-blad` zelf.

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

> **Bouw nooit zelf een knop met `navigator.clipboard.writeText()`
> erachter.** Dat ziet er in vijf regels uit alsof het werkt, en het werkt
> ook -- op https. Lokaal op een `.test`-adres gebeurt er niets: geen tekst
> op het klembord en geen vinkje, zonder foutmelding. Je klikt, en er
> verandert simpelweg niets op het scherm.
>
> Het is hier twee keer gebeurd, voor het laatst bij "Kopieer de tekst" op
> het scherm [Juridisch](../security/verzoeken-van-bezoekers.md). Komt er
> een plek bij waar iets te kopiëren valt, dan is `CopyButton` het
> antwoord en niet een eigen knopje ernaast.

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

## De overgang tussen twee pagina's

Er zit een korte overgang op `<main>`, en de reden om hem te maken was niet
"het mag wat levendiger". Er zát al een haperingsbron, en die is ermee
opgelost.

**Wat er misging.** Bij een navigatie wisselt Inertia de inhoud meteen om.
Een tel later pas draait `scanReveals` in
[`PublicLayout`](../../resources/js/layouts/PublicLayout.vue), en
`revealOnScroll` doet een `gsap.fromTo` die élk blok eerst op `opacity: 0`
zet om het daarna op te laten komen. De nieuwe pagina werd dus één of twee
beeldjes volledig getekend, knipperde naar onzichtbaar, en kwam daarna pas
op.

**Wat het nu is.** Een Vue-`<Transition>` met `mode="out-in"` om `<main>`
heen, met de taal én de paginanaam als sleutel. De nieuwe `main` begint op
`opacity: 0`, dus het moment waarop `scanReveals` alles op nul zet valt
binnen die onzichtbare fase. Eén beweging in plaats van een flits gevolgd
door een beweging.

|     |                                                                                 |
| --- | ------------------------------------------------------------------------------- |
| Uit | 160 ms, alleen `opacity`                                                        |
| In  | 420 ms, `opacity` en 12 beeldpunten omhoog, met `cubic-bezier(0.16, 1, 0.3, 1)` |

Het weggaan is sneller dan het komen: wachten tot iets verdwenen is voelt
als vertraging, iets dat opkomt leest als aankomst. Alleen `opacity` en
`translate`, want die doet de browser op de grafische kaart zonder de
pagina opnieuw op te meten -- een overgang die de opmaak laat herberekenen
hapert op een telefoon, en dan heb je een animatie toegevoegd die het juist
slechter maakt.

Die tijden waren eerst 140 en 260, en dat voelde schokkerig in plaats van
snel: een overgang die net te kort is leest als een sprong met een waas
eroverheen. De versoepeling zit vooral in de curve -- die begint snel en
komt lang en zacht tot stilstand, dus het laatste stukje glijdt uit in
plaats van te stoppen.

`out-in` en niet tegelijk: twee pagina's over elkaar heen hebben allebei
hun eigen hoogte, en dan springt de voettekst tijdens de overgang op en
neer.

**Alleen `<main>`, niet de hele pagina.** De kop en de voettekst blijven
staan. Dat is wat continuïteit geeft: de navigatieknop die je net
aanklikte blijft op zijn plek.

### Scannen hoort bij `before-enter` en niet bij `navigate`

**Dit is de val van dit hoofdstuk**, en hij kostte een pagina zonder tekst.

De markup van een reveal draagt `opacity-0` als klasse -- zie
[`SectionHeading.vue`](../../resources/js/components/site/SectionHeading.vue)
-- en `revealOnScroll` haalt die er met een inline stijl weer af. Een
element dat niet wordt gescand blijft dus onzichtbaar. Dat is geen
vertraagde animatie maar verdwenen inhoud.

Zolang `<main>` geen overgang had, viel dat goed samen: Inertia wisselde de
inhoud om, en een `nextTick` na `navigate` stonden de nieuwe elementen er.
Met `mode="out-in"` is dat niet meer waar. `navigate` vuurt bij het
omwisselen, maar de nieuwe `main` wordt pas ingevoegd nadat de oude is
weggegaan -- honderdzestig milliseconden later. Die herscan pakte dus de
vertrekkende pagina, en de aankomende nooit. Ga je van een subpagina terug
naar de voorpagina, dan bleef vrijwel alle tekst weg.

Daarom staat de herscan nu in `before-enter`, als de nieuwe `main` er wél
is en nog onzichtbaar. De luisteraar op `navigate` is er nog steeds, maar
alleen voor een navigatie die de pagina **niet** wisselt -- het
contactformulier dat zichzelf opnieuw laadt en zijn bevestiging toont, dat
soort geval. Wisselt de pagina wel, dan slaat hij over.

**Twee beeldjes wachten voordat je scant.** `alInBeeld` meet met
`getBoundingClientRect`, en Lenis zet zijn scrollpositie door in zijn eigen
beeldje -- dat hangt aan de ticker van GSAP en niet aan een losse
`requestAnimationFrame`. Meet je meteen, dan reken je met de plek waar je
vandaan kwam, en krijgt een blok dat allang in beeld staat een
scroll-trigger die nooit meer langskomt. Dezelfde onzichtbare tekst, via
een andere weg.

**En er ligt een vangnet in `after-enter`.** Dat loopt na de overgang de
reveals na die ín beeld staan en tóch nog op nul: die zet het aan. Het hoort
niets te vinden; het staat er omdat de fout die het afvangt een pagina
zonder tekst oplevert, en dat is het ergste wat er met deze site kan
gebeuren. Vindt het toch iets, dan is er iets mis met het moment waarop
gescand wordt -- niet met die regel.

### Een herscan mag niet laten knipperen

`revealOnScroll` en `splitReveal` mogen meer dan eens over dezelfde pagina
lopen -- bij een navigatie die het component laat staan gebeurt dat. Zonder
zeef zet zo'n herscan álles wat je op dat moment ziet terug op nul om het
opnieuw op te laten komen. Verstuur je het contactformulier onderaan de
voorpagina, dan knippert daarmee de halve pagina.

Beide functies slaan daarom over wat `data-gezien` draagt. Dat merkje komt
er bij `revealOnScroll` pas als de beweging klaar is: een blok dat nog op
zijn scroll-trigger wacht is dus niet gemerkt, en wordt bij een herscan
gewoon opgepakt -- precies wat je wil, want dat is nog onzichtbaar. Bij
`splitReveal` komt het merkje meteen, want daar staat de kop vanaf de split
al op doorzichtigheid 1 en schuiven alleen de regels nog achter hun masker
omhoog.

Het vangnet in `after-enter` merkt ook wat het aanzet. Anders pakt een
volgende herscan precies het blok op dat het net heeft gered.

### Lenis en ScrollTrigger moeten opnieuw opmeten

**Een paginawissel is geen `resize`, en dat kost je de helft van je
scrollsprongen.** Allebei houden ze de hoogte van het document in een cache
en werken die bij als het venster van maat verandert. Bij een
Inertia-navigatie wordt de inhoud vervangen en wordt de pagina een paar
schermen langer of korter, terwijl zij met het oude getal blijven rekenen.

Voor Lenis is dat hard: `scrollTo` doet `clamp(0, target, this.limit)`.
Kom je van een korte subpagina terug naar de lange voorpagina en klik je op
"LinkedIn", dan wordt dat doel afgeknipt op de hoogte van de pagina waar je
vandaan kwam -- en land je ergens halverwege. Klik je nog een keer, dan is
Lenis zichzelf ondertussen via zijn eigen waarnemer bijgekomen en klopt het
wel. Precies de klacht "je moet nog een keer drukken".

Voor ScrollTrigger is het zachter maar even vervelend: een nieuw aangemaakte
trigger berekent zijn begin- en eindpunt met een verkeerde maximale
scrollpositie, en gaat dus op de verkeerde hoogte af of helemaal niet.

Twee plekken lossen dat op, en ze dekken verschillende gevallen:

| Waar                           | Wat               | Waarom daar                                                                                    |
| ------------------------------ | ----------------- | ---------------------------------------------------------------------------------------------- |
| `scrollNaar`, bij `direct`     | `lenis.resize()`  | Een directe sprong is het geval waarin de pagina net gewisseld kan zijn                        |
| `scanReveals`, na het aanmaken | `hermeetScroll()` | Dan rekent de verversing met de triggers die er nú zijn, niet met die van de vertrokken pagina |

Een gewone klik op een anker binnen dezelfde pagina hermeet niet: daar is
niets veranderd, en een hermeting dwingt de browser de hele opmaak opnieuw
door te rekenen.

### De scroll hoort in de onzichtbare fase

Inertia zet de scroll normaal terug naar boven op het moment van
omwisselen -- dus vóórdat je iets ziet veranderen, en dan voelt het als een
schok die losstaat van je klik. Nu gebeurt het in `before-enter`, als het
scherm leeg is. Daar hoort `scrollNaar(..., { direct: true })` bij: een
glijbeweging achter een doorzichtige laag is tijd die je kwijt bent zonder
dat iemand het ziet.

Die sprong is verdeeld over drie gevallen, en dat is geen slordigheid:

| Geval                                       | Wie het doet                      |
| ------------------------------------------- | --------------------------------- |
| Alleen de taal gewisseld: blijf waar je was | `PublicLayout`, in `before-enter` |
| Geen anker in het adres: naar boven         | `PublicLayout`, in `before-enter` |
| Wél een anker: naar dat onderdeel           | `Welcome.vue`, in `onMounted`     |

Die laatste staat daar omdat hij ook zonder overgang moet werken. Open je
`/#diensten` rechtstreeks uit een zoekresultaat of ververs je de pagina,
dan is er geen wissel en dus geen `before-enter` -- maar wel een
`onMounted`.

#### Een taalwissel is geen paginawissel

Hij komt wél langs deze overgang, want de taal zit in de sleutel van
`<main>`. Maar de bezoeker gaat nergens heen: hij las iets en wil datzelfde
stuk in de andere taal lezen. Naar boven springen gooit hem weg van waar
hij was.

`PublicLayout` kijkt daarom in een `watch` op de sleutel of alleen het
taaldeel is veranderd, onthoudt daar waar de bezoeker stond, en zet dat in
`before-enter` terug met `scrollNaar(<getal>)`.

**Dat "waar hij stond" is een onderdeel en geen pixel.** De Engelse tekst
is korter: op de voorpagina scheelt de hele pagina ruim duizend pixels. Bij
een gelijke `scrollY` schoof de werkwijze daardoor 379 pixels en het
contactblok 1007 -- je blijft op je plek en kijkt toch naar iets anders.
`pakHouvast()` onthoudt daarom het laatste `section[id]` dat boven de
bovenrand begon plus hoeveel ervan al voorbij was, en `zetTerug()` rekent
de pixel daar na de wissel opnieuw uit. Staat de bezoeker nog in de hero of
op een subpagina zonder onderdelen, dan is er niets om vast te houden en
valt het terug op de pixel.

**Die `watch` is het hele punt, en hij hing eerst aan het verkeerde
touwtje.** De eerste poging gebruikte `router.on('navigate')`. Inertia
vuurt dat alleen af als het adres verandert:

```js
// @inertiajs/core, in setPage
if (!replace) {
    fireNavigateEvent(page, { cached, visitId });
}
```

Een taalwissel is een POST die via `back()` op dezelfde URL terugkomt, dus
`replace` is waar en er komt niets binnen. Een `watch` op een computed
vuurt wél, want die sleutel verandert gewoon. Bijkomend voordeel: een
watcher met de normale `pre`-volgorde draait vóór de DOM-update, dus daar
staat de scrollpositie nog goed.

Dat terugzetten wacht twee beeldjes, zodat het ná het ankersprongetje van
`Welcome.vue` komt. Anders wint dat sprongetje en sta je alsnog ergens
anders.

**En het vangnet kijkt bij een taalwissel tot de onderrand.** `alInBeeld`
rekent standaard tot 85% van de schermhoogte; een blok dat daaronder
begint wacht netjes op zijn scroll-trigger. Dat klopt als je bovenaan
begint, maar bij een taalwissel blijf je staan waar je stond en valt er
geregeld iets in die onderste strook. In de browser gemeten bleef stap vier
van de werkwijze zo leeg achter. `naBinnenkomst` geeft `alInBeeld` daarom
`1` mee zodra het alleen de taal was -- de bezoeker keek er een tel geleden
nog naar.

**De voortgangsbalk van Inertia blijft zoals hij is.** Die verschijnt pas
na 250 ms, dus bij een snelle navigatie zie je hem niet en bij een trage
wel. Precies goed, en niets om aan te zetten.

**Bij `prefers-reduced-motion` is er geen overgang**, en niet een korte.
Twintig milliseconden is geen rustiger animatie maar een flikkering. Er
gaat ook niets verloren: `revealOnScroll` zet daar alles meteen op zijn
plek, dus de knippering die deze overgang afdekt bestaat er niet.

## Animatie

De animatielaag zit in [`resources/js/lib/motion.ts`](../../resources/js/lib/motion.ts):

```ts
// Waar ben je, wat kan het apparaat
prefersReducedMotion(): boolean
heeftMuis(): boolean
opEenTelefoon(): boolean                        // < 48rem, voor de duur
opEenBreedScherm(): boolean                     // >= 50rem, voor blur en diepte
alInBeeld(element, deel):         boolean

// Scrollen
startSmoothScroll():              () => void   // geeft een opruimfunctie terug
scrollNaar(doel, opties):         boolean
hermeetScroll():                  void
volgSecties(sleutels, opActief):  () => void
trackScrollProgress(element):     () => void
lichtVeld(veld, scope):           () => void

// Opkomen
revealOnScroll(selector, scope):  () => void
splitReveal(selector, scope):     () => void   // koppen, met scherpstellen
kopOpkomst(kop, opties):          () => void   // de kop van de hero
irisOpen(element, opties):        () => void   // diafragma
kaartenBinnen(kaarten):           () => void
revealCards(kaarten, opties):     () => void

// Per onderdeel
drawTimeline(rail, punten):       () => void
followYears(groepen):             () => void
countUp(tellers):                 () => void
tekenRing(ring, baan, opties):    () => void
tekenPad(pad, punt, stappen):     () => void
rolbord(selector, scope):         () => void
vulStatistieken(blok, delen):     () => void
groeiLijn(selector, scope):       () => void

// Tijdens het scrollen (ronde twee)
plaatOpkomst(plaat):              () => void   // een plaat komt uit de diepte
stapelBinnen(elementen, opties):  () => void   // een groep komt samen binnen
scrollVoortgang(el, opties):      () => void   // --voortgang 0..1 voor CSS

// Op de muis
volgDeMuis(element):              () => void
kantelKaarten(kaarten, opties):   () => void
spotlicht(elementen):             () => void
```

`formatteerGetal` staat niet hier maar in
[`lib/getallen.ts`](../../resources/js/lib/getallen.ts): de
statistiekcomponenten worden ook in het portaal getoond, en een import uit
dit bestand trok daar de hele animatiechunk mee.

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

Hier stond `scrollThrough`, dat het muiswiel binnen één vak overnam om
door een reeks te bladeren. Het is weg, en waarom staat bij
[Het wiel is van de pagina](#het-wiel-is-van-de-pagina).

Wat wel bleef: **alles op de publieke site dat
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

### De hero: "Iris"

[`HeroSection.vue`](../../resources/js/components/site/sections/HeroSection.vue)
is een scène van drie lagen: de **diepte** (de achtergrond, een waas die de
tekst zijn contrast geeft, en een gloed die de cursor volgt), de **tekst**,
en de **iris**: het portret in een stelsel van ringen, met een vaste schaal
van maatstreepjes, een ring die zich tekent en een lichtboog die blijft
rondgaan. Het merk is een oog, en deze scène speelt daarop; zie
[besluit 019](../decisions/README.md).

**Bij binnenkomst gaat het oog open.** De achtergrond stelt scherp (van
`blur(14px)` en iets te dichtbij naar scherp), het portret wordt onthuld
door een diafragma (`irisOpen()`), de ring tekent zich (`tekenRing()`) en
de kop komt regel voor regel op en stelt daarbij scherp (`kopOpkomst()`).

**Bij het scrollen zakt de scène in drie lagen weg**: de achtergrond het
langzaamst, de tekst het snelst, en de iris trekt iets samen alsof het oog
scherpstelt op wat eronder komt. Dat is één tijdlijn met `scrub` op de
hero, van `top top` tot `bottom top`. **`scrub` mag hier**, want het
drukt een positie uit en geen waarde -- zie de les over `scrub` verderop.
Er zit geen `pin` op: de hero is `min-height: 100svh` en groeit gewoon mee
met een lange kop of inleiding.

**De kop kiest zijn maat op lengte** (`data-lengte`: kort tot 42 tekens,
middel tot 80, daarboven lang), en een inleiding van meer dan 180 tekens
krijgt een kleinere letter. De klant typt deze teksten zelf, en een maat die
bij vijf woorden indrukwekkend is, maakt van tachtig tekens een muur die de
knoppen onder de vouw duwt.

**De hero heeft geen `id`.** Het houvast bij een taalwissel zoekt
`main section[id]`, en in de hero valt niets vast te houden.

#### De typmachine is weg

Hier werd de kop letter voor letter ingetikt. Dat oogde levendig bij een
kop van vijf woorden, en het is eruit gehaald om wat er bij een beheerde
kop gebeurde: bij tachtig tekens las je drie seconden lang "Advies over
inr", en een bezoeker die op dat moment een schermafdruk maakt of wegklikt
heeft een halve zin gezien. `kopOpkomst()` toont de hele zin meteen in zijn
regels, en laat alleen de regels opkomen.

#### Op een telefoon een visitekaartje, geen iris

Onder de tabletgrens staat er een glazen strook: het portret klein en rond,
met een dunne ring, en de naam en de functie ernaast. De naam komt uit de
gedeelde prop `eigenaar` (`config/security.php`), dezelfde als bij het
LinkedIn-blok; hij stond hier eerst hardgecodeerd. Is hij leeg, dan toont
het kaartje alleen de foto en de functie.

Het opschrift boven de titel verdwijnt op die breedte, want de functie
staat dan in het kaartje.

#### Waarom er op een telefoon geen parallax, blur en draaiende boog is

**Een element dat permanent beweegt staat permanent in een bewegende
compositielaag, en die rastert de browser in lagere resolutie**; de
scherpte komt pas terug als hij stilstaat. Bij het portret zag je dat als
een foto die de hele tijd wazig is. Een `filter: blur()` over een heel
scherm kost op een telefoon bovendien meer dan hij oplevert. De diepte, de
blur en de draaiende boog staan daarom in een `gsap.matchMedia` vanaf 50rem;
op een telefoon komt de achtergrond alleen op.

**Dit is een val die terugkomt.** Zet je ooit een animatie zonder einde
op iets waar een foto of scherpe tekst in zit, reken er dan op dat het
op een telefoon zachter wordt.

### De intro bij binnenkomst: "scherpstellen"

De welkomstanimatie bij het eerste bezoek aan de voorpagina. Zie
[`SiteIntro.vue`](../../resources/js/components/site/SiteIntro.vue),
[`intro.ts`](../../resources/js/lib/intro.ts) en
[`intro.css`](../../resources/css/site/intro.css).

**Het idee.** Het merk is een oog en de site is een lens: dingen komen in
focus. In het donker tekenen de ringen van een lens zich om het merkteken,
het teken stelt scherp (een kleine blur op één klein beeld), de schaalring
draait als een focusring, een lichtboog loopt mee en "@T IT Advies" komt
letter voor letter op. Dan **vliegt de lens naar de iris in de hero** --
dezelfde cirkels in hetzelfde `viewBox`, dus gemeten en verschoven
(translate + scale) vallen ze precies op elkaar -- en gaat het diafragma
daar open: eerst tot de rand van de ring, zodat je even door de lens het
portret ziet, en dan tot voorbij de hoeken. Het merkteken krimpt de pupil
in, en waar de lens oplost tekent de ring van de hero zich en gaat het
portret open. De entree eindigt dus niet vóór de hero maar ín de hero.

| Fase        | Breed (≥ 50rem)                                            | Smal                                             |
| ----------- | ---------------------------------------------------------- | ------------------------------------------------ |
| Opbouw      | ~1,3 s: ring, schaal, binnenring, boog, naam               | ~1,0 s: alleen de ring en de naam                |
| Landen      | ~1,3 s: lens vliegt naar de iris, diafragma vanuit de iris | ~0,8 s: lens krimpt, diafragma vanuit het midden |
| Hero begint | bij aankomst van de lens (signaal)                         | meteen bij het landen (signaal)                  |

**Wanneer hij speelt** (allemaal in `introSpeeltAf()`):

- alleen bij de **eerste paginalaad**, en alleen als dat de voorpagina is
  (`page.component === 'Welcome'`). Een Inertia-navigatie naar de
  voorpagina speelt hem nooit: de vraag is dan allang beantwoord;
- **één keer per browser**: daarna staat `brand-intro-gezien` in
  `localStorage`. Geen datum, geen teller, niets naar de server. Verversen
  en een taalwissel spelen hem dus niet opnieuw;
- **niet met een anker** in het adres (`/#contact`): dan wil je daar
  uitkomen. Er wordt dan ook niets onthouden;
- **niet zonder opslag** (privévenster, geblokkeerd): anders speelde hij bij
  elke verversing;
- **niet onder reduced motion**: dan staat de site er meteen;
- **niet als het laden al traag was** (DOMContentLoaded na 2,5 s breed of
  1,5 s smal); ook dan wordt niets onthouden.

**Overslaan.** Elke toets (ook Tab en Escape), klik, tik, scrollbeweging of
veeg spoelt de entree in een paar tienden van een seconde door naar het
einde (de landing op 2,6× snelheid), en rechtsonder staat de knop "Intro
overslaan". Er wordt niet aan de scrollpositie gezeten: wie meteen scrolt,
scrolt. Zodra de lens landt laat de laag klikken door (`pointer-events`).

**De hero wacht op een signaal, met een vangnet.** `naDeIntro()` in
`intro.ts` roept de hero aan zodra de intro `introDraagtOver()` meldt, of
meteen als er geen intro speelt, en anders uiterlijk na 2,6 s. De laag
zelf heeft in CSS een vangnet (`brand-site-intro-vangnet`): na vier
seconden is hij doorzichtig en laat hij alles door, ook als de code
halverwege vastloopt. Beide zijn getest door de entree halverwege stil te
zetten.

**Wat het kost.** Het gat is een `mask` met een radiaal verloop op
`--gat`; die variabelen staan op het veld, en het veld heeft geen
kinderen, zodat er per stap niets anders opnieuw berekend wordt. De gloed
is een eigen laag die alleen in doorzichtigheid verandert (als variabele
in het veld liet hij elk beeldje het hele scherm opnieuw schilderen). De
lens beweegt met transform; het enige filter is de blur op het merkteken,
alleen breed.

**Opnieuw bekijken.**

- Zet `?intro` achter het adres (`https://t-it-advies.test/?intro`): dan
  speelt hij altijd, ook als hij al gezien is, en de parameter gaat daarna
  uit het adres. Dit werkt ook op de echte site, zonder iets te wissen.
- Of wis de vlag: in de console van de browser
  `localStorage.removeItem('brand-intro-gezien')`, en ververs.
- In de ontwikkelmodus hangen de twee tijdlijnen aan
  `window.__brandIntro` (`opbouw()`, `landing()`), zodat je hem kunt
  stilzetten en beeld voor beeld kunt bekijken
  (`__brandIntro.opbouw().pause().progress(0.5)`). In de gebouwde site
  bestaat dat niet.

#### De hero na de intro

De hero zelf is ongewijzigd van opzet -- de achtergrond komt op, de kop
komt op, de iris gaat open en de ring tekent zich -- maar begint nu op het
signaal in plaats van na een vaste wachttijd.

**De achtergrond heeft geen blur meer.** Hij stelde scherp van een
vervaging over het hele scherm, en dat viel samen met het diafragma en de
kop: op de gebouwde site was 22 tot 37 procent van de beeldjes in de hero
te laat (wie tijdens de entree scrolde), zonder de blur 0 tot 3 procent;
de p95 tijdens de entree ging van 33 naar 16,8 ms. Het scherpstellen doet
de lens; de foto schuift alleen nog van iets te dichtbij op zijn plek.
Op een telefoon loopt zijn tijdlijn 1,4× sneller. De grens is `47.9375rem`
in [`motion.ts`](../../resources/js/lib/motion.ts) (`opEenTelefoon`); de
zwaardere effecten (blur, diepte, de volle lens) gebruiken de tabletgrens
van 50rem (`opEenBreedScherm`).

### Beweging tijdens het scrollen, niet alleen bij binnenkomst

De eerste versie van "Helder zicht" had één entree bovenaan en daarna
vooral reveals. De eigenaar vond dat terecht te weinig: de beweging hoort
bij het lezen en niet alleen bij het binnenkomen. Daarom heeft elk
onderdeel nu een eigen moment dat bij zijn inhoud hoort (zie de
moduledocs), en zijn er drie gedeelde hulpen:

- **`plaatOpkomst(plaat)`**: een plaat (een sectie op oneven plek) komt
  bij het binnenscrollen omhoog uit de diepte, van iets lager en iets
  kleiner naar zijn plek, gekoppeld aan de scroll. `SiteSection` doet dit
  zelf; een onderdeel hoeft er niets voor te doen. De transform gaat eraf
  zodra de plaat op zijn plek staat, want een blijvende transform maakt de
  plaat het kader voor alles wat erin `position: fixed` staat.
- **`stapelBinnen(elementen, { van })`**: een groep die samen binnenkomt,
  via `ScrollTrigger.batch`. Wat tegelijk in beeld komt, komt ook tegelijk
  op; een raster dat per tegel op zijn eigen trigger wacht, komt in brokken
  binnen en leest als laden.
- **`scrollVoortgang(element)`**: de voortgang door een element als
  `--voortgang` (0 tot 1), voor effecten die in de CSS horen. **Zet hem op
  een klein element**: een CSS-variabele erft, en elke wijziging laat de
  browser de stijl van alle kinderen opnieuw berekenen.

Wat voor alle drie geldt, en voor wat je erbij zet:

- `scrub` alleen voor iets wat een positie uitdrukt; een waarde die
  terugloopt als je omhoog scrolt blijft eenmalig (`once`).
- **Geen `pin` van ScrollTrigger.** Een korte vaste scène mag alleen met CSS
  `position: sticky`, binnen de sectie, met hoogstens een halve
  schermhoogte extra scroll, en uit op een telefoon en onder reduced
  motion.
- Geen wachttijd die inhoud tegenhoudt: wie snel scrolt ziet nooit een
  leeg vak.

#### Het wiel is van de pagina

De carrousel van de ervaring nam het muiswiel over zolang hij in beeld
stond (`scrollThrough`), om door de functies te bladeren. Met 27 functies
stond de pagina daardoor 28 wielstappen stil: vanaf de pagina gezien voelde
dat als vastlopen. Dat is eruit; bladeren in de carrousel gaat met de
jaartallen, de knoppen, de toetsen en een klik. Zie
[ervaring](modules/ervaring.md). **Neem het scrollen van de bezoeker niet
over** voor iets wat ook met een knop kan.

### Labels zonder lijntjes

Op de eerste versie stond voor elk sectielabel een kort blauw lijntje, en
ook boven de cijfers van de loopbaan, achter de groepskoppen van de
statistieken, boven het mailadres bij contact en voor de kopjes op een
projectpagina. De eigenaar vond die terecht weinig zeggend: ze herhaalden
alleen dat er een label stond. Ze zijn overal weg, en er is één vorm voor
in de plaats gekomen, met twee varianten.

- **Het sectielabel** (`.brand-site-opschrift` in `resources/css/site/basis.css`):
  klein, halfvet, in cyaan, dicht op de titel. Label en titel vormen samen
  één kop. Dezelfde vorm voor de kolomkoppen in de voettekst, het kopje
  van de opleidingen, de groepskoppen van de statistieken en het opschrift
  van /projecten en een project.
- **De meting** (`.brand-site-metingen`, `.brand-site-meting-*`): een getal
  met zijn label. Het getal bovenaan, groot en halfvet; het label eronder,
  klein en zilver; een haarlijn links die de metingen van elkaar scheidt.
  Die lijn ordent echte inhoud, en is daarmee geen versiering. De cijfers
  van de loopbaan en de tellers van de statistieken zijn zo gebouwd. Het
  label van een kerngegeven heeft dezelfde letter, maar staat boven de
  waarde, omdat je een lijst van boven naar beneden leest.

**Er komen geen badges, stipjes of iconen voor in de plaats.** Een label dat
zichzelf draagt heeft geen accent nodig.

### Horizontaal met de scroll mee

Op een paar plekken beweegt een blok zijwaarts terwijl je verticaal scrolt,
gekoppeld aan de positie (`scrub`): terugscrollen draait het om.

| Waar                  | Wat er beweegt                                                         | Wanneer stil                      |
| --------------------- | ---------------------------------------------------------------------- | --------------------------------- |
| Kerngegevens          | De linkerkolom van links, de rechterkolom van rechts                   | voor het midden van het scherm    |
| Projecten, de casus   | Het beeld van links, de tekst van rechts, met de opening van het beeld | als het beeld open is             |
| Projecten, de kaarten | De rij van rechts naar links, elke kaart iets verder weg               | ruim een derde van het scherm in  |
| Ervaring, de jaarband | De jaartallen van links naar rechts, terug in de tijd                  | loopt door zolang hij in beeld is |

Drie afspraken, die bij elke nieuwe zijwaartse beweging horen:

- **Wat je leest of aanklikt, staat stil.** Een beweging eindigt voordat de
  tekst het midden van het scherm haalt; een kaart is daarna een gewone
  link. Alleen de jaarband loopt door, en die is een beeld (`aria-hidden`).
- **De afstand komt uit de maten zelf**, als functie met
  `invalidateOnRefresh`, en is begrensd: een spoor van zesduizend pixels in
  één schermhoogte is een veeg en geen beweging.
- **De container knipt horizontaal af** (`overflow-x: clip`, met een kleine
  `overflow-clip-margin` voor gloed en randen), zodat de pagina tijdens de
  beweging niet breder wordt dan het scherm. `clip` en geen `hidden`: dat
  maakt geen schuifvlak en laat `sticky` heel.

En de les die het kostte: **geen `stagger` in een scrub met afstanden die
opnieuw worden berekend**. Zie de valkuilen.

### Het lichtveld en de horizonlijn

Twee dingen die de secties met elkaar verbinden, en die allebei op **plek
in de rij** werken en niet op naam -- zodat een andere volgorde ze niet
kapotmaakt.

- **Het lichtveld** is één vast vlak achter de hele pagina, in
  `PublicLayout`. Er liggen twee zachte lichtvlekken in, en `lichtVeld()`
  schuift ze per actieve sectie naar links of rechts (`--veld-kant`, even
  of oneven plek). Alleen `transform`, en het verloop zelf blijft staan,
  dus het kost de browser niets om te tekenen. De korrel die er overheen
  ligt zit op dit vaste vlak en nooit op iets dat meescrollt.
  **Het vlak knipt zichzelf af** (`overflow: hidden`, het licht staat in
  een `::before` die groter is): een vast element dat buiten het scherm
  uitsteekt, laat een telefoon de pagina breder maken dan hij is.
- **De horizonlijn** staat boven elk open veld (`base`) en tekent zich van
  het midden uit zodra de sectie binnenkomt. Dat doet de browser zelf, met
  `animation-timeline: view()`; een browser die dat niet kent toont de lijn
  gewoon. Geen JavaScript, dus ook niets op te ruimen.

`lichtVeld()` wordt bij elke scan opnieuw opgebouwd, net als de reveals:
na een navigatie of een taalwissel staan er andere secties.

### Twee soorten reveals

`[data-reveal]` schuift een heel blok omhoog. `[data-split]` tilt tekst
regel voor regel achter een masker op. Allebei worden ze gescand door
[`PublicLayout.vue`](../../resources/js/layouts/PublicLayout.vue), ook
opnieuw na een Inertia-navigatie.

Gebruik `data-split` alleen op een kop. SplitText moet meten, en bij elke
maatverandering en elk later geladen lettertype opnieuw; dat is werk dat je
op drie woorden doet en niet op elke alinea.

**Een kop stelt daarbij scherp**: op een breed scherm komen de regels op
van een lichte waas (`blur(10px)`) naar scherp, als een lens die zijn focus
vindt. Alleen op koppen en alleen vanaf 50rem; `filter` is duurder dan een
transform, en `clearProps` haalt hem er na afloop weer af zodat de tekst
niet in een eigen laag blijft staan.

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

**Sinds de nieuwe vormgeving geldt dit alleen nog op een telefoon.** Op een
breed scherm zijn de diensten een index met een meelopend paneel; zie
[diensten](modules/diensten.md). De kaarten onder elkaar op een telefoon,
en de certificaattegels, komen nog steeds zo binnen.

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

De balk zet er zoveel los als er passen, gemeten, en de rest gaat achter
**"Meer"** (hoogstens zeven los). Het menu gebruikt ook kortere namen dan
het beheer ("Feiten", "Aanpak", "Cijfers"). Hoe dat werkt en wat er op welke
breedte staat: zie "Korte namen in het menu, en zoveel als er passen" in
[pagina-indeling](pagina-indeling.md).

De balk zelf heeft `flex: 1` tussen het merk en de knoppen: zijn breedte
hangt dan niet af van wat erin staat, en de meting kan niet heen en weer
springen. Tot de eerste meting zijn de items onzichtbaar, zodat er bij het
laden niets verschijnt en weer verdwijnt. Staat er iets uit "Meer" aan, dan
kleurt de knop mee; de schuivende markering blijft eronder weg, want die
hoort bij een onderdeel en "Meer" is er geen.

Op mobiel is dit geen vraag: daar is het een kolom, en die kan gewoon
doorlopen.

Het merkteken staat links naast de naam. Op een scherm smaller dan 26 rem,
en tussen 50 en 64 rem, blijft alleen dat teken over; daar heeft het menu
de ruimte nodig, en het teken alleen is herkenbaar genoeg -- het is
hetzelfde beeldmerk als de favicon.

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

#### Een zwevende glazen balk

De kop is een pil van glas die boven de pagina zweeft (`brand-site-kopbalk`
in `resources/css/site/kop.css`). Op de voorpagina ligt hij over de hero
heen; op een subpagina neemt hij gewoon zijn plek in, zodat de inhoud er
niet onder verdwijnt. Dat onderscheid is één regel CSS:
`.brand-site:has(.brand-site-hero)` geeft de balk een negatieve marge.

**De vervaging staat er altijd op**, ook bovenaan de pagina, en alleen haar
kleur en de schaduw veranderen bij het scrollen. `backdrop-filter` laat de
browser een eigen compositielaag maken; zat die in de klasse die bij het
scrollen omklapt -- en dat zat hij ooit -- dan wordt die laag telkens
opgebouwd en weer weggegooid, en dat ziet eruit als trillende tekst in het
menu.

**De vervaging zit op de pil en niet op `<header>`.** Een `backdrop-filter`
maakt van zijn element het kader voor alles wat erin `position: fixed`
staat. Op de header zou het mobiele menu, dat het hele scherm hoort te
vullen, gevangen zitten in een balk van vier rem.

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
omslag te maken heeft. Hij staat óók als mediaquery in de bestanden onder
`resources/css/site/` en als `matchMedia` in `opEenBreedScherm()` en in
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

#### Het menu op mobiel gaat open als een diafragma

Het menu vult het hele scherm en gaat open vanuit de knop waar je op tikte:
een cirkel die vanaf die plek groeit (`clip-path: circle()`), dezelfde
beweging als de iris in de hero. De regels komen er met een stagger
achteraan, groot, zodat elke regel een duim-doel van minstens 52 pixels is.
Kan het niet allemaal op het scherm, dan schuift het menu zelf
(`data-lenis-prevent`).

Verder drie dingen die er eerst niet waren: het gaat dicht met Escape, het
gaat dicht bij **elke** link -- ook Dashboard, dat eerder alleen bleef
staan -- en de pagina eronder staat stil zolang het open is. Zonder dat
laatste scrol je achter een menu dat het hele scherm vult, en sta je na het
sluiten ergens anders dan waar je was.

Op mobiel is er geen schuivende markering maar een lichtstreepje links van
de actieve regel. Een glijdende onderstreping werkt niet in een kolom, en
een tweede mechaniek bouwen voor hetzelfde idee is meer code dan het waard
is.

### De lijn boven de werkwijze

Een lichtpad dat zich door de stappen tekent terwijl je scrolt, met stappen
die oplichten zodra het licht ze passeert. Zie `tekenPad()` en
[werkwijze](modules/werkwijze.md) voor de vormen per aantal stappen.

**De stappen worden gemeten, niet geteld.** Waar stap drie oplicht hangt
af van waar hij staat. Zou je delen door het aantal, dan klopt het alleen
bij gelijke afstanden -- en bij een laatste stap met een langere tekst al
niet meer.

### Wat op de muis reageert

Alles hieronder alleen op een apparaat met een fijne aanwijzer
(`heeftMuis()`), en nooit onder reduced motion:

- de gloed achter de hero verschuift met de cursor mee (`volgDeMuis()`);
- de certificaattegels kantelen een paar graden met een glans die meeloopt
  (`kantelKaarten()`);
- het paneel van de diensten, de projectkaarten en het LinkedIn-blok krijgen
  een spotlicht op hun rand (`spotlicht()`, `.brand-site-spot`);
- een projectkaart met een beeld toont een kleine preview die de cursor
  volgt.

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

### Eén taal voor alles wat je aanraakt

Knoppen, tekstlinks, pijlen, kaarten, menu-items en de regels in een lijst
reageren op dezelfde manier en in hetzelfde tempo. De afspraken staan in
`basis.css` en gelden voor elke module:

| Wat       | Hoe                                                                                | Waarom                                      |
| --------- | ---------------------------------------------------------------------------------- | ------------------------------------------- |
| Hover     | `--site-duur-hover` (200ms), alleen in `@media (hover: hover) and (pointer: fine)` | Op touch blijft een hover na een tik hangen |
| Indrukken | `--site-duur-druk` (120ms), `translateY(1px) scale(.98)` of een lichter vlak       | Moet voelen als meegeven, niet als animatie |
| Focus     | 2px cyaan, 2px lucht, voor elk `a`/`button`/`[tabindex]` via `:where()`            | Toetsenbord even duidelijk als de muis      |
| Pijl      | Schuift 3px in zijn eigen richting (↗ schuin)                                      | Zegt waar je heen gaat                      |
| Maat      | Nooit vetter of meer opvulling op hover                                            | Anders verschuift alles ernaast             |

**Wat niet klikbaar is, krijgt geen knopuitstraling.** Een badge, jaartal
of label licht niet op en krijgt geen handje. Eén uitzondering, bewust: de
onderdelen van een dienst in het dienstenpaneel (Monitoring, Back-ups, ...)
krijgen onder de muis een **kijkreactie** -- een lichtpunt dat aangaat, de
buren een tint zachter -- maar geen lift, geen indrukken en geen cursor.
Het zegt "dit hoort bij de dienst", niet "klik hier".

Wat er per plek gebeurt:

- **Menu in de kop:** een zacht vlak achter het woord onder de muis, iets
  lichter bij indrukken; de lichtstreep blijft voor het onderdeel waar je
  bent.
- **Knoppen:** de pijl erin schuift mee; de glazen knop krijgt een cyaan
  rand.
- **Tekstlinks (`.brand-site-link`):** de streep groeit van links, de pijl
  schuift; bij focus hetzelfde plus de rand.
- **Projectkaarten en lijstregels:** zie [projecten](modules/projecten.md).
- **Inhoudsopgave op /privacy:** het hoofdstuk waar je leest licht op
  (IntersectionObserver, geen scrollluisteraar); een klik scrollt via Lenis
  en zet het anker met `replaceState(history.state, ...)`.

Bestaande `:hover`-regels in de site-bestanden zijn in één keer in die
mediaquery gezet; een losse `:hover` op het hoogste niveau hoort er niet
meer bij te komen (zie [valkuilen](../development/valkuilen.md)).

### De vervolgpagina's

Rustig, met één klein moment per pagina, en geen hero-entree: je komt er
met een klik, niet van de straat.

- **/over-mij:** het portret gaat bij binnenkomst één keer open als een
  diafragma (CSS, 0,9 s); het verhaal staat op een leesbare regel (40rem),
  met de punten op een breed scherm als kolom ernaast.
- **/contact:** het visitekaartje blijft naast het formulier staan terwijl
  je invult (sticky vanaf 64rem).
- **/privacy:** een inhoudsopgave die op een breed scherm naast de tekst
  blijft staan; de pulserende bolletjes staan daar stil.
- **/werkwijze**, **/projecten** en een projectpagina: zie de moduledocs.

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
npm run check         # vp check: lint en opmaak (oxlint en oxfmt)
npm run check:fix     # hetzelfde, en meteen repareren
```
