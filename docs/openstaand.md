# Wat er nog open staat

Een korte, eerlijke lijst. Niet alles hoeft af voordat we aan de modules
beginnen -- maar het moet wel érgens staan, anders is "dat doen we later"
hetzelfde als "dat doen we niet".

Haal een punt hier weg zodra het klaar is. Een lijst met afgevinkte dingen
erin leest niemand meer.

## Voor de eerste module

### Het dashboard

Nog vier vlakken met een streepjespatroon. Er hoort in elk geval een klok in
het middelste vlak bovenin; dat was een expliciete wens. Dit is het eerste
scherm dat de eigenaar na het inloggen ziet.

## Voor livegang

### De foutpagina van de landing

Het portaal heeft er een; de landing krijgt voorlopig de standaardpagina van
Laravel. Wat die variant anders moet doen staat in
[foutpagina's](architecture/foutpaginas.md).

### De inhoud van de overige onderdelen beheerbaar maken

De [ervaring](architecture/modules/ervaring.md) en de
[kop](architecture/modules/kop.md) zijn af. De diensten en de werkwijze
staan nog in hun Vue-component; allebei zijn ze een module op zich, en de
stappen ernaartoe staan in
[pagina-indeling](architecture/pagina-indeling.md).

Zolang dat zo is, staat er op het indelingsscherm bij die onderdelen "Nog
niet te beheren".

Wie het bewerkvenster van de indeling met Escape sluit, verliest zijn
wijzigingen zonder melding. Bewust nog niet gebouwd: het venster is een
afgebakende handeling met een zichtbare knop "Annuleren", en een
bevestiging om een bevestiging heen maakt het niet veiliger. Zodra er meer
schermen met een bewerkvenster zijn en de inhoud er zwaarder in wordt, is
het de moeite waard om het één keer goed te doen.

### Een sitemap

Pas zinvol zodra er pagina's zijn, en die komen uit de modules.

## Nog te beslissen

### Back-ups

**Dit is een open vraag, geen taak.** Er is nu niets: geen pakket, geen
geplande taak, niets in de documentatie.

Zodra de klant inhoud invoert staat die alleen in de database. Gaat daar
iets mis, dan is het weg. Maar wat verstandig is hangt af van de hosting:
veel partijen maken zelf al dagelijkse snapshots, en daar nog een eigen
regeling naast zetten levert twee halve oplossingen op die allebei niet
worden gecontroleerd.

Drie richtingen, als het zover is:

1. **De hosting doet het.** Dan hoeven wij niets te bouwen, maar moeten we
   wél vastleggen hoe vaak, hoe lang ze blijven staan en hoe je terugzet --
   en dat één keer geprobeerd hebben.
2. **Zelf, met `spatie/laravel-backup`.** Database en uploads naar een
   externe opslag, met een melding als het misgaat.
3. **Allebei**, waarbij de onze de "ik heb per ongeluk alles verwijderd"-kant
   dekt en die van de hosting de "de server is weg"-kant.

Wat je ook kiest: een back-up die nooit is teruggezet is geen back-up.

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
