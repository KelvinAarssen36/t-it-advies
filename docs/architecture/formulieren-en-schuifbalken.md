# Formulieren en schuifbalken

**Dit is de standaard van dit project, geen suggestie.** Elk invoerveld, elk
keuzeveld en elk gebied dat kan schuiven in het portaal gebruikt wat hieronder
staat. Een kale `<select>` of een ongestylde schuifbalk is een fout, ook in een
scherm dat "maar even" is.

De reden is dat dit de onderdelen zijn die de browser standaard zélf tekent.
Laat je ze met rust, dan staat er midden in een donker portaal een grijze
systeemschuifbalk en een keuzeveld met een systeemrandje. Dat valt harder op
dan een verkeerde kleur, want het is niet alleen de verkeerde kleur -- het is
zichtbaar een ander programma.

## Kleuren komen uit één blok

Geen enkel veld en geen enkele schuifbalk noemt een kleur rechtstreeks. Alles
leest de besturingsvariabelen uit `resources/css/app.css`. Die staan één keer
in `:root` en één keer in `.dark`, en verder nergens.

| Variabele                 | Waarvoor                                                             |
| ------------------------- | -------------------------------------------------------------------- |
| `--control-bg`            | De achtergrond van een veld dát in de pagina zit. Mag doorzichtig.   |
| `--control-bg-solid`      | Dekkend. Voor regels die over andere inhoud vallen.                  |
| `--control-bg-elevated`   | Dekkend, iets lichter. Voor panelen die boven de pagina zweven.      |
| `--control-border`        | De rand in rust.                                                     |
| `--control-fg`            | De tekst.                                                            |
| `--control-muted`         | Gedempte tekst: pijltjes, placeholders, uitgeschakelde velden.       |
| `--control-hover`         | Achtergrond onder de muis, en onder de pijltjestoetsen.              |
| `--control-active`        | Achtergrond van de gekozen regel.                                    |
| `--control-ring`          | De focusring. In het donker Cyan, want Electric Blue verdwijnt daar. |
| `--scrollbar-track`       | De baan. Bij ons doorzichtig, in beide thema's.                      |
| `--scrollbar-thumb`       | De duim in rust.                                                     |
| `--scrollbar-thumb-hover` | De duim onder de muis.                                               |

Let op het verschil tussen `-solid` en `-elevated`. Half doorzichtig is prima
op een veld dat in de pagina ligt, en rampzalig op een paneel dat erboven
zweeft: dan lees je de tekst eronder dwars door je opties heen. Elk zwevend
paneel gebruikt `-elevated`.

Een nieuw thema is hierdoor een blok van vijftien regels, geen ronde langs
alle componenten. Voeg je een besturingselement toe dat een kleur nodig heeft
die er nog niet is, zet die dan in beide blokken en in de tabel hierboven --
niet in het component zelf.

De ronding komt uit `--radius-xl`, die in `@theme inline` is afgeleid van
`--radius`. Velden, keuzeknoppen en het paneel dat eruit klapt delen die ene
waarde, zodat ze naast elkaar één familie vormen.

## Waarom geen `!important`

Dit is de valkuil van dit hoofdstuk, dus hij staat vooraan.

De componenten uit de UI-pakketten zetten hun eigen Tailwind-klassen op
hetzelfde element. Die staan in de **utilities**-laag. Een regel die je in
`@layer components` schrijft verliest het daarvan, hoe specifiek hij ook is,
want met echte CSS-lagen wint de laag en niet de specificiteit. Dat is de
reden dat een eerste poging al snel vol `!important` staat.

De uitweg hier is `@utility`, met de toestanden als gewone regels eronder:

```css
@utility brand-control { … }
.brand-control:hover:not(:disabled) { … }
.brand-control:focus-visible { … }
```

`@utility` zet de basis in de utilities-laag, en de regels daaronder staan
buiten elke laag -- en ongelaagde CSS wint van gelaagde. Zo krijg je precies
wat je wilt, zonder één `!important` in het hele bestand. Dezelfde constructie
gebruikt `brand-scrollbar`.

## Besturingselementen

### `brand-control`

Eén utility voor het vlak waar je op klikt of in typt. Hij zit op
[`Input.vue`](../../resources/js/components/ui/input/Input.vue) én op de knop
van [`BrandSelect.vue`](../../resources/js/components/BrandSelect.vue), zodat
een filterrij met een zoekveld naast een keuzeveld één geheel is en niet twee
componenten die toevallig naast elkaar staan.

Wat hij regelt, en waarom:

- **Geen `display`.** Dit zit op een `<input>` én op een knop. Die eerste
  centreert zijn tekst vanzelf; zou hier `flex` staan, dan gaat die
  uitlijning eraan. De knop zet er zelf `inline-flex` bij.
- **Focus als schaduw, op `:focus-visible`.** De outline weghalen mag alleen
  als er iets voor terugkomt: een veld zonder zichtbare focus is onbruikbaar
  met een toetsenbord. `:focus-visible` en niet `:focus`, anders krijgt een
  muisklik ook een ring en leest dat als een foutmelding. En een schaduw en
  geen dikkere rand, want een rand verandert de afmeting en duwt de rest van
  het formulier opzij.
- **`[data-state='open']` deelt de focusstijl.** Staat het paneel open, dan
  hoort de knop eronder er ook actief uit te zien.
- **`-webkit-text-fill-color` bij `:disabled`.** Browsers leggen hun eigen
  grijs over een uitgeschakeld veld. Zonder deze regel is dat in het donker
  vrijwel onleesbaar.
- **`[aria-invalid='true']`** kleurt de rand en de schaduw rood. Dat staat in
  CSS en niet als Tailwind-klasse, omdat een schaduw met `color-mix` erin
  niet in een arbitrary value past zonder onleesbaar te worden.

### `BrandSelect`

Gebruik [`BrandSelect.vue`](../../resources/js/components/BrandSelect.vue) voor
elke keuze uit een lijst:

```vue
<BrandSelect
    v-model="status"
    aria-label="Filter op status"
    class="max-w-xs"
    :options="[{ value: '', label: 'Alle statussen' }, ...statuses]"
/>
```

Opties zijn een prop en geen slot met `<option>`-elementen: de lijst wordt
door ons getekend, dus er valt niets aan een `<option>` te ontlenen.

**Waarom niet het inheemse `<select>`.** Dat is een bewuste ommezwaai. Een
`<select>` geeft je toetsenbordbediening, telefoongedrag en toegankelijkheid
gratis, en dat is een sterk argument -- maar alleen het veld zelf is te
stylen. De lijst die eruit klapt tekent het besturingssysteem: vierkante
hoeken, een felblauwe selectiebalk en een grijze systeemschuifbalk, midden in
een donker portaal. Daar is met CSS niets aan te doen; het is geen kwestie van
de goede selector vinden, die elementen bestaan simpelweg niet in de pagina.
Voor een portaal dat het van zijn afwerking moet hebben, weegt dat zwaarder
dan het gemak.

Wat we ervoor inleveren is het wieltje dat een telefoon onderaan het scherm
toont. Reka-ui vangt dat op met een lijst die met een vinger te bedienen is en
die de toetsenbordbediening nabouwt: typen springt naar een optie, de pijltjes
lopen door de lijst, Escape sluit.

**De lege waarde.** Reka-ui weigert een optie met een lege waarde -- die is
bij hem gereserveerd om de keuze te wissen. Onze filters gebruiken juist een
lege waarde voor "alles", omdat die dan uit de URL blijft. `BrandSelect`
vertaalt tussen die twee: binnen het paneel heet hij `__alles__`, daarbuiten
is en blijft het een lege string. Daar hoef je op de aanroepplek niets van te
weten.

**Een label hoort erbij.** Staat er geen zichtbaar label naast, geef dan
`aria-label` mee. Een keuzeveld waarvan een schermlezer alleen de gekozen
waarde voorleest, zegt niets over waar die waarde over gaat.

### Hoe het veld in elkaar zit

Het component stapelt zes onderdelen van reka-ui op elkaar. Ze doen allemaal
iets, en een ervan weghalen breekt meer dan je verwacht:

| Onderdeel                          | Wat het doet                                                                  |
| ---------------------------------- | ----------------------------------------------------------------------------- |
| `SelectRoot`                       | Houdt de waarde vast en regelt toetsenbord, focus en sluiten.                 |
| `SelectTrigger`                    | De knop. Draagt `brand-control`, plus `inline-flex` voor de tekst en de pijl. |
| `SelectPortal`                     | Hangt het paneel aan `<body>`, zodat een `overflow: hidden` het niet afknipt. |
| `SelectContent`                    | Het paneel zelf: `brand-panel`, een maximale hoogte en de open-animatie.      |
| `SelectViewport`                   | Het element dat **schuift**. Hier zit `brand-scrollbar` op.                   |
| `SelectScrollUp`- en `-DownButton` | De pijltjes boven en onder, die verschijnen als er meer is.                   |

**Welk element schuift, is het hele verhaal.** Dat is het viewport en niet
het paneel eromheen. Het viewport staat bij reka-ui op `flex: 1` met
`overflow: hidden auto`, dus het paneel moet een flexkolom met een begrensde
hoogte zijn (`flex flex-col overflow-hidden` plus een `max-h`). Is het dat
niet, dan krijgt het viewport nooit een hoogte en schuift het paneel als
geheel. Dat oogt bijna goed, maar reka-ui schuift bij de pijltjestoetsen de
gemarkeerde regel in beeld _in het viewport_ -- en als dat niet het
schuivende element is, springt de lijst met horten en stoten. Precies dat
ging hier eerst mis.

**De schuifbalk moet je terugveroveren.** Reka-ui verbergt die van het
viewport met een `<style>` die hij bij het openen in de pagina zet. Die
regel staat buiten elke CSS-laag, en ongelaagde CSS wint van gelaagde,
ongeacht de volgorde -- dus `brand-scrollbar` alléén doet daar niets tegen.
In `app.css` staat daarom één regel die hetzelfde attribuut _plus_ onze
klasse noemt en zo specifieker is:

```css
[data-reka-select-viewport].brand-scrollbar::-webkit-scrollbar {
    display: block;
    width: 0.6rem;
}
```

De duim, de baan en de ronding komen daarna gewoon uit `brand-scrollbar`.

**Vloeiend schuiven** komt van `scroll-behavior: smooth` op datzelfde
viewport, met `scroll-padding-block` zodat de gemarkeerde regel niet tegen de
rand van het paneel komt te liggen. Beide staan achter
`@media (prefers-reduced-motion: no-preference)`: vloeiend schuiven is
precies het soort beweging waar iemand met bewegingsgevoeligheid last van
heeft, en dan is springen de juiste uitkomst.

**De animatie bij openen** komt uit `tw-animate-css`: vervagen en een klein
beetje inzoomen, met een verschuiving die afhangt van de kant waar het paneel
uitklapt (`data-[side=bottom]`). Daardoor lijkt het uit de knop te komen in
plaats van erover te vallen.

**Het pijltje op de knop** draait 180 graden zodra het paneel open staat. Dat
werkt via `group-data-[state=open]`, dus de knop heeft de klasse `group`
nodig -- vergeet je die, dan gebeurt er niets en zie je niet waarom.

**Het vinkje** staat absoluut rechts in de regel, in een `<span>` met een
vaste maat. De regel zelf heeft daarom rechts extra ruimte (`padding-right`
in `brand-option`), zodat een lange optietekst er niet onder doorloopt.

**De markering luistert naar `[data-highlighted]`** en niet naar `:hover`.
Reka-ui zet dat attribuut zowel bij de muis als bij de pijltjestoetsen, dus
toetsenbord en muis krijgen dezelfde markering zonder twee regels die elkaar
in de weg zitten.

## De segmentknop

[`SegmentToggle.vue`](../../resources/js/components/SegmentToggle.vue): een
pil met een vakje per optie en een gloeiende indicator die ertussen glijdt.
Gebruikt voor de taalkeuze op de landing en voor de weergavekeuze in het
portaal.

Neem hem voor een keuze uit twee of drie opties waar alles zichtbaar mag
zijn. Bij meer opties, of als de labels lang worden, is `BrandSelect` beter
-- een pil met vijf vakken past nergens meer.

Het component levert de vorm, de aanroeper bepaalt wat er gebeurt:

```vue
<SegmentToggle
    :model-value="thema"
    :options="[
        { value: 'dark', label: 'Donker' },
        { value: 'light', label: 'Licht' },
    ]"
    :groep-label="$t('Weergave')"
    @update:model-value="kies"
>
    <template #voor="{ optie }">
        <Moon v-if="optie.value === 'dark'" class="size-4" />
    </template>
</SegmentToggle>
```

Wat erin zit en waarom:

- **De indicator wordt gemeten, niet berekend.** De vakken zijn zo breed als
  hun tekst; "Nederlands" is langer dan "English". Het component leest
  `offsetLeft` en `offsetWidth` van het actieve vak en zet die als `--vak-x`
  en `--vak-w`. Een `ResizeObserver` hermeet als het lettertype binnenkomt.
- **De eerste meting animeert niet.** Pas na twee frames krijgt de pil de
  klasse `is-ready`. Zonder dat schuift de indicator bij het laden van elke
  pagina even vanaf links naar zijn plek.
- **De indicator loopt vooruit op de aanroeper.** Is een keuze een verzoek
  aan de server, dan zou wachten op het antwoord de knop een tel lang kapot
  laten lijken. Mislukt dat verzoek, dan roep je `herstel()` aan op de ref.
- **De curve schiet een klein stukje door en veert terug.** Dat is het
  verschil tussen "de knop reageert" en "de knop leeft". Er trekt tegelijk
  één lichte veeg over de indicator. Allebei uit bij
  `prefers-reduced-motion`.
- **`groepLabel` en niet `ariaLabel`.** Die tweede naam botst met het
  HTML-attribuut `aria-label`, waardoor de waarde als los attribuut op het
  element belandt in plaats van als prop binnen te komen.

**De kleuren komen uit de besturingsvariabelen**, dus dezelfde pil klopt in
licht en donker. Dat is met schade geleerd: de eerste versie stond met vaste
donkere waarden in de code omdat hij alleen op de altijd donkere landing
stond. Zodra hij in het portaal kwam was het een wit vlak op wit.

## Schuifbalken

### De schuifbalk van de pagina zelf

Die staat op `html` en gaat vanzelf mee met het thema, want `.dark` staat op
datzelfde element. Je hoeft er niets voor te doen.

Eén uitzondering: de publieke site en de inlogschermen staan **altijd**
donker, ongeacht iemands voorkeur, en zetten `dark` op een eigen vlak in
plaats van op `html`. Die vlakken dragen daarom ook `brand-dark-page`, waarna
`html:has(.brand-dark-page)` de donkere waarden en `color-scheme: dark`
oppakt. Zonder dat krijg je op een donkere pagina een lichte schuifbalk van de
browser.

`color-scheme` staat verder gewoon in `:root` en `.dark`. Dat is wat de
browser vertelt welke kant we op zitten; zonder die regel tekent hij
keuzerondjes, datumprikkers en de schuifbalk van een `<textarea>` in zijn
eigen lichte variant, ook midden in een donker portaal.

### `brand-scrollbar`

Voor alles wat bínnen de pagina kan schuiven:

```html
<div class="brand-scrollbar max-h-80 overflow-y-auto">…</div>
```

Opt-in, en met opzet niet op `*`. Globaal zou je elk paneel raken van een
pakket dat we niet beheren, en op macOS de zwevende balken dwingen te blijven
staan.

Wat erin zit en waarom:

- **Twee standaarden onder elkaar.** `scrollbar-width` en `scrollbar-color`
  zijn de officiële: die werken breed, maar geven je precies twee knoppen --
  dik of dun, en twee kleuren. De `::-webkit-`pseudo-elementen zijn het oude,
  niet-gestandaardiseerde spul waar wél alles mee kan. Ze bijten elkaar niet;
  elke browser pakt wat hij kent. Besef wel dat je twee uitkomsten
  onderhoudt: zorg dat de eerste op zichzelf goed is en behandel de tweede
  als verfijning.
- **`scrollbar-gutter: stable`** reserveert de ruimte vóórdat er een balk is.
  Zonder dit springt de inhoud een paar pixels opzij zodra een lijst lang
  genoeg wordt, en dat gebeurt tijdens het typen in een zoekveld -- precies
  wanneer iemand kijkt.
- **`overscroll-behavior: contain`** stopt het doorschuiven naar de pagina
  eronder zodra je de onderkant bereikt. In een venster is dat anders ronduit
  verwarrend.
- **`min-height: 2.5rem` op de duim.** In een heel lange lijst wordt die
  anders een streepje van drie pixels dat je niet kunt pakken.
- **Een doorzichtige rand plus `background-clip: padding-box`.** Dat geeft
  lucht om de duim zonder een tweede element en zonder hem smaller te maken:
  hij blijft over zijn volle breedte aan te klikken. Zonder `background-clip`
  doet die rand niets, dan loopt de achtergrond er gewoon achter door.

In het donkere thema is de duim een stap lichter dan de randen
(`--brand-border-dark-strong`). Zat hij op dezelfde kleur als een rand, dan
zie je niet dat er iets te schuiven valt.

### `brand-scrollbar-none`

Verbergt de balk volledig. Gebruik dit alleen waar iets ánders al verraadt dat
er meer is -- een rij die zichtbaar doorloopt tot buiten de rand, of een knop
die verder bladert. Een verborgen schuifbalk zonder zo'n aanwijzing is inhoud
die niemand vindt.

### Waar het al op staat

| Plek                                                                                              | Wat er schuift                            |
| ------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| `html`                                                                                            | De pagina zelf.                           |
| [BrandSelect.vue](../../resources/js/components/BrandSelect.vue)                                  | De uitgeklapte lijst van een keuzeveld.   |
| [SidebarContent.vue](../../resources/js/components/ui/sidebar/SidebarContent.vue)                 | Het menu, als het langer wordt.           |
| [SelectContent.vue](../../resources/js/components/ui/select/SelectContent.vue)                    | De lijst van de kale shadcn-select.       |
| [DropdownMenuContent.vue](../../resources/js/components/ui/dropdown-menu/DropdownMenuContent.vue) | Het accountmenu en soortgelijke.          |
| [DialogScrollContent.vue](../../resources/js/components/ui/dialog/DialogScrollContent.vue)        | Een venster dat langer is dan het scherm. |
| [MailLog.vue](../../resources/js/pages/admin/MailLog.vue)                                         | De brede tabel en de context-`<pre>`.     |
| [SecurityEvents.vue](../../resources/js/pages/admin/SecurityEvents.vue)                           | De brede tabel.                           |
| [Users.vue](../../resources/js/pages/admin/Users.vue)                                             | De brede tabel.                           |
| [Dashboard.vue](../../resources/js/pages/Dashboard.vue)                                           | Het raster op een smal scherm.            |

Komt er een scherm bij, dan hoort die plek in deze tabel.

## De componenten van de UI-pakketten

Dropdown en tooltip uit reka-ui/shadcn tekenen hun zwevende paneel met
`bg-popover`. `--popover` wijst in `app.css` naar `--control-bg-elevated`, dus
ze lezen dezelfde variabele als onze eigen velden en gaan vanzelf mee met het
thema.

Kom je iets tegen dat tóch niet meekleurt, dan is het antwoord het token
koppelen of `@utility` gebruiken zoals hierboven -- niet er een uitzondering
overheen leggen. Twee bronnen voor dezelfde kleur is precies wat we hier
vermijden.

## Bij een nieuw scherm

1. Een keuze uit een lijst? `BrandSelect`, met `aria-label` als er geen
   zichtbaar label naast staat.
2. Een invoerveld? `Input`, die draagt `brand-control` al.
3. Kan er iets schuiven binnen de pagina? `brand-scrollbar` erop, en de plek
   erbij in de tabel hierboven.
4. Een kleur nodig die er niet is? Die komt in `:root` én in `.dark`, en in de
   tabel bovenaan dit document.
5. Controleer beide thema's, en controleer met het toetsenbord dat de
   focusring zichtbaar is en dat de lijst met de pijltjes te bedienen is.

Zie ook [huisstijl en kleuren](huisstijl-en-kleuren.md) voor het palet zelf en
[frontend en animatie](frontend-en-animatie.md) voor de rest van de
frontend-afspraken.
