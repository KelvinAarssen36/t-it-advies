# Valkuilen die we al een keer in zijn gelopen

Dit document bestaat omdat dezelfde fout twee keer maken duurder is dan
hem één keer opschrijven. Elke regel hieronder is **een echte fout die in
dit project is gemaakt**, niet een algemene goede gewoonte.

Lees dit vóór je een module bouwt of een scherm aanpast. En kom je een
nieuwe valkuil tegen, zet hem erbij -- met het gevolg erbij, want een regel
zonder reden wordt de eerste die iemand overslaat.

---

## Opmaak

### Een raster krijgt `minmax(0, 1fr)`, nooit een kale `1fr`

Een kale `1fr` is `minmax(auto, 1fr)`, en die ondergrens is de breedte van
het langste woord. Eén lang samengesteld woord rekt de kolom op, duwt de
sectie breder, en geeft **de hele pagina een horizontale schuifbalk**.

Dat is bij `.brand-opleiding` gebeurd. Elk ander raster deed het al goed,
en bij `.brand-projectraster` stond de uitleg er zelfs bij -- die was
alleen nooit op de buurman toegepast.

> Hoort erbij: de tekstkinderen krijgen `min-width: 0`. Zonder dat houdt
> het rastervak zich wel aan zijn kolom, maar steekt de tekst erbinnen er
> alsnog uit.

### Een breekregel helpt niet als de doos zelf meegroeit

Dit is het vervolg op de regel hieronder, en het kostte een horizontale
schuifbalk op elke kleine telefoon. `overflow-wrap: break-word` breekt een
woord af zodra het niet in zijn regel past -- maar **een flex-kind bepaalt
zijn eigen breedte vóór dat moment**. In een kolom groeit het tot zijn
langste woord, in een rij mag het niet smaller worden dan dat woord. De
doos wordt dus eerst te breed, en dán past het woord er netjes in.

Op de voorpagina en op Projecten werden de projecttitel, de uitgever van
een certificaat en de organisatie in de tijdlijn alle drie zo breed als
`Bedrijfsinformatievoorzieningsbeheerder`. Op een ruime telefoon paste dat
nog net; op een iPhone 16 niet. De tekst in zo'n tegel werd bovendien
afgeknipt door de `overflow: hidden` van de kaart.

Er staat nu één regel bij de breekregel op `.brand-dark-page` die
tekstelementen `min-width: 0` en `max-width: 100%` geeft -- het slot in een
rij en dat in een kolom, want je weet niet welke kant het op staat.

> En let op het patroon: `align-items: flex-start` of `center` op een
> kolom betekent dat élk kind op zijn eigen breedte uitkomt. Dat is precies
> waar dit misgaat.

### Een label is geen alinea, en moet dus een eind kennen

Niet alles is opgelost met laten afbreken. Een uitgever van een certificaat
en een organisatie in de tijdlijn zijn **regels van één regel**: in het
ontwerp staat daar "Microsoft · 2022". Het beheerscherm laat er honderd­
negentien tekens in, en die werden in een tegel van 124 pixels zeven regels
brokken -- binnen de kaart, dus geen schuifbalk, maar het zag er stuk uit.

Zulke velden krijgen een `line-clamp` met een beletselteken. De tekst
blijft heel in de HTML staan, dus een schermlezer en een zoekmachine
missen niets, en bij een tegel met details staat alles in het venster dat
opengaat.

> Vraag je bij een nieuw veld dus af of het een label is of lopende tekst.
> Een alinea mag lang zijn; een label hoort een eind te hebben.

**En `hyphens: auto` is hier géén verbetering -- dat is gemeten.** Op
lettergreep afbreken leest netter dan een knip midden in een letter, maar
het kost ruimte: hetzelfde woord in hetzelfde vakje werd drie regels in
plaats van twee. Met een klem erop verdwijnt er dan juist méér tekst. In
het Engels deed het bovendien niets, want een Nederlands woord staat niet
in het Engelse woordenboek van de browser.

### Lange tekst breekt niet vanzelf af

`text-wrap: pretty` maakt de regelverdeling mooi en doet **niets** aan een
woord dat niet past. Een geplakt adres of een samengesteld woord van veertig
tekens steekt uit zijn kaart, of wordt halverwege afgeknipt door een
`overflow: hidden` verderop.

Er staat nu één regel op `.brand-dark-page` die dat voor de hele publieke
site afvangt. `overflow-wrap` erft, dus dat is genoeg -- een lijst van
losse klassen wordt bij elke module langer en er wordt er altijd eentje
vergeten.

### Een kaart in een rij rekt zijn buren op

Rasters staan standaard op `align-items: stretch`. Eén kaart met een lange
tekst maakt elke kaart in die rij even hoog, en de korte buren krijgen het
verschil als leeg vlak. Zichtbaar lelijk zodra die kaart een rand of een
achtergrond heeft.

Klem daarom elke samenvatting die in een kaart staat (`line-clamp`), en
gebruik de volle tekst in het venster of op de pagina erachter. Dat ging
mis bij `.brand-projectkaart-zin`: hetzelfde veld werd in de lijstweergave
wél geklemd en op de kaart niet.

### Dezelfde klasse twee keer definiëren

`app.css` is één ongelaagd bestand, dus de laatste definitie wint **stil**.
`.brand-werkwijze-raster` stond er twee keer; de tweede zette de `gap` van
de eerste om van 2rem naar 1.5rem. Wie alleen de eerste las, las een
waarde die nergens gold.

Controleer vóór je een nieuwe `brand-`klasse schrijft of de naam vrij is;
het recept staat in `AGENTS.md`.

### Een doorlopende animatie hoort in een reduced-motion blok

Van de vijftien `infinite`-animaties in dit bestand waren er twee vergeten.
Twee vormen zijn goed: de animatie binnen `@media (prefers-reduced-motion:
no-preference)` zetten, óf hem in het `reduce`-blok op `animation: none`.

> **Een animatie die iets betekent zet je niet stil maar vast.** De
> vertaalbalk zegt dat er iets bezig is; `animation: none` zou betekenen
> dat iemand met bewegingsgevoeligheid niet meer ziet dát er gewacht wordt.
> Die krijgt een volle balk op halve dekking.

---

## Navigatie en scrollen

### Een taalwissel komt langs de paginaovergang, maar ís er geen

De taal zit in de sleutel van `<main>`, dus wisselen van taal laat Vue de
hele pagina weggooien en opnieuw opbouwen -- precies alsof je naar een
andere pagina gaat. Alles wat aan die overgang hangt draait dus mee, en
daar zat een sprong naar boven bij. **Je las halverwege een tekst, klikte
op EN, en stond weer bovenaan.**

Wat het zo laat vindbaar maakte: die sprong sloeg over als er een anker in
het adres stond. Dat anker komt er alleen bij een klik op een menu-item, en
Inertia gooit het er bij het volgende bezoek weer af. Dus de ene keer
gebeurde het wel en de andere keer niet, en het leek van de richting van de
wissel af te hangen. Dat was het niet.

Komt er ooit nog iets in die sleutel, stel dan dezelfde vraag: gaat de
bezoeker hier echt ergens heen, of ziet hij hetzelfde anders?

### `router.on('navigate')` vuurt niet als het adres gelijk blijft

Dit kostte een hele ronde. De eerste reparatie van de valkuil hierboven
hing aan `router.on('navigate')` om te zien of alleen de taal was
veranderd. In de router van Inertia staat:

```js
if (!replace) {
    fireNavigateEvent(page, { cached, visitId });
}
```

Een POST die met `back()` op dezelfde URL terugkomt -- de taalwissel, maar
ook een formulier dat zichzelf opnieuw laadt -- zet `replace` op waar.
**Er komt dan niets binnen, zonder enige foutmelding.** De vlag bleef dus
op `false` en de fout was er gewoon nog, terwijl de code klopte als je hem
las.

> Hang zulke dingen aan de reactiviteit van Vue -- een `watch` op een
> computed die uit de props komt -- en niet aan een gebeurtenis van het
> framework. Die computed verandert altijd.

### Dezelfde pixel is niet dezelfde plek

Toen de taalwissel eenmaal de scrollpositie bewaarde, verschoof er nog
steeds iets -- minder, maar zichtbaar. De oorzaak: de Engelse tekst is
korter, en op de voorpagina scheelt dat **ruim duizend pixels over de hele
pagina**. Bij een gelijke `scrollY` schoof de werkwijze 379 pixels en het
contactblok 1007.

Een positie bewaren is dus niet hetzelfde als een plek bewaren. Bewaar
waar de bezoeker naar kéék -- welk onderdeel, en hoeveel ervan al voorbij
was -- en reken de pixel daar achteraf uit.

> Pak daarvoor iets met een vaste naam. De eerste opzet keek naar elk
> element met een `id`; daar zitten formuliervelden tussen en uitklappers
> die bij elke opbouw een nieuw nummer krijgen. `section[id]` is wél stabiel.

### Een vangnet met dezelfde grens als de animatie dekt de rand niet

`alInBeeld` rekent tot 85% van de schermhoogte. Het vangnet dat onzichtbare
tekst moet aanzetten gebruikte diezelfde grens, dus **de onderste 15% van
het scherm viel buiten het vangnet**. Zolang elke paginawissel bovenaan
begon viel dat niemand op; zodra een taalwissel je op een willekeurige
hoogte liet staan, bleef er geregeld één blok leeg achter.

Vraag bij een vangnet dus niet "gebruikt het dezelfde regel als de rest",
maar "dekt het alles wat de bezoeker kan zien".

En: **een fout in het gedrag van een pagina bewijs je in een browser, niet
door de code te lezen.** Er staat een headless Chrome in
`~/.cache/ms-playwright`; die is via het DevTools-protocol aan te sturen
met kale Node (`WebSocket` zit sinds versie 22 in de standaardbibliotheek).
Een tijdlijn van `window.scrollY` had de eerste poging meteen onderuit
gehaald.

---

## Formulieren en grenzen

### Elk tekstveld krijgt een `maxlength` én een teller

`ErvaringDialoog` had geen van beide. De klant kon vijfduizend tekens
beschrijving typen terwijl de kaart op de voorpagina er drie regels van
toont -- en niets vertelde hem dat.

De grens hoort op drie plekken gelijk te lopen: de kolom in de migratie, de
`max:` in de FormRequest, en de `maxlength` plus teller in het venster. Op
`experience_stats.label_nl` na doen alle kolommen dat; houd dat zo.

### Een foutmelding mag niet meer beloven dan de grens

Drie meldingen zeiden "hoort op één regel te passen" bij grenzen van 60, 80
en 120 tekens. Dat is niet waar, en de klant die het leest gaat iets anders
doen dan hij bedoelde.

Kies: óf de tekst klopt met de grens, óf de grens gaat omlaag. Een grens
verlagen keurt wel bestaande tekst af, dus dat is geen vrijblijvende keuze.

### Een gedeeld validatieveld neemt de ruimste van zijn gebruikers

`TranslateController` heeft één witte lijst voor alle modules. Staat
`result_nl` daar op 160 (de maat van een werkwijzestap) terwijl een project
4000 toestaat, dan valt bij een lang projectresultaat **het hele
vertaalverzoek** om -- niet dat ene veld. De klant krijgt alleen "het
vertalen is niet gelukt", en ook zijn titel komt niet terug.

Dat is drie keer gebeurd. `VertaalgrenzenTest` bewaakt het nu, en leest de
grens uit de module zelf in plaats van hem over te typen.

---

## De publieke site

### Een `backdrop-filter` vangt wat erin `position: fixed` staat

Een element met `backdrop-filter` (of `transform`, of `filter`) wordt het
kader voor zijn vaste kinderen. `position: fixed; inset: 0` vult dan niet
het scherm maar dat element. Dat gebeurde bijna met het mobiele menu: het
staat in de kop, en de vervaging stond op `<header>`. Daarom zit hij nu op
de pil erbinnen; zie `resources/css/site/kop.css`.

### Een vast element dat buiten het scherm uitsteekt, maakt de pagina breder

Op een telefoon telt een vast element dat over de rand loopt mee voor de
breedte van de pagina: de browser zoomt uit, en je kunt zijwaarts schuiven
terwijl er in de opmaak niets mis lijkt. Het lichtveld had `inset: -20vmax`
om bij het schuiven geen rand te tonen. Nu knipt het vaste vlak zichzelf af
en staat het grotere licht in een `::before`.

Meet het met de breedte die je zelf opgaf en niet met `innerWidth`: in een
mobiele emulatie groeit `innerWidth` gewoon mee, en dan zegt een controle
op `scrollWidth > innerWidth` dat alles in orde is.

### Een venster staat niet in `.brand-site`

De vensters van reka-ui staan in een portaal aan het eind van `<body>`. Wat
via `.brand-site` geërfd wordt -- het lettertype, de kleur, de variabelen
die alleen daar staan zoals `--site-ease` -- komt daar niet aan. Zet het op
het venster zelf (`.brand-blad` in `resources/css/site/vensters.css`), en
gebruik in een venster alleen de tokens uit `:root`.

### `var(--x), terugval` is niet hetzelfde als `var(--x, terugval)`

Staat `--font-geist` niet klaar (in het portaal laadt Geist niet), dan
maakt `font-family: var(--font-geist), sans-serif` de **hele** regel
ongeldig en valt de browser terug op zijn standaardletter -- niet op
`sans-serif`. Geef de terugval dus binnen de `var()` mee:
`var(--font-geist, 'Instrument Sans'), ...`.

### Een kopbalk die op een laptop past, past niet vanzelf op een tablet

Tussen 50rem en een laptop staan het merk, vijf menu-items, de taalknop en
de contactknop naast elkaar. Op 820 pixels vroegen ze samen 899, en de
pagina ging zijwaarts schuiven -- alleen op een tablet, dus op een
telefoon en een laptop zag je niets. Nu meet de kop zelf hoeveel items er
passen (`pasIn()` in SiteHeader.vue, zie pagina-indeling.md), en valt
tussen 50 en 64rem de naam naast het merkteken weg. Meet elke wijziging
aan de kopbalk toch op 800, 820, 1024 en 1440 pixels, in beide talen.

Een onzichtbare meetrij steekt nog steeds uit: `visibility: hidden` telt
mee voor de schuifbreedte. Zet hem in een doosje van nul breed met
`overflow: hidden`.

### Geen `stagger` in een scrub met berekende afstanden

Een `fromTo` met `scrub`, `stagger` en een afstand als functie
(`x: () => ...`, met `invalidateOnRefresh`) tekent de beginstand van een
later element pas als zijn beurt in de stagger komt. Bij de projectkaarten
stonden de tweede en derde kaart daardoor eerst op hun plek en schoten ze
pas daarna naar rechts weg; de rechterkolom van de kerngegevens deed
hetzelfde. Laat alle elementen tegelijk beginnen, en maak de waaier met de
afstand: elk element start verder weg en legt zijn weg dus sneller af.

### Een globale regel met een klasse ervoor weegt mee

In `app.css` krijgen tekstelementen op de publieke site `min-width: 0` en
`max-width: 100%`, als vangnet tegen lange woorden. Die regel was bedoeld om
niets te wegen, maar stond als `.brand-dark-page :where(span, p, ...)`: het
deel buiten de `:where()` telt wél, één klasse, net zoveel als de klasse
van een module. Bij gelijk gewicht wint wat later komt, en app.css komt na
de site-bestanden. Zo viel **elke** `max-width` op een kop, inleiding of
label stil terug op 100%: inleidingen liepen over de volle breedte, het
verhaal op /over-mij had regels van 110 tekens, en de meetrij van het
menu mat alleen zijn opvulling.

Eerst werd dat per geval omzeild met een extra klasse ervoor (de band in
Ervaring). Nu staat ook `.brand-dark-page` in een `:where()` en weegt de
regel echt nul. **Een vangnet dat breed geldt, schrijf je met
`:where()` om de hele selector**, niet alleen om het laatste deel. Meet bij
twijfel `getComputedStyle(el).maxWidth` in de browser: staat er `100%`
waar je iets anders schreef, dan wint er iets anders.

### Een hover zonder muis blijft hangen

Op een telefoon blijft `:hover` na een tik staan tot je ergens anders
tikt: een kaart die opgelicht blijft, een link die van kleur verschoten
lijkt. Zet hover op de publieke site altijd in
`@media (hover: hover) and (pointer: fine)`, en geef toetsenbordfocus
(`:focus-visible`) zijn eigen regel erbuiten. `:active` mag wel overal.
Zie "Eén taal voor alles wat je aanraakt" in
[frontend-en-animatie](../architecture/frontend-en-animatie.md).

### `history.replaceState(null, ...)` wist Inertia's geheugen

Inertia bewaart de pagina en de scrollpositie in `history.state`. Een anker
bijwerken met `replaceState(null, '', '#x')` gooit dat weg, en de
terugknop werkt daarna niet meer: menu → Werkwijze → /werkwijze → terug
bleef op /werkwijze staan. Geef de bestaande state mee:
`replaceState(history.state, '', '#x')`.

## Modules

### Een route zonder methode valt pas om als de klant klikt

`website.werkwijze.kop` wees naar `WorkStepController::kop`, en die methode
bestond niet. De route stond in de lijst, de knop stond op het scherm, en
op opslaan kreeg de eigenaar een 500. Het was de enige module zonder test
op die route.

`KoptekstRoutesTest` loopt nu **alle** kop-routes af en controleert dat de
methode bestaat. Bouw je iets vergelijkbaars, maak de test dan net zo: over
de hele lijst en niet per module, want een moduletest kent alleen zijn
eigen module.

### Eén kolom voor twee vensters gaat stil mis

De voettekst had één `machine_translated_at` voor twee vertaalbare velden
in twee verschillende vensters. Allebei schreven ze hun eigen oordeel in
die ene kolom, dus het ene venster wiste het merkje van het andere.

Heeft een module twee velden die apart worden bewerkt, dan horen daar twee
kolommen bij.

### Een ijkwaarde moet bijgewerkt worden ná de handeling

Vijftien vensters bepaalden "is dit nog een machinevertaling?" door het
Engels te vergelijken met wat er stónd toen het venster openging. Na een
vertaling is dat per definitie anders, dus het merkje werd **nooit** gezet.

De documentatie zei het goed: vergelijk met **wat de dienst voorstelde**.
Twee vensters deden dat ook; de andere vijftien hadden een vereenvoudiging
overgenomen die de functie sloopte.

### Opruimen van voorbeelddata gaat per veld, niet per rij

Bij een project of een kerngegeven is een rij één ding: hoort hij bij de
voorbeelddata, dan mag hij in zijn geheel weg. De voettekst is één rij met
zeven losse velden, en de eigenaar kan daar zijn eigen telefoonnummer in
hebben gezet terwijl de geseede zin eronder nog staat. `delete()` nam dat
mee.

En: tel élke module mee in de nulcontrole van `voorbeeld:opruimen`. De
werkwijze stond er niet in, dus stond alleen die nog in de database, dan
meldde het commando "er staat geen voorbeelddata" en bleven er vier
verzonnen stappen op een site die live kan zijn.

### Een massa-update slaat het activiteitenlogboek over

`LogsActivity` haakt op modelgebeurtenissen. `Model::query()->update()`
vuurt die niet af, dus die wijziging komt nergens in het logboek -- en de
module kan dan ook niet zien óf er iets veranderde, dus hij meldt altijd
succes.

---

## Documentatie

### Een lijst die onwaar is, is erger dan geen lijst

`docs/openstaand.md` beweerde dat de werkwijze nog gebouwd moest worden en
dat er geen back-upmodule was. Allebei waren ze af. Een lezer die dat
gelooft doet het verkeerde werk.

Haal een punt weg zodra het klaar is, en zet hetzelfde punt **nooit in twee
documenten**. Dat is hier ook gebeurd: vier punten stonden zowel in
`openstaand.md` als in `deployment.md`, en die twee gaan vroeg of laat
uiteen lopen.

### Commentaar veroudert sneller dan code

Dit project schrijft veel uitleg, en dat is de kracht ervan -- maar het
betekent ook dat een wijziging op twee plekken moet landen. Er stonden
docblocks die het tegenovergestelde beweerden van de code eronder, een
opsomming van "vijf dingen" met zes punten, en een getal dat na een
wijziging nergens meer op sloeg.

Pas je gedrag aan, lees dan het commentaar erboven **helemaal** door.

---

## Werkwijze

### Doe een proefronde voordat je veel bestanden tegelijk aanraakt

Twee keer in één ronde zou een mechanische vervanging het verkeerde hebben
geraakt: één script pakte de declaratie `let engelsBijOpenen = ''` in plaats
van de toekenning eruit, en een ander zou de annuleerknop hebben omgezet op
knoppen die helemaal niet annuleren.

Allebei kwam aan het licht door eerst zonder te schrijven te draaien en de
uitkomst te lezen. Dat kost een minuut.

### Een test die ook zonder de fix slaagt, bewijst niets

Controleer na een reparatie dat de test die erbij hoort werkelijk zou zijn
omgevallen. Bij `KoptekstRoutesTest` is dat nagegaan door de lijst routes
uit te draaien en te zien dat er één ontbrak.

### Je kunt geen pagina renderen; maak het zichtbaar

De gewone voorbeelddata is nette, korte tekst en zegt dus niets over wat er
bij extreme invoer gebeurt. `php artisan voorbeeld:oprekken` vult elk veld
tot zijn maximum, zet er een onbreekbaar woord van veertig tekens naast, en
een vakje met twee tekens ernaast -- de drie gevallen waar opmaak op
stukgaat.

## Zie ook

- [Testen](testen.md) -- wat elke module moet afdekken
- [Formulieren en schuifbalken](../architecture/formulieren-en-schuifbalken.md)
- [Openstaande punten](../openstaand.md)
