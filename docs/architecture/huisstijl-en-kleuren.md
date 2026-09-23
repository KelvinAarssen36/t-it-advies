# Huisstijl en kleuren

Het kleurenpalet van @T IT Advies. Dit is de bron: gebruik geen hexcode die
hier niet in staat, en voeg er geen toe zonder hem hier op te schrijven.
Anders staan er over een half jaar zeven soorten blauw in de code en lijkt
elke pagina net iets anders.

> **Stand van zaken.** Het palet is vastgesteld, maar staat nog niet in
> `resources/css/app.css`. De applicatie draait nog op het standaardthema van
> de starter kit. Zie [Hoe dit in code landt](#hoe-dit-in-code-landt).

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

Het palet hoort dus in laag 2 terecht te komen, niet als losse hexcodes in
componenten. Concreet betekent dat:

- Schrijf in componenten `bg-background`, `text-foreground`, `border-border`
  en `bg-primary`, en **niet** `bg-[#061626]`. Dan blijft licht en donker
  vanzelf kloppen en hoef je een kleurwijziging maar op één plek te doen.
- Kleuren die geen tegenhanger hebben in de tokenset -- de gradients, de
  glow, Cyan als highlight -- komen als eigen variabelen in `:root`, met de
  naam uit dit document (`--brand-cyan`, `--brand-gradient`).

**Let op de contrastcheck.** Electric Blue op Midnight Navy haalt de
WCAG-eis voor gewone tekst niet. Gebruik `#0787E8` daarom als vlak met witte
tekst erop, en niet als tekstkleur op donker; daar is Cyan Accent of Ice Blue
voor. Zie ook [frontend en animatie](frontend-en-animatie.md), waar staat dat
beweging uit moet kunnen -- toegankelijkheid is geen sluitstuk.

## Het logo

Het logo bestaat uit een blauw/cyaan oog met zilveren metalen delen. De
gradients hierboven zijn er om die twee materialen elders op de site terug te
laten komen zonder het logo zelf te herhalen.
