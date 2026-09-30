# De handleiding voor de eigenaar

Er zijn in dit project **twee** soorten documentatie, en ze hebben niets met
elkaar te maken.

| Waar                                                                              | Voor wie                   | Waarover                                                                  |
| --------------------------------------------------------------------------------- | -------------------------- | ------------------------------------------------------------------------- |
| `docs/` -- deze map                                                               | Wij, en de volgende AI     | Code, keuzes, valkuilen, waarom iets zo gebouwd is                        |
| [`settings/Documentatie.vue`](../../resources/js/pages/settings/Documentatie.vue) | De eigenaar van de website | Knoppen: wat er gebeurt als je erop drukt, en wat zijn bezoekers dan zien |

De klant leest `docs/` nooit. Wij lezen de pagina in het portaal nooit. Ze
lopen dus ook niet vanzelf gelijk, en daar is deze afspraak voor.

## De regel

> **Elk afgerond onderdeel krijgt een kaart in de handleiding, in dezelfde
> wijziging waarin het onderdeel af is.**

Dit is regel 1 uit [`AGENTS.md`](../../AGENTS.md), toegepast op de andere
lezer. Een module die de eigenaar niet kan vinden is geen module: hij weet
dan wel dat er een scherm bij is gekomen, maar niet wat er gebeurt als hij
op iets drukt, en dat is precies het moment waarop hij ons gaat bellen.

"Afgerond" betekent: de klant kan er iets mee. Een migratie zonder scherm
hoort er niet bij. Een beheerscherm waarop hij kan toevoegen, wijzigen of
verwijderen wél -- en dan in dezelfde wijziging, niet in een volgende.

## Waar de pagina staat

In de instellingen, onder Weergave:
`/settings/documentation`, met de naam `documentation.show`. Hij staat
bewust **niet** in de zijbalk van de website: je zoekt hem op als je iets
niet weet, en niet elke dag.

Het is een `Route::inertia()` zonder controller. Alles wat erop staat
beschrijft hoe het portaal werkt, en dat komt uit de code. Er hoort hier dus
geen tabel in de database bij; als de uitleg moet veranderen, is dat omdat
wij iets gebouwd hebben.

## Hoe hij is ingedeeld

Drie onderdelen, en die volgen de zijbalk van het portaal:

| Onderdeel   | Wat erin hoort                                                                  |
| ----------- | ------------------------------------------------------------------------------- |
| **Basis**   | Alles wat overal hetzelfde werkt. Niet een scherm, maar een gewoonte.           |
| **Website** | Eén kaart per module uit de groep Website, in dezelfde volgorde als de zijbalk. |
| **Beheer**  | Eén kaart per scherm uit de groep Beheer.                                       |

**Basis is het onderdeel dat het snelst rommelig wordt.** Daar hoort alleen
in wat de eigenaar op meer dan één scherm tegenkomt: de kleur van een knop,
de sterretjes, de twee talen, online en offline, zoeken. Gaat het maar over
één scherm, dan hoort het bij dat scherm.

## Zo voeg je een kaart toe

1. Bepaal in welk onderdeel hij hoort. Twijfel je tussen Basis en een
   module, kies dan de module -- Basis wordt vanzelf te lang.
2. Zet er een
   [`UitlegKaart`](../../resources/js/components/settings/UitlegKaart.vue)
   neer met een titel en een pictogram uit `@lucide/vue`. Gebruik hetzelfde
   pictogram als in de zijbalk, zodat het teken de eigenaar naar het juiste
   scherm wijst.
3. Schrijf de uitleg als antwoord op een vraag die de eigenaar echt heeft:
   "waarom zie ik dit niet op mijn website?", "wat gebeurt er als ik dit
   weghaal?". Niet als opsomming van wat er op het scherm staat -- dat ziet
   hij zelf.
4. Kan het besproken ding getóónd worden -- een knop, een tekentje -- zet het
   dan in de `voorbeeld`-slot. Een knop uitleggen met de knop ernaast
   scheelt een alinea. Zo'n voorbeeldknop krijgt `tabindex="-1"` en
   `aria-hidden="true"`, want hij doet niets; een link die wél ergens heen
   gaat is gewoon een link.
5. Elke zin door `$t()`, en de Engelse kant in `lang/en.json`.
   `TranslationsTest` valt om als je dat vergeet.

### Een kaart die bij een scherm hoort

Niet elk onderwerp is een scherm. Het bijsnijden van een logo, het
automatisch verkleinen en de kop boven de tijdlijn zitten alle drie
achter een knop op de ervaringenpagina -- maar als kaart stonden ze er
als los onderwerp bij, en dan zoekt de eigenaar zich suf.

Geef zo'n kaart daarom `:onder="$t('Ervaring')"`. Er komt dan een klein
label boven de titel ("Onderdeel van Ervaring") en de kaart springt een
stukje in, zodat hij ook zichtbaar een onderdeel van iets is in plaats
van een gelijke.

**Doe dat voor elk onderwerp dat achter een knop op een ander scherm
zit.** De vuistregel: kun je er niet komen zonder eerst ergens anders
heen te gaan, dan hoort dat "ergens anders" in het label.

## Waarover je niet schrijft

- **Geen techniek.** Geen Laravel, geen Inertia, geen tabelnamen. De
  eigenaar hoeft niet te weten dat een logo door GD gaat; hij moet weten dat
  hij zelf mag inzoomen.
- **Geen geheimen, en ook geen halve.** Regel 2 geldt hier net zo hard. Op
  deze pagina staat dat recovery codes nooit in het logboek komen -- niet
  hoe ze eruitzien.
- **Geen dingen die er niet zijn.** Geen voorbeelden van een module die we
  ooit nog gaan bouwen. Wat hier staat, kan hij vandaag uitproberen.
- **Geen beloftes over gedrag dat nergens afgedwongen wordt.** Schrijf je op
  dat iets niet kan, zorg dan dat er een test is die dat bewaakt.

## Tests

[`DocumentationTest`](../../tests/Feature/Settings/DocumentationTest.php)
controleert dat de pagina achter de inlog zit en dat hij blijft bestaan. Dat
laatste klinkt overbodig, maar een handleiding die stilletjes verdwijnt
merkt niemand -- tot de eigenaar hem nodig heeft.
