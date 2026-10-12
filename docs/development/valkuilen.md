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
