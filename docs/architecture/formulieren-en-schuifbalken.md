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

> **En `--control-hover` mag nooit dezelfde waarde krijgen als
> `--control-bg-elevated`.** Op donker was dat een tijdlang wel zo -- beide
> `#102d4a` -- en dan is er geen hover: je kleurt naar de kleur van het
> paneel waar de regel op ligt. Hetzelfde geldt voor `--control-active` en
> voor `--accent`, waarmee de UI-pakketten hun menuregels kleuren. Zie
> [huisstijl en kleuren](huisstijl-en-kleuren.md) voor hoe dat kon gebeuren
> en hoe het nu is opgelost.

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

### Zoeken in een lange lijst

Vanaf ongeveer twaalf regels zet `BrandSelect` zelf een zoekveldje boven de
lijst. Scrollen door een lijst waarvan je het antwoord al weet is werk dat
de computer kan doen. Met `zoekbaar` zet je hem aan of uit los van dat
aantal, en `zoek-tekst` verandert het opschrift.

Drie dingen die daarbij geregeld moesten worden:

- **De aandacht gaat bij het openen naar het zoekveld** in plaats van naar
  de gekozen regel. Lukt dat niet, dan blijft reka-ui gewoon werken zoals
  altijd -- pijltjes, en typen springt naar de eerste regel die met die
  letter begint. De slechtste uitkomst is dus "je moet er eerst in
  klikken", niet een kapot veld.
- **Het zoekveld houdt de lettertoetsen zelf.** Reka-ui luistert op het
  paneel mee om naar een regel te springen zodra je typt, en dat is precies
  wat je niet wilt terwijl je in een zoekveld typt. De toetsen waarmee je
  door de lijst beweegt en hem sluit gaan wél door.
- **De gekozen optie blijft altijd staan**, ook als hij niet op de zoekterm
  past. Reka-ui leest het opschrift van de knop af van het gekozen item;
  verdwijnt dat uit de lijst, dan lijkt de keuze gewist.

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

## De knop van een handeling

Aanmaken, bewerken en verwijderen hebben elk een eigen kleur, en die staat
in [huisstijl en kleuren](huisstijl-en-kleuren.md#de-drie-handelingen). Hier
staat waar welke vorm heen gaat.

In [`button/index.ts`](../../resources/js/components/ui/button/index.ts)
staan daarvoor zes varianten: drie kleuren maal twee vormen. Ze heten net
als `bevestigAanmaken` / `bevestigBewerken` / `bevestigVerwijderen` uit
[`lib/bevestiging.ts`](../../resources/js/lib/bevestiging.ts), zodat de knop
en zijn bevestiging dezelfde naam dragen.

| Waar de knop staat                          | Vorm     | Voorbeeld                              |
| ------------------------------------------- | -------- | -------------------------------------- |
| Bovenaan een scherm, één per scherm         | vol      | "Nieuwe ervaring" -> `aanmaken`        |
| De hoofdhandeling op een detailpagina       | vol      | "Bewerken" -> `bewerken`               |
| De opslaan-knop in een venster              | vol      | `bewerkt ? 'bewerken' : 'aanmaken'`    |
| De bevestigknop in `ConfirmDialog`          | vol      | volgt de `soort` van de vraag          |
| In een tabelrij, naast tien soortgenoten    | `-zacht` | het potlood en de prullenbak in de rij |
| Een tweede handeling naast een vollere knop | `-zacht` | de prullenbak naast "Bewerken"         |

**De zachte vorm heeft in rust geen kader.** Hij is alleen zijn pictogram
of zijn tekst, in de kleur van de handeling; bij het aanwijzen komt daar
een zachte schijf in diezelfde kleur onder, met een dunne rand van binnen,
en zwelt hij een paar procent aan. Dat gedrag staat één keer beschreven, in
`.brand-knop-zacht` in [`app.css`](../../resources/css/app.css), en alle
drie de kleuren lezen het via `--knop-kleur`. Verandert de hover, dan
verandert hij overal tegelijk.

Hier stond eerst een permanente rand met een getint vlak, dat bij het
aanwijzen helemaal volliep. Dat was te veel van het goede: twee
dichtgetimmerde vakjes achter elke tabelregel lezen als een waarschuwing,
en het omslaan naar een vol vlak is een grote sprong voor een knop die je
alleen maar aanwijst.

**Waar het niet voor is.** Een knop die alleen iets in het formulier
verandert en pas bij Opslaan echt iets doet, blijft `ghost` of `outline` --
"Logo weghalen" in
[`LogoKiezer.vue`](../../resources/js/components/LogoKiezer.vue) wist een
gekozen bestand en niet een ervaring. Hetzelfde geldt voor de knoppen in de
inlog- en tweestapsschermen: dat zijn geen handelingen op een item.
"Annuleren" is overal `ghost`.

**`variant="destructive"` blijft voor gevaarlijk-maar-geen-verwijdering**,
zoals tweestapsverificatie uitzetten.

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

## De schakelaar

[`Switch`](../../resources/js/components/ui/switch/Switch.vue): een schuifje
voor "aan of uit". Gebruikt op het indelingsscherm om een onderdeel van de
website aan of uit te zetten.

**Wanneer een schuifje en wanneer een vinkje.** Het verschil is niet
cosmetisch. Een schuifje zegt: dit is een toestand, en die verandert. Een
vinkje zegt: dit is een keuze die je aankruist en later opslaat. Zet je een
schuifje neer voor iets dat pas bij "Opslaan" ingaat, dan verwacht de
gebruiker dat het al gebeurd is.

Op het indelingsscherm staat het schuifje daarom **uitgeschakeld** buiten de
bewerkmodus in plaats van weggehaald: het is een toestand die je daar ziet
en die je in de bewerkmodus kunt aanpassen. Weghalen zou bovendien de hele
regel van breedte laten verspringen zodra je gaat bewerken, en dan schuift
het scherm onder je muis weg.

Net als het keuzeveld staat hij op de primitief van reka-ui en niet op een
`<input type="checkbox">`, om de reden die hierboven bij `BrandSelect`
staat: een inheems element laat zich niet volledig in de huisstijl zetten.

## Het tekstvak

[`Textarea`](../../resources/js/components/ui/textarea/Textarea.vue) deelt
`brand-control` met het gewone invoerveld, zodat de rand, de hoek en de
focusring uit één blok komen en niet uit elkaar lopen. Daar komt
`brand-textarea` overheen voor de verticale ruimte: `brand-control` zet die
op nul, want een invoerveld centreert zijn tekst op één regel.

De schuifbalk krijgt `brand-scrollbar`. De systeemschuifbalk is grijs en
vierkant, en die valt in een donker portaal meteen op.

## Verplichte velden

**Elk verplicht veld krijgt een sterretje bij zijn label.** Dat is een
afspraak voor het hele project en niet iets dat je per scherm afweegt:
velden zonder sterretje leest de klant als optioneel, dus een vergeten
sterretje is geen schoonheidsfoutje maar onjuiste informatie.

Het staat op één plek, als prop op het labelcomponent:

```vue
<Label for="role_nl" verplicht>{{ $t('Functie') }}</Label>
```

Zou je het tekentje met de hand achter de tekst typen, dan staat het er op
het ene scherm wel en op het andere niet, en verschilt ook nog de opmaak.

Het sterretje zelf is `aria-hidden`; een schermlezer krijgt het woord
"(verplicht)" te horen. Zou het teken worden voorgelezen, dan hoor je
"ster" achter elk veld en weet je nog niets.

**Hoe je weet welke velden het zijn:** loop de `rules()` van de
bijbehorende FormRequest langs en zet er een bij alles met `required`. Die
twee horen niet uit elkaar te lopen.

Eén grens, en hij volgt uit waar het sterretje voor is. Het beantwoordt de
vraag "moet ik dit invullen?", en bij een keuzelijst die al een waarde
heeft -- het pictogram bij een ervaring bijvoorbeeld -- is die vraag al
beantwoord: leeglaten kan er niet eens. Zo'n veld is in de validatie wel
`required` maar voor de klant niets om op te letten, en dan is een
sterretje ruis. Het gaat om velden die hij zélf moet invullen.

## Datum kiezen: een maandkiezer, geen datumveld

Waar een maand en een jaar nodig zijn -- de periode van een ervaring --
staat
[`MaandKiezer`](../../resources/js/components/MaandKiezer.vue) en geen
`<input type="month">`. Dat inheemse veld tekent zijn kalender met het
besturingssysteem, precies het probleem dat hierboven bij `BrandSelect`
staat beschreven. En het geeft ook nog eens een dag terug die hier niets
betekent.

Het is één knop met "maart 2021" erop. Erachter zit een paneel met een
jaarkeuze en een raster van twaalf maanden; de waarde gaat als `"2021-03"`
naar buiten. Hiervoor stonden er twee keuzelijsten per datum, en dus vier
op één scherm -- dan moet je voor elke datum twee lijsten opentrekken om te
zien wat er staat.

**Dagen kun je niet kiezen, en dat is het punt.** Bij een loopbaan weet
niemand meer op welke dag hij ergens begon. Een veld dat om die precisie
vraagt en het antwoord daarna weggooit, is erger dan een veld dat er niet
om vraagt.

De maandnamen en de jaren komen van de **server** en niet uit `Intl` in de
browser: anders hangt de taal van de lijst af van het besturingssysteem van
de bezoeker in plaats van van de taal die hij in het portaal heeft gekozen.
Zie [vertalingen](vertalingen.md#niet-in-de-browser).

## Een bestand kiezen

Het inheemse `<input type="file">` is niet te stylen -- de knop erin tekent
de browser. Daarom staat het veld op `sr-only` en klikt een gewone
`Button` het aan.

**Niet met `display: none`.** Dan is het veld met het toetsenbord niet meer
te bereiken en verdwijnt het uit de voorleesvolgorde. `sr-only` houdt het
bruikbaar en haalt het alleen van het scherm.

Ernaast staat een voorbeeld van wat je koos. Dat is een `blob:`-adres uit
`URL.createObjectURL`, en dat moet je **teruggeven** met
`URL.revokeObjectURL` zodra je het niet meer nodig hebt -- anders blijft
het bestand in het geheugen staan zolang het tabblad open is, en bij een
formulier waarin je een paar plaatjes uitprobeert loopt dat op.

### Te groot? Dan verkleinen we het, en keuren we het niet af

Een afgekeurd bestand is bijna altijd een afgekeurd bestand voor niets.
Wat er bewaard wordt is een vierkantje van 256 bij 256, dus van een foto
van acht megabyte blijft sowieso niets over. Toch hoorde de klant dat pas
ná het uploaden, ná het invullen van het hele formulier en ná een klik op
Opslaan -- op een telefoon een minuut wachten op een nee.

[`lib/beeldmerk.ts`](../../resources/js/lib/beeldmerk.ts) doet dat werk nu
in de browser, op het moment dat het bestand gekozen wordt:

| Wat er aan de hand is                | Wat er gebeurt                                                                                             |
| ------------------------------------ | ---------------------------------------------------------------------------------------------------------- |
| Past binnen de grenzen               | Niets. Het bestand gaat ongewijzigd mee.                                                                   |
| Te groot, of breder dan 3000 pixels  | Verkleind naar hoogstens 1600 pixels en opnieuw gecodeerd, met een regel erbij die zegt wat er gebeurd is. |
| Kortste zijde onder de 48 pixels     | Afgekeurd. Verkleinen maakt dat erger en oprekken maakt van een logo een vlek.                             |
| Zo langgerekt dat geen maat past     | Afgekeurd, met de uitleg dat het vakje vierkant is.                                                        |
| Geen beeld dat de browser kan openen | Afgekeurd.                                                                                                 |

**1600 pixels, en dat is geen willekeurig getal.** Het bewaarde vierkant
is 256 en de klant mag vijf keer inzoomen, dus in de uiterste stand komt
256 / 5 ≈ 52 pixel van het origineel in beeld. Met 1600 op de langste
zijde blijft dat ook volledig ingezoomd scherp.

**WebP als de browser het kan wegschrijven, anders PNG of JPEG.** Let op
de valkuil: geef je `toBlob` een formaat dat de browser niet kent, dan
krijg je stilletjes een PNG terug in plaats van een foutmelding -- en dan
klopt de extensie van het bestand niet meer met de inhoud, en struikelt de
`mimes`-regel op de server. Daarom vraagt de module vooraf of WebP kan, in
plaats van achteraf te hopen.

**De grenzen staan op twee plekken en moeten gelijk blijven.** De echte
staat in [`config/media.php`](../../config/media.php); de browser heeft
zijn eigen kopie, want die kan geen PHP lezen.
[`LogoLimietenTest`](../../tests/Feature/Website/LogoLimietenTest.php)
leest de getallen uit het TypeScript-bestand en legt ze naast de
configuratie. Lopen ze uiteen, dan gaat het op de vervelendste manier mis:
de browser verkleint netjes naar een maat die de server daarna weigert.

Diezelfde test controleert ook dat PHP méér toestaat dan wij. Zie
[deployment](../operations/deployment.md#de-php-instellingen-voor-uploads)
voor wat de server moet toestaan; dat is de instelling die op gedeelde
hosting het vaakst te krap staat.

### Een beeld bijsnijden

[`LogoKiezer`](../../resources/js/components/LogoKiezer.vue) is het veld
voor een beeldmerk: een rond voorbeeld met een zoomschuif eronder, slepen
om te verschuiven, en een schakelaar voor een witte ondergrond.

**Waarom de klant het zelf doet.** Wat er binnenkomt is niet te
voorspellen -- een liggend logo met de bedrijfsnaam ernaast, een vierkant
beeldmerk met veel lucht eromheen, of gewoon een foto. Elke automatische
regel gaat bij een van die drie mis: bijsnijden knipt de naam eraf,
passend maken laat een foto met witranden staan. Dat is hier eerst allebei
geprobeerd. Wie het beeld voor zich ziet, kiest in twee seconden wat wij
niet kunnen raden.

Twee dingen die daarbij kloppen moeten blijven:

- **Het voorbeeld en de server rekenen hetzelfde.** Bij zoom 1 past de
  langste zijde in het vierkant, en de verschuiving is in halve
  vierkanten. Lopen die uiteen, dan krijgt de klant iets anders dan hij
  instelde -- en dat merkt hij pas op de website.
- **De achtergrond wordt in het bestand gebakken.** Daardoor is elk
  opgeslagen beeld daarna een gewoon vierkant plaatje dat overal met
  `object-fit: cover` getoond kan worden, zonder uitzondering voor "is dit
  een logo of een foto". Zie `App\Support\Media\Uitsnede`.

Een formulier met een bestand erin gaat als `FormData` de deur uit
(`forceFormData: true`). Een wijziging gebruikt dan `POST` met
`_method: 'put'`: Laravel leest die omweg alleen uit formuliergegevens en
niet uit JSON. Zie
[`ErvaringDialoog.vue`](../../resources/js/components/website/ErvaringDialoog.vue).

## Een beheertabel op een telefoon

De drie logboeken -- gebruikers, beveiliging en activiteit -- zijn tabellen
van vier tot zes kolommen. Die passen niet op een telefoon, en zijwaarts
scrollen in een tabel die zelf al in een scrollende pagina zit is geen
oplossing: dan weet je nooit of je alles gezien hebt.

Zet `brand-tabel-kaarten` op de `<table>` en geef elke `<td>` een
`:data-label`. Onder de tabletgrens wordt elke rij dan een kaartje: de
cellen onder elkaar, met de kolomnaam ervoor.

```vue
<table class="brand-tabel-kaarten w-full text-sm">
    ...
    <td :data-label="$t('Wanneer')" class="px-3 py-2">…</td>
```

Drie dingen die hierbij horen:

- **De tabel op een breed scherm verandert geen pixel.** Alles staat in
  één mediaquery. Op desktop was er niets mis met een tabel, en dat is de
  reden dat dit geen herbouw is maar een laag eroverheen.
- **Een cel zonder `data-label` blijft een blok over de volle breedte.**
  Dat is precies goed voor de knoppenkolom en voor de cellen met een
  `colspan` -- de lege lijst, en de rij die uitklapt als je er een
  aanklikt.
- **De eerste cel wordt zwaarder gezet.** Dat is waar je op zoekt: de
  naam, of het tijdstip.

De uitlijning en het niet-afbreken uit Tailwind worden hier overschreven,
en dat lukt alleen omdat deze regels ongelaagd staan. Zie de valkuil
daarover in [`AGENTS.md`](../../AGENTS.md); hier werkt hij een keer in ons
voordeel, maar reken er niet op.

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

### De schuifbalk van een mail

Een mail is een eigen document en hoort daarom niet bij het bovenstaande. Je
ziet die balk op twee plekken: in het voorbeeld onder Instellingen → Weergave
(een `iframe` met de échte mailpagina erin) en bij een ontvanger die zijn mail
in een browser opent.

Hij staat in de **thema's** en niet in de maillayout, want het is kleur:
[`atit.css`](../../resources/views/vendor/mail/html/themes/atit.css) en
[`atit-huisstijl.css`](../../resources/views/vendor/mail/html/themes/atit-huisstijl.css),
elk met een regel op `html`. Dezelfde waarden als `--scrollbar-thumb` hier, zodat
het voorbeeld dezelfde balk heeft als het portaal eromheen.

Twee dingen die daarbij anders werken dan op een gewone pagina:

- **`::-webkit-scrollbar` kan er niet in.** Laravel voegt een mailthema met
  CssToInlineStyles in de tags van de mail, en dat gereedschap kan alleen
  selectors inzetten die een element aanwijzen. Een pseudo-element wijst niets
  aan, dus zo'n regel wordt stil weggegooid -- hij haalt de mail niet eens.
  `scrollbar-width` en `scrollbar-color` zijn gewone eigenschappen en komen dus
  wél in de `<html>`-tag terecht.
- **Het document moet zeggen welke kant het op zit.** Dat deed het niet: Laravel
  zet in élke mail `<meta name="color-scheme" content="light">`, vast erin
  getypt. Een donkerblauwe mail die "licht" zegt krijgt een witte schuifbalk met
  pijltjes ernaast, en Apple Mail en Outlook.com gaan hem zelf omkleuren.
  Daarom is
  [`layout.blade.php`](../../resources/views/vendor/mail/html/layout.blade.php)
  gepubliceerd: de twee metategels volgen nu de gekozen stijl. De
  `color-scheme`-eigenschap in het thema wint van de metategel, dus die bepaalt
  de balk; de metategel is er voor de mailprogramma's, die geen CSS op `html`
  lezen.

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

### `brand-schuif-x`: een brede tabel die de pagina niet gijzelt

Een beheertabel die breder is dan het scherm schuift zijwaarts. Zet daar
**`brand-schuif-x`** op, en nooit los `overflow-x-auto`:

```css
.brand-schuif-x {
    overflow-x: auto;
    overscroll-behavior-x: contain;
    overscroll-behavior-y: auto;
    scrollbar-gutter: auto;
}
```

> **Dit is een echte bug geweest, gemeld met "als ik in tabel probeer te
> scrollen dat ik dan de pagina niet naar beneden kan scrollen".** Met de
> muis boven een brede tabel deed het wiel niets meer: de pagina stond
> stil en je moest er eerst naast gaan staan.

De oorzaak zit in twee regels die elk apart redelijk lijken:

1. **`overflow-x: auto` trekt `overflow-y` mee.** De specificatie zegt dat
   als één as `visible` is en de andere niet, die `visible` wordt
   behandeld als `auto`. Een vak met alléén `overflow-x-auto` schuift dus
   óók verticaal -- alleen valt er verticaal niets te schuiven.
2. **`overscroll-behavior: contain`** zegt tegen de browser: geef de
   beweging niet door aan wat erachter zit. Op een vak dat verticaal
   niets te schuiven heeft betekent dat: slik het wiel op en laat de
   pagina staan.

De oplossing is de twee assen uit elkaar trekken: horizontaal houden we
`contain` (anders schuift de pagina er zijwaarts achteraan mee), en
verticaal zetten we hem terug op `auto` zodat de beweging netjes
doorgaat naar de pagina.

De regel staat **buiten een `@layer`**, want een losse Tailwind-utility
als `overflow-x-auto` zou hem anders kunnen overrulen; zie
[AGENTS.md](../../AGENTS.md) over die val.

#### En `scrollbar-gutter`, uit dezelfde hoek

`scrollbar-gutter: auto` in die lijst is een tweede reparatie met dezelfde
oorzaak. De eigenaar meldde het zo: _"bij de tabel loopt de bovenste kleur
niet helemaal tot rechts door"_ -- de gekleurde kop van een beheertabel
stopte een centimeter voor de rechterrand, met een strook in de kleur van
het vak eronder ertussen.

Dat komt van `scrollbar-gutter: stable` in `brand-scrollbar`. Die
reserveert ruimte voor de schuifbalk van de **blokas** -- rechts, in onze
schrijfrichting -- vóórdat die balk er is, zodat inhoud niet verspringt
zodra een lijst lang genoeg wordt. Voor een paneel dat verticaal schuift
is dat precies goed.

Hier niet, en de reden is punt 1 hierboven: de verticale as is alleen
`auto` geworden omdat de horizontale dat is. Er komt nooit een verticale
balk, en toch bleef die strook gereserveerd. De tabel heeft `w-full` --
honderd procent van de **inhoudsbreedte** -- dus hij stopt waar die strook
begint.

> **De les is dezelfde als bij `overscroll-behavior`:** `brand-scrollbar`
> is geschreven voor een paneel dat verticaal schuift. Elke eigenschap
> daarin die over de blokas gaat, moet je terugdraaien voor een vak dat
> alleen zijwaarts schuift. Daarom horen die correcties bij elkaar in
> `brand-schuif-x` te staan en niet verspreid over de schermen.

Aan de tabellen zelf is hiervoor niets veranderd; dit haalt alleen weg wat
er niet hoorde te staan.

**Waarom niet de tabel zelf laten schuiven in de hoogte.** Dat was het
voorstel bij de melding: geef de tabel een eigen hoogte met een eigen
verticale balk. Dat lost het wiel op, maar het levert twee schuifbalken
op één pagina op, de kop van de tabel loopt weg onder je handen, en op
een telefoon is een vak-in-een-vak slopend. Een tabel die net zo lang is
als zijn inhoud en een pagina die normaal schuift, is rustiger.

### Waar het al op staat

| Plek                                                                                              | Wat er schuift                            |
| ------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| `html`                                                                                            | De pagina zelf.                           |
| [De twee mailthema's](../../resources/views/vendor/mail/html/themes/)                             | De mail, en zijn voorbeeld.               |
| [BrandSelect.vue](../../resources/js/components/BrandSelect.vue)                                  | De uitgeklapte lijst van een keuzeveld.   |
| [SidebarContent.vue](../../resources/js/components/ui/sidebar/SidebarContent.vue)                 | Het menu, als het langer wordt.           |
| [SelectContent.vue](../../resources/js/components/ui/select/SelectContent.vue)                    | De lijst van de kale shadcn-select.       |
| [DropdownMenuContent.vue](../../resources/js/components/ui/dropdown-menu/DropdownMenuContent.vue) | Het accountmenu en soortgelijke.          |
| [DialogScrollContent.vue](../../resources/js/components/ui/dialog/DialogScrollContent.vue)        | Een venster dat langer is dan het scherm. |
| [MailLog.vue](../../resources/js/pages/admin/MailLog.vue)                                         | De brede tabel en de context-`<pre>`.     |
| [SecurityEvents.vue](../../resources/js/pages/admin/SecurityEvents.vue)                           | De brede tabel.                           |
| [Users.vue](../../resources/js/pages/admin/Users.vue)                                             | De brede tabel.                           |
| [Activity.vue](../../resources/js/pages/admin/Activity.vue)                                       | De brede tabel.                           |
| [Diensten.vue](../../resources/js/pages/website/Diensten.vue)                                     | De brede tabel.                           |
| [Ervaring.vue](../../resources/js/pages/website/Ervaring.vue)                                     | De brede tabel.                           |
| [Certificaten.vue](../../resources/js/pages/website/Certificaten.vue)                             | De brede tabel.                           |
| [Statistieken.vue](../../resources/js/pages/website/Statistieken.vue)                             | De brede tabel.                           |
| [Dashboard.vue](../../resources/js/pages/Dashboard.vue)                                           | Het raster op een smal scherm.            |

Alles in die lijst dat **zijwaarts** schuift, draagt `brand-schuif-x`.
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

1. **Loop de `rules()` van de FormRequest langs en zet `verplicht` op het
   label van elk veld met `required`.** Dit is de stap die het vaakst
   wordt overgeslagen, en de enige waarbij de klant verkeerde informatie
   krijgt als je hem vergeet.
2. Een keuze uit een lijst? `BrandSelect`, met `aria-label` als er geen
   zichtbaar label naast staat. Lange lijst? Dan zoekt hij vanzelf.
3. Een maand en een jaar? `MaandKiezer`, met de lijsten van de server.
4. Een invoerveld? `Input`, die draagt `brand-control` al.
5. Een bestand? Het inheemse veld op `sr-only` achter een `Button`, en het
   formulier als `FormData` versturen.
6. Kan er iets schuiven binnen de pagina? `brand-scrollbar` erop, en de plek
   erbij in de tabel hierboven. Schuift het **zijwaarts** -- een brede
   tabel -- dan `brand-schuif-x` in plaats van `overflow-x-auto`, anders
   slikt het vak het muiswiel op en staat de pagina stil.
7. Een kleur nodig die er niet is? Die komt in `:root` én in `.dark`, en in de
   tabel bovenaan dit document.
8. Maakt, wijzigt of verwijdert een knop iets? Dan krijgt hij de variant
   van zijn handeling, niet de standaardknop. Zie hierboven.
9. Gaat een link naar de publieke site? Dan hoort `VerlaatPortaal` erachter.
10. Controleer beide thema's, en controleer met het toetsenbord dat de
    focusring zichtbaar is en dat de lijst met de pijltjes te bedienen is.

Zie ook [huisstijl en kleuren](huisstijl-en-kleuren.md) voor het palet zelf en
[frontend en animatie](frontend-en-animatie.md) voor de rest van de
frontend-afspraken.
