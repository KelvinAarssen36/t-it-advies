# Huisstijl en kleuren

Het kleurenpalet van @T IT Advies. Dit is de bron: gebruik geen hexcode die
hier niet in staat, en voeg er geen toe zonder hem hier op te schrijven.
Anders staan er over een half jaar zeven soorten blauw in de code en lijkt
elke pagina net iets anders.

Het palet staat in `resources/css/app.css` en werkt door in de hele
applicatie: de publieke site, het beheergedeelte en de inlogschermen.
Zie [Hoe dit in code landt](#hoe-dit-in-code-landt).

## De regel: huisstijl, en altijd allebei de thema's

**Dit is bindend, niet vrijblijvend.** Alles wat we bouwen staat in de
huisstijl, en alles in het **portaal** moet kloppen in het lichte én het
donkere thema.

Die tweede helft is de valkuil. Het portaal volgt de voorkeur van de
gebruiker, dus er is geen "de achtergrond is toch altijd donker". Een kleur
die je vastlegt omdat hij er nu goed uitziet, is in het andere thema
onleesbaar -- meestal wit op wit of donkergrijs op donkerblauw. En omdat
niemand de hele dag van thema wisselt, merk je dat pas wanneer de klant het
meldt.

De **landing** is het andere geval: die staat altijd donker, ongeacht wat
iemand als voorkeur heeft. Daar hoef je dus niets te controleren, maar
gebruik daar nooit een vaste kleur "omdat het toch altijd donker is" --
zodra zo'n component in het portaal hergebruikt wordt, breekt hij. Dat is
hier echt gebeurd met de segmentknop.

Hoe je het goed doet:

1. **Nooit een kleur rechtstreeks.** Geen `bg-[#061626]`, geen
   `text-white` op een vlak waarvan de achtergrond kan wisselen. Gebruik de
   rol-tokens (`bg-background`, `text-foreground`, `border-border`) of de
   besturingsvariabelen; zie
   [formulieren en schuifbalken](formulieren-en-schuifbalken.md).
2. **Twijfel je of een token bestaat voor wat je nodig hebt?** Dan maak je
   er een, in `:root` én in `.dark`. Niet een uitzondering in het component.
3. **Controleer het.** Instellingen → Weergave, en klik één keer heen en
   weer. Dat kost vijf seconden.

Wat je vooral controleert: tekst op een gekleurd vlak, randen, schaduwen,
gedempte tekst, en alles met doorzichtigheid erin.

## Het volledige palet

| Rol         | Naam           | HEX       | Gebruik                                |
| ----------- | -------------- | --------- | -------------------------------------- |
| Primary 900 | Midnight Navy  | `#061626` | Hoofdachtergrond, footer, hero-secties |
| Primary 800 | Deep Navy      | `#0A2340` | Cards, donkere vlakken, navigatie      |
| Primary 700 | Corporate Blue | `#104A78` | Secundaire vlakken, borders            |
| Brand Blue  | Electric Blue  | `#0787E8` | Buttons, links, actieve states         |
| Brand Cyan  | Cyan Accent    | `#13C7F3` | Highlights, lijnen, glow, details      |
| Light Blue  | Ice Blue       | `#A9E8FA` | Zachte accenten, iconen, achtergronden |
| Silver Dark | Steel Silver   | `#9EADBC` | Zilverachtige logo-details             |
| Silver Lt.  | Platinum       | `#E3EAF0` | Lichte metallic uitstraling            |
| Neutral 900 | Charcoal       | `#111820` | Tekst op lichte achtergronden          |
| Neutral 600 | Slate          | `#65717D` | Secundaire tekst                       |
| Neutral 200 | Soft Gray      | `#E8EDF1` | Borders en subtiele scheidingen        |
| Neutral 100 | Cloud          | `#F5F8FA` | Lichte secties                         |
| White       | Pure White     | `#FFFFFF` | Tekst, logo, schone achtergronden      |

## De vijf die je dagelijks nodig hebt

Wie snel iets bouwt heeft aan deze vijf genoeg:

| Kleur         | HEX       | Waarvoor                                                   |
| ------------- | --------- | ---------------------------------------------------------- |
| Midnight Navy | `#061626` | Het fundament van de site. De belangrijkste donkere kleur. |
| Deep Navy     | `#0A2340` | Cards en lagen bovenop die donkere achtergrond.            |
| Electric Blue | `#0787E8` | De interactieve kleur: buttons, links, alles klikbaars.    |
| Cyan Accent   | `#13C7F3` | Alleen highlights. Dit geeft de technische uitstraling.    |
| White         | `#FFFFFF` | Contrast, en de zakelijke, schone kant.                    |

## Verhouding op de pagina

Dit is het deel dat het verschil maakt tussen premium en druk.

| Kleurgroep            | Aandeel           |
| --------------------- | ----------------- |
| Donkerblauw / navy    | 55-65%            |
| Wit / lichte neutrals | 20-30%            |
| Electric Blue         | 8-12%             |
| Cyan                  | 3-5%              |
| Zilver                | alleen als detail |

**Waarom dit belangrijk is:** als alles felblauw wordt, valt niets meer op en
verliest het logo juist zijn premium uitstraling. Het blauw werkt doordat het
schaars is.

## Gradients

Er zijn er precies drie. Meer niet.

**Brand Gradient** -- buttons, accentlijnen, kleine vlakken, subtiele animaties:

```css
linear-gradient(135deg, #0787E8 0%, #13C7F3 100%)
```

**Dark Brand Gradient** -- hero-secties en grote achtergronden:

```css
linear-gradient(135deg, #061626 0%, #0A2340 55%, #104A78 100%)
```

**Silver Gradient** -- laat de metalen delen van het logo terugkomen. Spaarzaam
gebruiken, en liever niet op gewone UI-elementen:

```css
linear-gradient(135deg, #FFFFFF 0%, #E3EAF0 45%, #9EADBC 100%)
```

## Buttons

**Primair:**

```
Background: #0787E8
Tekst:      #FFFFFF
Hover:      #13A8ED
```

Op een donkere achtergrond mag ook de Brand Gradient (`#0787E8` -> `#13C7F3`).

**Secundair op donker:**

```
Background: transparant
Border:     #2D5E83
Tekst:      #FFFFFF
Hover bg:   #0A2340
```

**Secundair op wit:**

```
Border: #0787E8
Tekst:  #0A2340
```

## Tekstkleuren

Op donkere achtergronden:

| Element          | Kleur     |
| ---------------- | --------- |
| Titel            | `#FFFFFF` |
| Normale tekst    | `#D8E2EA` |
| Secundaire tekst | `#9EADBC` |
| Links            | `#13C7F3` |

Op lichte achtergronden:

| Element          | Kleur     |
| ---------------- | --------- |
| Titel            | `#061626` |
| Normale tekst    | `#263746` |
| Secundaire tekst | `#65717D` |
| Links            | `#0787E8` |

Cyan is geen tekstkleur voor lange stukken -- daar is hij te fel voor. Als
highlight is hij juist sterk.

## Achtergronden

Donker, drie niveaus:

```
Pagina:         #061626
Sectie/card:    #0A2340
Verhoogde card: #102D4A
```

Licht:

```
Pagina:          #FFFFFF
Alternatieve bg: #F5F8FA
Card:            #FFFFFF
Border:          #E8EDF1
```

Drie niveaus is genoeg. Je hebt geen twintig soorten grijs nodig.

## Borders

Donkere UI:

```
Normaal: #1A3A55
Sterk:   #2D5E83
Accent:  #0787E8
```

Lichte UI:

```
Normaal: #E8EDF1
Sterk:   #C9D4DD
```

## Glow

Het logo en het blauw hebben een high-tech uitstraling die je op enkele
plekken mag versterken:

```css
box-shadow: 0 0 20px rgba(19, 199, 243, 0.18);
```

Sterker, bijvoorbeeld voor het logo:

```css
box-shadow: 0 0 35px rgba(7, 135, 232, 0.3);
```

Spaarzaam blijven. Niet iedere card laten gloeien.

## Statuskleuren

Horen niet bij het merk, maar zijn nodig zodra er formulieren en dashboards
zijn. Bewust gedempt, zodat ze niet met het blauw botsen.

| Status  | HEX       |
| ------- | --------- |
| Success | `#24A66A` |
| Warning | `#E3A425` |
| Error   | `#D94A4A` |
| Info    | `#0787E8` |

## De definitieve kern

Als er ooit een officiële brand guide komt, is dit de hoofdset:

```
Midnight Navy   #061626
Deep Navy       #0A2340
Electric Blue   #0787E8
Cyan Accent     #13C7F3
Ice Blue        #A9E8FA
Steel Silver    #9EADBC
Platinum        #E3EAF0
Cloud           #F5F8FA
Charcoal        #111820
White           #FFFFFF
```

De gewenste uitstraling is vooral `#061626` + wit + `#0787E8`, waarbij
`#13C7F3` alleen wordt gebruikt voor de herkenbare heldere highlights van het
oog in het logo.

## Hoe dit in code landt

De frontend gebruikt Tailwind 4 met de tokenopzet van shadcn-vue. In
[`resources/css/app.css`](../../resources/css/app.css) staan twee lagen:

1. `@theme inline` koppelt Tailwind-kleurnamen aan CSS-variabelen:
   `--color-primary: var(--primary);`
2. `:root` en `.dark` geven die variabelen hun waarde.

Het palet staat in laag 2, niet als losse hexcodes in componenten. Concreet:

- Schrijf in componenten `bg-background`, `text-foreground`, `border-border`
  en `bg-primary`, en **niet** `bg-[#061626]`. Dan blijft licht en donker
  vanzelf kloppen en hoef je een kleurwijziging maar op één plek te doen.
- De hexcodes staan één keer, in `:root`, onder hun merknaam
  (`--brand-navy`, `--brand-cyan`, `--brand-gradient`). De rolvariabelen
  verwijzen daarnaar.

### Wat de rollen zijn geworden

| Token              | Licht         | Donker                   |
| ------------------ | ------------- | ------------------------ |
| `background`       | Wit           | Midnight Navy            |
| `foreground`       | Charcoal      | `#D8E2EA`                |
| `card`             | Wit           | Deep Navy                |
| `popover`          | Wit           | Verhoogde card `#102D4A` |
| `primary`          | Electric Blue | Electric Blue            |
| `muted-foreground` | Slate         | Steel Silver             |
| `border`           | Soft Gray     | `#1A3A55`                |
| `ring` (focus)     | Electric Blue | **Cyan Accent**          |

Die laatste is geen slordigheid: Electric Blue als focusring op Midnight Navy
is nauwelijks te zien, en een focusring die je niet ziet is geen focusring.

### Twee thema's, geen drie

Het portaal kent **Huisstijl** en **Licht**. De starter kit kent ook
"systeem", maar dat is hier weggehaald: het betekent dat het portaal er
anders uitziet naargelang een instelling die ergens anders staat, en dat je
in het scherm niet kunt zien welke van de twee je nu eigenlijk hebt. Bij een
portaal met één gebruiker levert die onzekerheid niets op.

**De huisstijl is de basis**, en dat is waarom hij in het scherm
"Huisstijl" heet en niet "Donker". Het is niet de donkere variant van iets
anders; het is het palet waar alles op gebouwd wordt. De landing staat er
altijd in, het portaal sluit erop aan, en wie niets kiest krijgt hem. Licht
is de uitzondering: er voor wie de hele dag in het portaal werkt en dat
prettiger vindt.

Wat je daaruit meeneemt bij het bouwen: ontwerp in de huisstijl, en
**controleer** daarna in licht. Niet andersom. Een component dat in licht
is bedacht en daarna donker wordt gemaakt, valt bijna altijd uit de toon.

In de code heet die stand nog gewoon `dark`, want die waarde stuurt de
klasse op `<html>` aan waar het hele kleurstelsel aan hangt. Alleen het
etiket in het scherm is anders.

De keuze staat in een cookie én in `localStorage`. Het cookie is er zodat de
server `dark` al op `<html>` kan zetten vóórdat er JavaScript draait --
zonder dat zie je bij elke paginalading een flits van het verkeerde thema.
[`HandleAppearance`](../../app/Http/Middleware/HandleAppearance.php) houdt
die waarde tegen een witte lijst: alles wat niet letterlijk `light` is, is
donker. Een oude `system` uit een vorige versie landt dus vanzelf goed, net
als onzin die iemand zelf in het cookie zet.

De landing staat hier los van: die is **altijd** donker, ongeacht de
voorkeur. Zie de paragraaf over de publieke site verderop.

[`AppearanceTest`](../../tests/Feature/AppearanceTest.php) bewaakt alle vier
de gevallen.

### Welk scherm volgt het thema, en welk niet

Niet alles in het portaal volgt de voorkeur, en dat is bewust. De vuistregel
gaat over wat je op dat moment aan het doen bent:

| Scherm                                                           | Thema             |
| ---------------------------------------------------------------- | ----------------- |
| De landing                                                       | Altijd huisstijl  |
| De voordeur: inloggen, wachtwoord vergeten, nieuw wachtwoord     | Altijd huisstijl  |
| Onderweg naar binnen: 2FA instellen, het laadscherm              | Altijd huisstijl  |
| Binnen het portaal, inclusief de foutpagina                      | Volgt de voorkeur |
| Een onderbreking terwijl je werkt: wachtwoord of code bevestigen | Volgt de voorkeur |

Die laatste regel is de reden dat
[`AuthSimpleLayout`](../../resources/js/layouts/auth/AuthSimpleLayout.vue)
twee varianten heeft. `merk` is de voordeur: donker, met het brede logo en
de aurora. `portaal` is een onderbreking: je bent al aan het werk, en dan
klap je niet vanuit een licht portaal ineens in een donkere pagina. Zet die
variant op de pagina zelf:

```ts
defineOptions({
    layout: { variant: 'portaal', title: '…', description: '…' },
});
```

In de portaalvariant staat het **vierkante merkteken** en niet het brede
logo. Dat brede logo is getekend voor een donkere ondergrond -- zilver en
blauw met donkere contouren -- en verliest op wit zijn contrast.

Een scherm dat altijd donker staat en dat het hele venster vult, krijgt
`brand-dark-page` naast `dark`. Zonder die klasse blijft de schuifbalk van
het document licht op een donkere pagina; zie
[formulieren en schuifbalken](formulieren-en-schuifbalken.md).

### Besturingselementen hebben hun eigen laag

Keuzevelden, invoervelden, zwevende panelen en schuifbalken lezen niet
rechtstreeks uit de tabel hierboven, maar uit een eigen set variabelen:
`--control-bg`, `--control-border`, `--scrollbar-thumb` en de rest. Die
staan in dezelfde twee blokken in `app.css` en halen hun waarden uit
hetzelfde palet.

De reden is dat die elementen net iets anders werken dan de rest: een
zwevend paneel mag nooit doorzichtig zijn, een schuifbalkduim heeft een
hoverkleur die verder nergens voorkomt. Zonder eigen laag zou dat als
losse uitzonderingen door de componenten heen slingeren.

`popover` in de tabel hierboven wijst naar `--control-bg-elevated`, zodat
de panelen van de UI-pakketten dezelfde bron gebruiken.

De ronding van die elementen komt uit `--radius-xl`, afgeleid van `--radius`.
Dat is ruimer dan de `rounded-md` van de starter kit, en dat is een keuze
voor het hele portaal: zachte hoeken passen bij de rest van de huisstijl, en
een veld met scherpe hoeken tussen ronde kaarten valt op als een vreemde
eend.

Zie [formulieren en schuifbalken](formulieren-en-schuifbalken.md) voor de
volledige lijst en wanneer je welke pakt.

### Merkkleuren als utility

Naast de rollen zijn de merkkleuren los beschikbaar voor plekken waar een rol
niets zegt: `bg-brand-navy`, `text-brand-cyan`, `border-brand-line`,
`text-brand-ice`, `bg-brand-cloud`. Gebruik ze spaarzaam -- de verhouding
hierboven is er niet voor niets.

### Gradients, glow en de accentlijn

Als eigen utility, zodat de waarden op één plek staan en niemand er een
vierde gradient bij verzint:

| Utility                | Wat het doet                        |
| ---------------------- | ----------------------------------- |
| `brand-surface`        | De Brand Gradient als achtergrond   |
| `brand-surface-dark`   | De Dark Brand Gradient, voor hero's |
| `brand-surface-silver` | De Silver Gradient                  |
| `brand-text-gradient`  | Tekst in de merkgradient            |
| `brand-glow`           | De subtiele glow                    |
| `brand-glow-strong`    | De sterke versie, voor het logo     |
| `brand-rule`           | Dunne cyaan lijn die uitdooft       |

Het zijn `@utility`-regels en geen gewone klassen, zodat `hover:brand-glow`
werkt -- en juist op hover gebruik je de glow.

`brand-text-gradient` heeft een vaste terugvalkleur. Zonder ondersteuning
voor `background-clip: text` zou de tekst anders onzichtbaar worden, en dat
is erger dan een tint mis.

### Knoppen

De gewone `Button` is al Electric Blue met wit, want `primary` is dat. Voor
op donker zijn er twee varianten bij: `variant="brand"` (de gradient) en
`variant="brand-outline"` (doorzichtig met een `#2D5E83`-rand). Gebruik
`brand` niet op wit: daar verliest de gradient zijn contrast en wordt de knop
juist zwakker dan de gewone.

**Let op de contrastcheck.** Electric Blue op Midnight Navy haalt de
WCAG-eis voor gewone tekst niet. Gebruik `#0787E8` daarom als vlak met witte
tekst erop, en niet als tekstkleur op donker; daar is Cyan Accent of Ice Blue
voor. Zie ook [frontend en animatie](frontend-en-animatie.md), waar staat dat
beweging uit moet kunnen -- toegankelijkheid is geen sluitstuk.

### De aurora achter het inlogscherm

De inlogschermen hebben een langzaam drijvende gloed: drie wolken in Ice
Blue, Cyan en Electric Blue op de navy achtergrond. De klasse is
`brand-aurora`, met drie lege `<span>`-elementen erin.

Drie dingen die daar bewust zo zijn:

- **Alleen `transform` beweegt.** Daarmee kan de browser het aan de
  compositor overlaten. Zou je posities of kleuren animeren, dan moet hij
  elk beeldje opnieuw tekenen en gaat de ventilator aan op een scherm waar
  iemand alleen even wil inloggen.
- **De drie duren zijn 23, 31 en 37 seconden.** Geen ronde of deelbare
  getallen, want dan lopen ze na een tijdje gelijk en wordt het patroon
  zichtbaar.
- **Bij `prefers-reduced-motion` staan de wolken stil.** Ze blijven wel
  zichtbaar; er is hier niets dat op een eindtoestand gezet moet worden,
  anders dan bij de scroll-reveals.

Het formulier staat op een eigen vlak met `backdrop-blur`. Zonder die laag
zweven de velden los over de beweging en is het onrustig om naar te kijken
terwijl je typt.

### Pop-ups: vervagen, niet verduisteren

De overlay achter een dialoogvenster is standaard
`bg-brand-navy/35 backdrop-blur-md`: de achtergrond wordt vooral **wazig**
en maar een beetje donkerder. Dat houdt het portaal leesbaar achter het
venster, zodat je ziet waar je gebleven was.

Wil een scherm een zwaardere sluier -- het instelvenster voor 2FA
bijvoorbeeld, waar niets anders mag afleiden -- dan geeft het
`overlay-class` mee aan `DialogContent`. Zet dat niet standaard aan: een
ondoorzichtige laag maakt van elk venster een aparte pagina.

### Micro-animaties in het portaal

De begroeting na het binnenkomen
([`WelcomeDialog.vue`](../../resources/js/components/WelcomeDialog.vue), die
in de layout van het portaal staat en niet op één pagina)
laat zijn onderdelen trapsgewijs opkomen, elk met een eigen vertraging via
`--welcome-delay`. Dat is wat het levendig maakt: alles tegelijk laten
verschijnen leest als een melding, na elkaar als een beweging.

**Die animaties zijn pure CSS, en dat is een harde keuze.** GSAP zit in een
chunk die alleen de publieke site ophaalt; die naar het portaal trekken zou
ruim honderd kilobyte kosten voor een begroeting van twee seconden. Houd
het portaal dus op CSS-animaties, en gebruik alleen `opacity` en
`transform` zodat ze op de compositor draaien.

### De publieke site staat altijd donker

`PublicLayout` zet zelf `dark` op zijn wortel, en `AuthSimpleLayout` doet
hetzelfde voor de inlogschermen. De bezoeker die zijn systeem op licht heeft
staan krijgt dus toch de navy site. Midnight Navy is het fundament van de
huisstijl; een lichte versie van dezelfde pagina zou een tweede ontwerp zijn
en geen instelling, en het logo werkt op wit ook minder goed.

Het beheergedeelte áchter het inloggen volgt de voorkeur van de gebruiker
wél -- daar zit je soms een uur in.

Wil je op de publieke site een lichte sectie, bouw die dan als een bewuste
lichte blok binnen het donkere geheel (Platinum of Cloud als vlak), en niet
door het thema per sectie om te draaien. Dat laatste breekt de
`dark:`-varianten van de componenten die erin staan.

## Het logo

Het logo bestaat uit een blauw/cyaan oog met zilveren metalen delen. De
gradients hierboven zijn er om die twee materialen elders op de site terug te
laten komen zonder het logo zelf te herhalen.

De aangeleverde bestanden staan in `public/images/`:

| Bestand                    | Wat het is                                           |
| -------------------------- | ---------------------------------------------------- |
| `logo-vierkant.png`        | Vierkant, met transparantie                          |
| `logo-breed.png`           | Liggend, met transparantie                           |
| `logo-volledig-1/2/3.png`  | Varianten met achtergrond                            |
| `achtergrond-oog-3840.png` | Het sfeerbeeld met het oog in 4K -- de hero          |
| `achtergrond-met-logo.png` | Dezelfde compositie in 1672 px; niet meer in gebruik |
| `persoon-met-logo.png`     | Nog niet gebruikt                                    |
| `vulling-1.png`            | Nog niet gebruikt                                    |

Voor gebruik in de applicatie staan er twee lichte varianten klaar. De
bronbestanden zijn 0,8 tot 0,9 MB en horen nooit rechtstreeks op een pagina:

| Bestand                    | Waar                              |
| -------------------------- | --------------------------------- |
| `logo-breed.webp` (640 px) | Het inlogscherm                   |
| `logo-merk.webp` (128 px)  | De zijbalk, via `AppLogoIcon.vue` |

Het logo is een render met verlopen en metallic vlakken, dus een afbeelding
en geen SVG -- in vectoren verliest het zijn karakter. Alles verwijst ernaar
via [`AppLogoIcon.vue`](../../resources/js/components/AppLogoIcon.vue); komt
er ooit een echte SVG, dan hoef je alleen dat component te vervangen.

Het logo is getekend voor een donkere ondergrond: zilver en blauw met
donkere contouren. Op wit verliest het zijn contrast. Dat is een van de
redenen dat de inlogschermen donker staan.

Het tabblad-icoon (`public/favicon.ico`) en het iOS-icoon
(`public/apple-touch-icon.png`) komen hiervandaan. De `favicon.svg` van de
starter kit is verwijderd: browsers geven een SVG voorrang boven een `.ico`,
dus zolang die er stond zag je het Laravel-logo in je tabblad.

De hero gebruikt het sfeerbeeld met een afdeklaag eroverheen. Het oog staat
rechts in beeld, dus de laag dekt van links af en de tekst staat links. Zie
[frontend en animatie](frontend-en-animatie.md) voor hoe dat werkt en waarom
er altijd een afdeklaag overheen gaat.
