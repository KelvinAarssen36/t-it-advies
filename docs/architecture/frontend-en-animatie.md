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

## Afbeeldingen

Er zijn twee plekken, en het verschil is wie het bestand erin zet.

| Plek                  | URL              | Waarvoor                                                    |
| --------------------- | ---------------- | ----------------------------------------------------------- |
| `public/images/`      | `/images/<naam>` | Vaste beelden die met de code meegaan: logo, og-afbeelding. |
| `storage/app/public/` | `/storage/<pad>` | Alles wat de klant later zelf uploadt.                      |

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

| Bestand                                      | Waarvoor                                                |
| -------------------------------------------- | ------------------------------------------------------- |
| `public/favicon.ico`                         | Het tabblad-icoon                                       |
| `public/images/favicon.ico`                  | Dezelfde, en dit is de versie waar de pagina naar wijst |
| `public/apple-touch-icon.png`                | 180x180, wat iOS ophaalt                                |
| `public/images/hero-achtergrond.webp`        | De hero-achtergrond, 1672 px                            |
| `public/images/hero-achtergrond-mobiel.webp` | Dezelfde foto, 900 px, voor telefoons                   |
| `public/images/hero-achtergrond-groot.webp`  | Dezelfde foto, 3344 px, opgeschaald en verscherpt       |
| `public/images/og-afbeelding.jpg`            | Wat sociale media tonen bij een link                    |
| `public/images/*.png`                        | De aangeleverde bronbestanden                           |

**Zet nooit een aangeleverde PNG rechtstreeks op de pagina.** De
hero-achtergrond kwam binnen als PNG van 1,2 MB; als WebP is dat 70 kB, en
de mobiele versie 21 kB. Op een telefoon met slecht bereik is dat het
verschil tussen een site die laadt en een bezoeker die wegklikt.

Er staat geen beeldbewerking in het project. Converteren doe je met een
eenmalig script; hoe dat is gegaan staat in de commit waarin deze bestanden
zijn toegevoegd.

**Kwaliteit 92 en niet lager**, terwijl 82 gemeten nauwelijks slechter is
(41,2 tegen 42,8 dB PSNR, allebei ruim boven wat een oog ziet). De reden is
banding: grote donkere verlopen zijn het slechtste geval voor WebP, en dat
is precies wat deze foto is. PSNR meet dat slecht, je oog niet.

**Een foto wordt nooit scherper dan zijn bron.** Deze is 1672 px breed. Op
een scherm van 1920 wordt hij dus al 15% opgerekt, op een 2560-monitor 53%,
en op een scherm met dubbele pixeldichtheid nog veel meer. Dat is geen
instelling die je kunt bijdraaien: vraag een grotere bronfoto. Voor een
hero die de volle breedte vult wil je er minstens 2560 px, liever 3840 px.

### De opgeschaalde variant

Zolang die grotere bron er niet is, staat er een variant van 3344 px:
tweemaal opgeschaald en daarna verscherpt met een milde convolutiekern.

Dat maakt geen nieuwe details -- die zitten niet in de bron. Het haalt het
oprekken alleen weg bij de browser. Een browser rekt op zonder te
verscherpen; wij rekken op, verscherpen de randen, en de browser schaalt
vervolgens terug. Terugschalen ziet er altijd scherper uit dan oprekken, en
de verscherping blijft daarbij behouden.

De kern is bewust mild (2,0 in het midden, deler 1,2). Een sterkere geeft
lichte randen om donkere vormen, en op een egale navy achtergrond valt dat
meteen op.

Vervang dit zodra er een echte bron van 3000 px of meer is: verscherpen is
een pleister, geen oplossing.

### Drie maten, de browser kiest

De hero gebruikt `srcset` met breedtes en `sizes="100vw"`:

| Bestand                        | Breedte | Grootte |
| ------------------------------ | ------- | ------- |
| `hero-achtergrond-mobiel.webp` | 900     | 21 kB   |
| `hero-achtergrond.webp`        | 1672    | 70 kB   |
| `hero-achtergrond-groot.webp`  | 3344    | 180 kB  |

Daarmee telt ook de pixeldichtheid mee, en dat is precies wat je wilt: een
telefoon met een scherm van 375 px en drievoudige dichtheid heeft 1125 px
nodig en krijgt dus de middelste, niet de kleinste. Met alleen een
`media`-regel in een `<picture>` zou die telefoon een opgerekte 900 px
krijgen.

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

De animatielaag zit in [`resources/js/lib/motion.ts`](../../resources/js/lib/motion.ts)
en biedt drie dingen:

```ts
prefersReducedMotion(): boolean
startSmoothScroll(): () => void      // geeft een opruimfunctie terug
revealOnScroll(selector, scope):  () => void
```

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
