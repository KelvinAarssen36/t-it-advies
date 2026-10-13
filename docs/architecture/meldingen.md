# Meldingen

De berichtjes die rechtsonder verschijnen nadat je iets hebt gedaan.

## Vijf soorten, en waarom precies vijf

| Soort        | Wanneer                               | Kleur     | Pictogram          |
| ------------ | ------------------------------------- | --------- | ------------------ |
| `aangemaakt` | Er is iets bijgekomen                 | Groen     | Plus in een cirkel |
| `bijgewerkt` | Er is iets veranderd                  | Merkblauw | Potlood            |
| `verwijderd` | Er is iets weg                        | Rood      | Prullenbak         |
| `melding`    | Een mededeling; er is niets gewijzigd | Cyaan     | Informatie         |
| `fout`       | Het is niet gelukt, of het mocht niet | Rood      | Waarschuwing       |

Dit zijn precies de dingen die je van elkaar moet kunnen onderscheiden
**zonder te lezen**. Dat is het hele doel: je klikt, je kijkt al ergens
anders, en in je ooghoek zie je of er iets bij is gekomen of juist weg is.
Een reeks identieke groene vinkjes doet dat niet.

Verwijderen en een fout delen dezelfde kleur, en dat klopt: allebei zijn het
dingen waar je even bij stil wilt staan. Het pictogram maakt het verschil.

**Een melding is geen bevestiging.** Dit is wat er is gebeurd, niet een
vraag of het mag. Vooraf bevestigen doe je met het venster hieronder.

## Het bevestigingsvenster

De afspraak, en die geldt voor elk beheerscherm:

| Handeling              | Aantal vragen                                                                                        |
| ---------------------- | ---------------------------------------------------------------------------------------------------- |
| Bestaand item bewerken | **Twee.** Eerst de vraag zelf, daarna "Weet je het 100% zeker? Dit staat direct live op de website." |
| Nieuw item aanmaken    | Eén.                                                                                                 |
| Verwijderen            | Eén.                                                                                                 |

**Waarom bewerken twee keer vraagt en verwijderen één keer.** Wat de klant
aanpast staat meteen op de website die zijn eigen klanten bezoeken; er zit
geen concept of publicatieknop tussen. De tweede vraag zegt dat ook
letterlijk, en juist daarom staat die tekst vast in het component en niet
bij de aanroeper -- anders verwatert hij tot "weet je het zeker?" en vraag
je twee keer hetzelfde. Verwijderen is onomkeerbaar maar ook onmiskenbaar:
je klikt op een prullenbak. Een tweede vraag maakt daar een reflex van, en
een reflex leest niemand meer.

### Hoe je hem gebruikt

Met [`bevestig()`](../../resources/js/lib/bevestiging.ts), als functie die
je kunt awaiten:

```ts
const akkoord = await bevestigBewerken({
    titel: t('De indeling van je website aanpassen?'),
    tekst: t('De onderdelen komen in deze volgorde te staan.'),
});

if (!akkoord) {
    return;
}
```

Er zijn drie ingangen -- `bevestigAanmaken`, `bevestigBewerken` en
`bevestigVerwijderen` -- en die bepalen de kleur, het pictogram én het
aantal vragen. Het aantal staat dus niet bij de aanroeper, zodat niemand
het per ongeluk anders invult.

De kleur is die van de handeling zelf: blauw bij aanmaken, oker bij
bewerken, rood bij verwijderen. Dezelfde drie als de knop waarop je zojuist
klikte, in de accentlijn én op de bevestigknop, zodat het venster zichtbaar
bij die knop hoort. Zie
[de drie handelingen](huisstijl-en-kleuren.md#de-drie-handelingen).

Bij aanmaken stond hier eerder groen, geleend van de melding achteraf. Dat
was een andere vraag: groen zegt "gelukt", en op het moment van bevestigen
weet je dat nog niet.

Het venster zelf,
[`ConfirmDialog`](../../resources/js/components/ConfirmDialog.vue), hangt
één keer in de layout van het portaal. Je hoeft het nergens te plaatsen.
Alle teksten staan daar, want dat is de plek waar `$t()` bestaat; de module
met de logica bevat geen woord Nederlands.

### Een keuze in de bevestiging

Soms hoort er bij een handeling nog één beslissing die níet in het
formulier ervoor thuishoort: bij het toevoegen van een ervaring is dat "wil
je hem ook meteen op je website zetten?". Daarvoor kan er een schuifje in
het venster:

```ts
const meteenOnline = ref(true);

const akkoord = await bevestigAanmaken({
    titel: t('Deze ervaring toevoegen?'),
    keuze: {
        model: meteenOnline,
        label: t('Meteen op je website zetten'),
        tekst: t(
            'Zet je hem uit, dan bewaren we hem wel maar zien bezoekers hem niet.',
        ),
    },
});
```

Het antwoord komt in je eigen `ref` te staan; de belofte blijft gewoon een
`boolean`. Dat is met opzet -- zo hoeft geen enkele bestaande aanroep mee
te veranderen, en staan de vraag en wat ermee gebeurt nog steeds op
dezelfde plek in de code.

Het schuifje verschijnt alleen op de **eerste** stap. Bij bewerken is de
tweede stap er om te bevestigen wat je al besloten hebt, en daar hoort geen
nieuwe keuze meer bij.

Eén technisch gevolg: `bevestigStaat` is `shallowReactive` en niet
`reactive`. Een diep reactief object pakt elke `Ref` erin automatisch uit,
en juist die `model` moet een `Ref` blijven -- dat is de draad terug naar
de aanroeper.

### Het venster sluiten en heropenen zonder de invoer te verliezen

Vraag je een bevestiging vanuit een formulier in een ander venster, dan
gaat dat venster eerst dicht -- twee dialogen over elkaar laten
`pointer-events` op elkaar achter. Daarna gaat het weer open: als de klant
annuleert, en als de server een validatiefout teruggeeft.

**Vult dat venster zich bij het openen uit zijn props, dan is de invoer
weg.** Dat is precies wat er gebeurde: je drukte op Opslaan, de server
weigerde een te lange tekst, het venster kwam terug met de oude inhoud en
zónder de foutmelding -- die werd bij dat vullen ook gewist.

Los het op met een vlag die zegt "ik open mezelf, niet de gebruiker":

```ts
let behoudInhoud = false;

const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
};

watch(open, (isOpen) => {
    if (!isOpen) {
        behoudInhoud = false;
        return;
    }
    if (behoudInhoud) {
        behoudInhoud = false;
        return;
    }
    vulIn();
});
```

Zie `ErvaringDialoog.vue` en `CijfersDialoog.vue`. Bouw je een nieuw
formulier met een bevestiging erin, neem dit dan over.

### Dit is geen beveiliging

Een bevestiging die in de browser staat, kun je in de browser overslaan. Wat
echt niet mag, hoort op de server te worden tegengehouden -- met een recht,
een validatieregel of een verse authenticator-code. Zie
[gevoelige acties](../security/gevoelige-acties.md).

## Hoe je er een stuurt

Vanuit de controller, met [`App\Support\Toast`](../../app/Support/Toast.php):

```php
Toast::aangemaakt(__('De dienst is toegevoegd.'));
Toast::bijgewerkt(__('De tekst is aangepast.'), __('Hij staat nu live.'));
Toast::verwijderd(__('De dienst is verwijderd.'));
Toast::melding(__('Er is een e-mail onderweg.'));
Toast::fout(__('Dat is niet gelukt.'));
```

Altijd met `__()`, want deze teksten leest de klant; zie
[vertalingen](vertalingen.md).

**De omschrijving is optioneel** en hoort iets toe te voegen, niet de titel
te herhalen. Gebruik hem voor het gevolg: wat er nu op de website staat, of
wat de volgende stap is. "Je wachtwoord is gewijzigd" + "Bewaar het ergens
veilig; we kunnen het niet voor je terughalen."

**Het gaat via `Inertia::flash` en niet via de gewone sessie-flash.** Die
laatste overleeft alleen een omleiding, en de helft van deze meldingen komt
uit een handeling die op dezelfde pagina blijft -- een rol wijzigen
bijvoorbeeld.

## Hoe ze eruitzien

Eén CSS-variabele, `--toast-accent`, stuurt zowel de streep links als het
pictogram aan. Een nieuwe soort is daardoor één regel. De rest -- de
achtergrond, de rand, de tekstkleur -- komt uit de rol-tokens, dus het
klopt in de huisstijl én in het lichte thema.

De selectoren noemen `[data-sonner-toast]` naast onze eigen klasse. Dat is
geen overdaad: vue-sonner brengt een eigen stylesheet mee die buiten onze
CSS-lagen valt, en met alleen `.brand-toast` hangt het van de volgorde af
wie er wint. Dezelfde truc staat uitgelegd in
[formulieren en schuifbalken](formulieren-en-schuifbalken.md).

**Een fout blijft langer staan** (negen tegen viereneenhalve seconde). De
andere meldingen bevestigen iets dat je zelf net deed en die mag je missen;
een fout moet je lezen, en misschien twee keer.

### Meerdere tegelijk: een stapel die uitklapt als je erop gaat staan

Komen er meerdere meldingen, dan legt vue-sonner ze als een pak kaarten op
elkaar: de voorste is te lezen, de rest wordt kleiner geschaald, op de
hoogte van de voorste gezet (`height: var(--front-toast-height)`) en
erachter geschoven. Ga je er met de muis op staan, dan klapt de stapel uit
en zie je ze allemaal.

Dat is de standaard van vue-sonner, en het **blijft** zo. De eigenaar heeft
daar expliciet om gevraagd: "dat je er muis op kan houden zoals eerst en dat
dan alle meldingen zich opstapelen zodat je ze toch allemaal kan zien".

#### Waarom dat stapelen eerst niet werkte

> **De oorzaak was één regel van onszelf: `position: relative` op
> `.brand-toast`.** Die stond er zodat de streep links als pseudo-element
> geplaatst kon worden, en hij was nooit nodig -- maar hij was wel
> specifieker dan de `position: absolute` die vue-sonner met
> `[data-sonner-toast]` op elke melding zet. Onze regel won dus altijd.
>
> Daarmee stonden de meldingen in de **normale tekststroom** in plaats van
> absoluut in hun hoek, terwijl vue-sonner ze ondertussen wél verschoof met
> `translateY`, op de hoogte van de voorste zette en schaalde. Bij één
> melding valt dat niet op: die staat toch op zijn plek. Bij twee of meer
> wordt het een stapel die niet klopt, met kaarten en gekleurde randjes die
> scheef onder elkaar uit piepen.
>
> Dat is ook precies hoe de eigenaar het meldde, in twee rondes: eerst "als
> je meerdere meldingen krijgt valt dat soms een beetje raar over elkaar
> heen", daarna "hij blijft er dan achter staan".

**De les.** Een `position` op een component uit een pakket is geen
onschuldige toevoeging. Dit soort bibliotheken rekent op zijn eigen
positioneringsmodel, en een specifiekere regel van ons zet dat stil zonder
een foutmelding -- het ziet er in het eenvoudigste geval nog goed uit en
valt pas om zodra er een tweede element bij komt.

**Twee eerdere pogingen waren het verkeerde antwoord**, en staan hier
omdat ze er logisch uitzagen:

1. **`expand` op de Toaster** om de stapel uit te zetten. Dat werkte en het
   zag er netjes uit, maar het kostte het uitklappen bij hover -- en dat was
   juist wat er goed aan was.
2. **De streep verbergen** op een melding die achteraan ligt. Dat was een
   echte verbetering (zie hieronder), maar het bestreed een symptoom: de
   kaarten zelf stonden nog steeds verkeerd.

#### De streep zit in de box-shadow, niet op een pseudo-element

Vue-sonner gebruikt `::before` op een melding zélf, als onzichtbaar
raakvlak voor het wegvegen (`left: -100%; right: -100%`, drie keer de
hoogte) en voor het verdwijnen. Twee partijen op één pseudo-element is
precies hoe dit soort fouten ontstaat, dus de streep zit nu in een
`inset`-schaduw:

- Die kan per definitie niet buiten de kaart vallen en volgt de ronde
  hoeken vanzelf.
- Er hoeft geen `overflow: hidden` bij -- en dat is winst, want daarmee
  knipten we het raakvlak voor het wegvegen af.
- Hij zit in een eigen variabele (`--toast-streep`), dus een melding die
  achteraan ligt zet alleen die variabele leeg. Omdat de rest van de
  schaduw gelijk blijft, loopt dat mee met de `box-shadow`-overgang die
  vue-sonner er zelf al op heeft: hij vaagt in en uit in plaats van te
  springen.

Dat verbergen hoort erbij: vue-sonner vaagt de inhoud van de kaarten
achteraan weg, zodat je een stapel blanco kaarten ziet. Een gekleurd
randje van een melding die je niet kunt lezen, zegt niets.

Drie tegelijk blijft het maximum -- de standaard van vue-sonner -- dus een
reeks snelle handelingen kan het scherm niet vullen.

### Ze gaan mee met licht of huisstijl

De `Toaster` krijgt het thema van het portaal mee (`:theme="appearance"`,
uit `useAppearance`). De standaard van vue-sonner is `light`, en dat is hier
altijd fout: het portaal staat standaard in de huisstijl.

De kleuren van de kaart zelf gingen altijd al mee -- de achtergrond, de
rand en de tekst komen uit de rol-tokens (`--popover`,
`--popover-foreground`, `--border`), en de accenten uit `--success`,
`--primary`, `--destructive` en `--brand-cyan`. Wat niet meeging is de
grijstrap van vue-sonner zelf, die aan `data-theme` op het vak eromheen
hangt. Die is nu goed, en dat telt zodra er iets bij komt dat daarop rekent
-- een sluitknop bijvoorbeeld.

Het haarlijntje om de kaart volgt sinds dezelfde ronde ook de tekstkleur van
het vlak in plaats van een vaste witte lijn te zijn. In de huisstijl ziet dat
er hetzelfde uit als eerst; in het lichte thema was het wit op wit en dus
onzichtbaar.

## Bij een nieuwe CRUD

1. Bij opslaan: `Toast::aangemaakt(...)` of `Toast::bijgewerkt(...)`.
2. Bij verwijderen: `Toast::verwijderd(...)`.
3. Bij een geweigerde handeling: `Toast::fout(...)` -- **geen** `melding`.
   Je dacht dat er iets zou gebeuren en dat gebeurde niet; dat moet er
   anders uitzien dan een bevestiging, anders lees je eroverheen en denk je
   dat het gelukt is.
4. Zet in de test van die CRUD dat de **soort** klopt, niet alleen dat er
   iets verschijnt. Zie [`ToastTest`](../../tests/Feature/ToastTest.php).

## Wat hier níet thuishoort

- **Validatiefouten.** Die horen bij het veld waar ze over gaan, met
  `InputError`. Een melding rechtsonder laat je zoeken naar wat er mis is.
- **Iets dat je moet onthouden.** Een melding is na een paar seconden weg.
  Moet de klant er iets mee, zet het dan op de pagina zelf.
- **Bevestigingsvragen.** Zie hierboven.
