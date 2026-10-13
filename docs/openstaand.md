# Wat er nog open staat

Een korte, eerlijke lijst. Niet alles hoeft meteen af -- maar het moet wel
érgens staan, anders is "dat doen we later" hetzelfde als "dat doen we
niet".

Haal een punt hier weg zodra het klaar is. Een lijst met afgevinkte dingen
erin leest niemand meer -- en erger: een lijst die beweert dat iets nog
moet gebeuren terwijl het er al is, stuurt de volgende lezer het verkeerde
werk in. Dat is hier twee keer gebeurd (de modules en de back-ups stonden
er nog op nadat ze af waren), en daarom staat deze regel er nu met nadruk
bij.

## Voor livegang

> De praktische lijst -- wat je moet uitzoeken bij Strato, aanvragen bij
> anderen en op het echte domein controleren -- staat in
> [livegang](operations/livegang.md). Hieronder staat alleen wat er nog
> _gebouwd of besloten_ moet worden.

### Wat er bij de livegang moet gebeuren, staat ergens anders

Hier stonden vier punten: de cron-interval van het hostingpakket uitzoeken,
de privacyverklaring juridisch laten nalezen, een adres voor
beveiligingsmeldingen kiezen, en passkeys op het echte domein nalopen.

**Ze zijn verhuisd naar [livegang](operations/livegang.md)**, en dat is geen
opschoning maar een correctie. Het zijn alle vier dingen die een mens moet
doen en niet dingen die nog gebouwd moeten worden -- ze hoorden dus niet op
een lijst met openstaand werk. Erger: ze stonden óók al in
[deployment](operations/deployment.md), en twee lijsten over hetzelfde gaan
vroeg of laat uiteen lopen. Precies dat is dit document al een keer
overkomen.

Wat hieronder blijft staan is wat er nog **gebouwd of besloten** moet
worden.

### De foutpagina van de landing

Het portaal heeft er een; de landing krijgt voorlopig de standaardpagina van
Laravel. Wat die variant anders moet doen staat in
[foutpagina's](architecture/foutpaginas.md).

### Een opschrift boven de tijdlijn

Sinds het samenvoegen van de koptabellen heeft élk onderdeel een kolom
voor een opschrift -- ook de tijdlijn, die er nooit een had. Het
bewerkvenster van de tijdlijn laat dat veld niet zien, en de seeder vult
het niet, want dat samenvoegen was een verhuizing en geen herontwerp van
de voorpagina.

Wil de klant er later een, dan is het één veld in het bewerkvenster en
`opschriftVerplicht()` op `false` laten staan. Zie
[kopteksten](architecture/kopteksten.md).

### Een venster dat met Escape dichtgaat

Wie een bewerkvenster met Escape sluit, verliest zijn wijzigingen zonder
melding. Bewust nog niet gebouwd: het venster is een afgebakende handeling
met een zichtbare knop "Annuleren", en een bevestiging om een bevestiging
heen maakt het niet veiliger.

Hier stond "zodra er meer schermen met een bewerkvenster zijn, is het de
moeite waard om het één keer goed te doen". Dat moment is voorbij -- het
zijn er inmiddels eenendertig -- maar bij het natellen bleek de prijs
hoger dan die zin suggereerde. Het gedeelde `DialogContent` kan niet weten
óf er iets is gewijzigd; dat moet elk venster zelf melden. Het zijn dus
eenendertig aanpassingen en niet één, voor een handeling waar al een
zichtbare uitweg voor is.

Blijft staan als bewuste keuze, niet als vergeten punt.

## Wat hier bewust níet op staat

- **Een foutmelder van een externe dienst** (Sentry, Flare). Er is nu een
  eigen crashmelding per e-mail; zie [monitoring](operations/monitoring.md).
  Dat is genoeg tot het aantal meldingen onhandelbaar wordt.
- **Een foutenscherm in het portaal.** Bewust niet: stacktraces en
  ontwikkelaarstaal horen niet bij de eigenaar van de website, en met één
  rol ziet hij alles wat in het menu staat. Waar je dan wél kijkt staat in
  [monitoring](operations/monitoring.md).
- **Een Content-Security-Policy.** De andere beveiligingskoppen staan er;
  waarom de CSP apart aandacht verdient staat in
  [headers](security/headers.md).
