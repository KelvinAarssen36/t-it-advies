# Prestaties van de publieke site

De eigenaar merkte "af en toe haperingen". Dit document legt vast wat er
gemeten is, wat de oorzaken waren, wat er is hersteld, en wat bewust is
gebleven. Lees het voordat je een effect toevoegt dat bij het laden of
tijdens het scrollen werk doet: de meeste fouten hieronder waren in de code
onzichtbaar en kwamen pas boven toen ze gemeten werden.

**De belangrijkste uitkomst: de haperingen kwamen niet van localhost.** Ze
traden in de ontwikkelmodus (Vite) én in een lokaal geserveerde
productiebuild op, even groot. De ontwikkelmodus maakt alleen de eerste
paginalaad trager (honderden losse modules in plaats van een bundel); voor
het scrollen maakt het geen verschil.

## Hoe er gemeten is

- **Twee servers op dezelfde testdata.** De ontwikkelmodus, en een kopie
  van de werkmap met een echte `vite build`, geserveerd met `php -S` op een
  eigen poort. De omgeving van de eigenaar bleef ongemoeid: geen
  `npm run build` in de echte map, geen `public/hot` weggehaald.
- **Headless Chromium via Playwright**, met de DevTools-protocollen: een
  trace (`devtools.timeline`) die per gedwongen opmaakberekening de
  JavaScript-regel geeft, Long Animation Frames met toerekening per script,
  CPU-profielen, heap snapshots, en `Emulation.setCPUThrottlingRate` voor
  een viermaal tragere processor.
- **Echt scrollen**: muiswiel langzaam (120 px per stap) en snel (700 px),
  heen en terug, en op mobiel echte veegbewegingen via touch-events -- niet
  alleen `scrollTo`.

**Beperkingen, eerlijk:** headless Chromium tekent met software in plaats
van met een GPU, dus de rasterkosten zijn veel hoger dan op een echt
toestel, en "4x CPU" vertraagt alleen de processor, niet het geheugen of de
GPU. Een geëmuleerde telefoon is geen telefoon. De getallen hieronder zijn
voor vergelijken (voor/na, dev/prod) en niet als belofte voor een toestel.
`php -S` stuurt geen cache-headers en geen compressie, dus een "tweede
bezoek" haalt daar alles opnieuw op; daarover zegt deze meting niets.

## Wat er gevonden en hersteld is

### 1. Elk binnenkomend lettertype splitste alle koppen opnieuw

De grootste hapering, en de enige die je bij gewoon gebruik voelde: ruim
een seconde na het laden, precies als je begint te scrollen, stond de
pagina twee keer ±800 ms stil (1x CPU; bij 4x meer dan drie seconden).

- **Oorzaak.** SplitText met `type: 'lines'` en `autoSplit: true` hangt bij
  elke splitsing een luisteraar aan `document.fonts` ('loadingdone'). Die
  splitst opnieuw bij **elk** lettertype dat klaar is, ook als het in die
  kop niet voorkomt. Tien gesplitste koppen, en elke hersplitsing meet de
  regels opnieuw op en bouwt de animatie opnieuw.
- **Waarom het laat gebeurde.** Het lettertype dat dan binnenkwam was
  Instrument Sans -- het portaallettertype. `<html>` (Tailwind-basis) en
  `<body class="font-sans">` hebben dat lettertype, en alleen de witruimte
  daarin was genoeg om het te laten laden.
- **Herstel.**
    - `splitsRegels()` en `stopRegels()` in `motion.ts`: haalt de
      lettertype-luisteraar van SplitText na elke splitsing weg en hangt één
      eigen luisteraar op, die alleen de koppen opnieuw splitst waarvan het
      eigen lettertype (familie en gewicht) binnenkwam. `splitReveal` en
      `kopOpkomst` gebruiken hem.
    - `html.brand-site-pagina` (gezet door Blade, bijgehouden door
      PublicLayout) zet `<html>` en `<body>` in Geist op de publieke site. Het
      portaal houdt Instrument Sans.
    - Koppen die meer dan twee schermen weg staan worden pas gesplitst als ze
      één scherm onder de onderrand zijn (IntersectionObserver), niet allemaal
      tegelijk bij het laden.
- **Gemeten (prod, 1x).** Eerste wielscroll na het laden: van twee lange
  taken van ±800 ms naar geen enkele. Laadtaak: van 800–1120 ms naar
  ±460 + 150 ms.

### 2. Een `:has()` op de wortel liet elke DOM-wijziging de hele site doorrekenen

- **Oorzaak.** `.brand-site:has(.brand-site-hero) .brand-site-kopbalk` in
  `kop.css`. Een `:has()` op een groot voorouder met het onderwerp eronder
  laat de browser bij elke wijziging in de pagina (`allDescendantsMightBeInvalid`)
  de hele site opnieuw doorrekenen: 3640 elementen, twee keer per kop die
  SplitText opdeelde. Een losse `span` invoegen bleef klein; een splitsing
  niet.
- **Herstel.** PublicLayout zet `data-met-hero` op de wortel
  (`markeerHero()`), op het moment dat de nieuwe pagina binnenkomt.
  Daarbij een valkuil: `before-enter` komt vóórdat Vue het nieuwe element
  invoegt, dus er wordt gekeken in het element dat Vue meegeeft, niet in de
  wortel.
- **Gemeten.** Een splitsing tijdens het scrollen: van 226 ms over 3654
  elementen naar 7 ms over 9.

Kleine `:has()` op een kaart (`.brand-site-projectkaart:has(...)`) zijn
geen probleem; het gaat om een `:has()` op `html`, `body` of de wortel.

### 3. De oude pagina bleef na elke navigatie in het geheugen

- **Oorzaak.** `ScrollTrigger.batch` maakt per callback meteen een
  gepauzeerde `gsap.delayedCall`. Gaat de batch nooit af -- je klikt naar
  een project voordat je bij de certificaten bent -- dan blijft die voor
  altijd in de tijdlijn staan. Hij houdt de callback vast, de callback de
  elementen, en via `__vueParentComponent` de hele vorige pagina. De
  triggers killen ruimt hem niet op.
- **Herstel.** `bundel()` in `motion.ts`: dezelfde batch, maar de
  opruimfunctie killt ook die delayedCalls. Alle aanroepen gebruiken hem;
  `ScrollTrigger.batch` staat alleen nog in `bundel()`.
- **Gemeten (vijf keer naar een project en terug, drie taalwissels, na
  geforceerde garbage collection).** Knopen: van 3639 → 25.348 naar stabiel
  ±3650. Heap: van 27 → 53 MB naar 31 MB. ScrollTriggers en tweens bleven
  ook stabiel.

### 4. Lenis scrolde na GSAP, en dat dwong extra doorrekeningen af

- **Oorzaak.** Lenis stond achteraan de GSAP-ticker: GSAP schreef eerst
  alle transforms, daarna scrolde Lenis met `scrollTo` (wat de browser die
  verse stijlen meteen laat doorrekenen), daarna las ScrollTrigger.
- **Herstel.** `gsap.ticker.add(ticker, false, true)`: Lenis vooraan.
  Daarnaast leest `trackScrollProgress` (de voortgangsbalk) de positie in
  het scroll-event, op een schone pagina, en niet meer in het animation
  frame.
- **Gemeten.** De gedwongen doorrekening uit `setScroll` (955 per
  doorloop) en die uit de voortgangsbalk (±1100) zijn weg.

### 5. De ring in de hero liet de bovenkant van de pagina constant herverven

- **Oorzaak.** GSAP draaide een SVG-`<g>` met `svgOrigin`; op SVG schrijft
  dat het `transform`-attribuut, en dat is elk beeldje opnieuw verven en
  rasteren -- ook als de pagina stilstaat: 3,2 s rasterwerk per 3 s.
- **Herstel** (HeroSection, door de hoofdsessie). De ring draait nu als
  eigen `<svg>` met een CSS-transform, met `will-change: transform` op dat
  ene element.
- **Gemeten.** Stilstaand bovenaan: geen verf- of rasterwerk meer.

### 6. Een blur over het hele scherm tijdens de welkomstanimatie

De achtergrond van de hero stelde scherp van `blur(10px)` naar scherp, in
1,6 seconde. Dat viel samen met het diafragma van de welkomstanimatie en de
kop die opkomt. Gemeten op de gebouwde site (1x CPU, desktop, eerste
bezoek, scrollen tijdens de entree): **22 tot 37 procent** van de beeldjes
in de hero te laat, p95 tijdens de entree 33 ms. Zonder die blur -- de foto
schuift nu alleen van iets te dichtbij op zijn plek -- **0 tot 3 procent**,
p95 16,8 ms. Het scherpstellen doet de lens van de entree zelf, op één
klein beeld. Zie "De intro bij binnenkomst" in
[frontend en animatie](frontend-en-animatie.md).

Meetscripts: `intro-frames.cjs` (beeldjes tijdens de entree, met
`Emulation.setCPUThrottlingRate`) en `frames.cjs` met en zonder entree
(`NOINTRO=1`).

### 7. De welkomstanimatie begon tijdens het opstarten van de pagina

Bij het laden zet de pagina al haar onderdelen klaar: een lange taak en
daarna nog een kortere. Begon de entree meteen, dan viel die tweede taak
(150 ms bij 4x CPU) in de eerste streep van de ring. Nu wacht de entree op
het eerste rustige moment (`requestIdleCallback`, hoogstens 350 ms); tot
dan staat alleen het donkere veld, dat ook de eerste stand van de entree
is. De eerste, lange taak valt vóór het eerste beeld van de laag en is dus
niet als hapering te zien.

## Voor en na, onder dezelfde omstandigheden

Productiebuild, desktop 1440×900 tenzij anders vermeld, 4x CPU.

| Meting                                            | Voor            | Na              |
| ------------------------------------------------- | --------------- | --------------- |
| Langzaam scrollen: langste beeldje                | 3400 ms         | 150 ms          |
| Langzaam scrollen: lange beeldjes samen / langste | 12,5 s / 4,6 s  | 0,84 s / 105 ms |
| Snel scrollen: langste beeldje                    | 3383 ms         | 283 ms          |
| Dev, snel scrollen: langste beeldje               | 3500 ms         | 200 ms          |
| Mobiel (390, vegen), snel: langste / lange samen  | 2266 ms / 8,8 s | 100 ms / 0,37 s |
| LCP (heldenbeeld)                                 | 5,4 s           | 2,3 s           |
| CLS                                               | 0               | 0               |

Bij 1x CPU scrolt de site op desktop en mobiel vrijwel volledig op 60
beeldjes per seconde (p95 16,8 ms), zonder lange beeldjes.

## Ronde 7: de nieuwe onderdelen nagemeten (15 oktober)

Gemeten op twee lokaal geserveerde productiebuilds naast elkaar, om en om
en meerdere keren: de vastgelegde code (HEAD, via `git archive`) en de
nieuwe, met dezelfde testdatabase en dezelfde scripts (`perf.cjs`,
headless Chromium, `Emulation.setCPUThrottlingRate`).

| Situatie                  | HEAD (voor)                         | Nu (na)                             |
| ------------------------- | ----------------------------------- | ----------------------------------- |
| Desktop 1x, snel          | 3–4% trage beeldjes, max 33–50 ms   | 5–6%, max 33–50 ms                  |
| Desktop 4x, langzaam (3×) | 59–60%, max 133–150, LoAF 758–1097  | 59–61%, max 100–133, LoAF 684–961   |
| Desktop 4x, snel (3×)     | 50–53%, max 167–250, LoAF 2095–2253 | 53–55%, max 167–217, LoAF 2268–2358 |
| Mobiel 4x, snel (3×)      | 6–7%, max 83–100, LoAF 166–242      | 6–7%, max 67–83, LoAF 172–223       |
| CLS                       | 0                                   | 0                                   |

**Wat dit zegt.** Bij gewoon gebruik (1x) en op mobiel is er geen
meetbaar verschil. Bij snel scrollen op een vier keer tragere processor
kost de nieuwe versie op desktop ongeveer vijf procent meer tijd in lange
beeldjes; dat is klein en ligt aan de rand van de spreiding tussen runs.
Een verbetering is het niet, en zo staat het hier ook.

**Wat onderweg is gevonden en hersteld** (eerst gemeten, dan opgelost):

- **Het kompas** in de mobiele kopbalk stond op desktop verborgen in de
  pagina en wisselde toch bij elk onderdeel van naam; Vue's overgang
  rekende dan de opmaak door (`FrameRequestCallback` met gedwongen layout
  in het profiel). Nu bestaat hij alleen op een telefoon.
- **De lens in de voet** had een `drop-shadow` op een ring die bij elke
  scrollstap verandert: elk beeldje een filter opnieuw schilderen. Op
  mobiel ook zonder naloop (`scrub: true`). Mobiel ging daarmee van LoAF
  227–449 ms naar 172–223 ms, gelijk aan HEAD.
- **Vooraf ophalen** van projectpagina's (`prefetch`) hield met de
  standaardcache van dertig seconden een hele voorpagina in het geheugen
  (±3200 knopen extra na twee rondes heen en terug). Met
  `cache-for="1500"` blijft het aantal knopen gelijk.

**Wat niet nieuw is:** het aantal luisteraars groeit met ongeveer twintig
per navigatie, voor en na. Dat is `gsap.matchMedia()`: zijn `add()` hangt
een luisteraar aan een `MediaQueryList` die `revert()` nooit weghaalt
(gezien in `gsap-core.js`). Er hangt geen DOM aan.

## Wat bewust is gebleven

- **De knopglans (`.brand-glans`).** Een eindeloze CSS-animatie; omdat de
  GSAP-ticker (met Lenis) elk beeldje een hoofdframe aanvraagt, krijgt een
  lopende CSS-animatie daarin elk beeldje een kleine stijlstap. Gemeten
  ±1,5% van de processor bij 1x, en hetzelfde met één of met zes knoppen.
  Geen hapering.
- **ScrollTrigger werkt twee keer per beeldje bij**: via zijn eigen
  scroll-luisteraar en via Lenis. Lenis eruit halen spaart ±0,4 ms per
  beeldje, maar dan lopen scrub-animaties (de werkwijze-scène) één beeldje
  achter op de inhoud. Dat is zichtbaar; de 0,4 ms niet.
- **Gecomposite lagen (70–90).** De helft komt door "overlap": inhoud ligt
  boven het vaste lichtveld. Zonder eigen laag zou dat lichtveld bij elke
  scrollstap opnieuw geverfd worden. Kost ±1,3 ms per beeldje bij 1x.
- **Rasterwerk in Projecten, Ervaring en Certificaten** (1,4–2,2 s per
  seconde scrollen, headless). Komt van bedoelde animaties: de clip-path
  van het casusbeeld, schaal en doorzichtigheid in de carrousel, de
  kanteling van de tegels. Het draait op de rasterdraden en niet op de
  hoofddraad, en is op een toestel met GPU-raster veel goedkoper.
- **`gsap.matchMedia`** hangt bij elke `add()` een luisteraar aan een nieuwe
  MediaQueryList en haalt die nooit weg, ook niet bij `revert()`. Dat is
  ±20 kleine objecten per bezoek aan de voorpagina, zonder DOM erachter.
  Gedrag van de bibliotheek.
- **De CSS is ±450 KB onverpakt** op de publieke site, omdat de stijlen van
  het portaal meekomen. Verpakt een fractie daarvan; splitsen is een
  grotere verbouwing.

## Opnieuw meten

De meetscripts staan niet in de repository (ze horen bij de sessie die
dit onderzocht). Wat ze deden is na te bouwen met Playwright en een
CDP-sessie; de belangrijkste vragen om te stellen:

1. Long Animation Frames tijdens langzaam en snel scrollen, met
   `scripts[].sourceFunctionName` en `forcedStyleAndLayoutDuration`.
2. In een trace: welke `UpdateLayoutTree` heeft een `stackTrace`
   (gedwongen) en hoeveel elementen (`elementCount`) rekent hij door.
3. Na herhaald navigeren en geforceerde garbage collection: blijven
   `Nodes` en `JSHeapUsedSize` gelijk?
4. Stilstaand op een paar hoogtes: is er verf- of rasterwerk? Dan draait er
   iets eindeloos.

Zie ook [frontend en animatie](frontend-en-animatie.md) en
[valkuilen](../development/valkuilen.md).
