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
vraag of het mag. Vooraf bevestigen doe je met een venster; zie de afspraak
over de dubbele bevestiging bij het wijzigen van live inhoud in
[beheerbare inhoud](../../AGENTS.md).

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
