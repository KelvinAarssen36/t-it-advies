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
