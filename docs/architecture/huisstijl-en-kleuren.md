# Huisstijl en kleuren

Het kleurenpalet van @T IT Advies. Dit is de bron: gebruik geen hexcode die
hier niet in staat, en voeg er geen toe zonder hem hier op te schrijven.
Anders staan er over een half jaar zeven soorten blauw in de code en lijkt
elke pagina net iets anders.

Het palet staat in `resources/css/app.css` en werkt door in de hele
applicatie: de publieke site, het beheergedeelte en de inlogschermen.
Zie [Hoe dit in code landt](#hoe-dit-in-code-landt).

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

### De publieke site staat altijd donker

`PublicLayout` zet zelf `dark` op zijn wortel. De bezoeker die zijn systeem
op licht heeft staan krijgt dus toch de navy site. Midnight Navy is het
fundament van de huisstijl; een lichte versie van dezelfde pagina zou een
tweede ontwerp zijn en geen instelling. Het beheergedeelte volgt de voorkeur
van de gebruiker wél -- daar zit je soms een uur in.

Wil je op de publieke site een lichte sectie, bouw die dan als een bewuste
lichte blok binnen het donkere geheel (Platinum of Cloud als vlak), en niet
door het thema per sectie om te draaien. Dat laatste breekt de
`dark:`-varianten van de componenten die erin staan.

## Het logo

Het logo bestaat uit een blauw/cyaan oog met zilveren metalen delen. De
gradients hierboven zijn er om die twee materialen elders op de site terug te
laten komen zonder het logo zelf te herhalen.

De aangeleverde bestanden staan in `public/images/`:

| Bestand                    | Wat het is                            |
| -------------------------- | ------------------------------------- |
| `logo-vierkant.png`        | Vierkant, met transparantie           |
| `logo-breed.png`           | Liggend, met transparantie            |
| `logo-volledig-1/2/3.png`  | Varianten met achtergrond             |
| `achtergrond-met-logo.png` | Het sfeerbeeld met het oog -- de hero |
| `persoon-met-logo.png`     | Nog niet gebruikt                     |
| `vulling-1.png`            | Nog niet gebruikt                     |

Het tabblad-icoon (`public/favicon.ico`) en het iOS-icoon
(`public/apple-touch-icon.png`) komen hiervandaan. De `favicon.svg` van de
starter kit is verwijderd: browsers geven een SVG voorrang boven een `.ico`,
dus zolang die er stond zag je het Laravel-logo in je tabblad.

De hero gebruikt het sfeerbeeld met een afdeklaag eroverheen. Het oog staat
rechts in beeld, dus de laag dekt van links af en de tekst staat links. Zie
[frontend en animatie](frontend-en-animatie.md) voor hoe dat werkt en waarom
er altijd een afdeklaag overheen gaat.
