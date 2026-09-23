# Frontend en animatie

## Opbouw

Vue 3 met TypeScript, via Inertia aan Laravel gekoppeld. Pagina's staan in
`resources/js/pages` en komen overeen met wat de controller aan
`Inertia::render()` meegeeft. Layouts worden centraal toegewezen in
`resources/js/app.ts`:

- `Welcome` krijgt geen layout (dat is de publieke site met een eigen opzet).
- Alles onder `auth/` krijgt `AuthLayout`.
- Alles onder `settings/` krijgt `AppLayout` plus de instellingen-layout.
- De rest krijgt `AppLayout`, inclusief het beveiligde gedeelte onder `admin/`.

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

De UI-componenten in `resources/js/components/ui` komen van reka-ui. Pas die
bij voorkeur niet aan: maak een eigen component eromheen als je afwijkend
gedrag nodig hebt.

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
die aan in `onBeforeUnmount`. Zie `Welcome.vue` voor het patroon.

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
