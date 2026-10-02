# Het scherm Veiligheid

Onder **Instellingen → Veiligheid** staat in gewone taal hoe de website en
het portaal beschermd zijn, met een paar echte cijfers erbij.

> **Een scherm zonder knoppen, en dat is de bedoeling.** De eigenaar vroeg
> erom in die vorm: een pagina "die niet echt iets doet maar waarin gewoon
> simpel staat hoe het allemaal beveiligd is". Er valt hier niets in te
> stellen; het bestaat zodat hij weet waar hij aan toe is.

## De regel waar alles aan hangt

**Niets op dit scherm staat opgeschreven. Alles is gemeten of uit de
instellingen gelezen.**

Dat is bij dit onderwerp geen stijlkeuze. Een scherm dat zegt dat de
spamcontrole aanstaat terwijl de sleutel leeg is, is **erger dan geen
scherm** -- dan denkt de eigenaar beschermd te zijn en kijkt hij er nooit
meer naar. Staat iets uit, dan staat dat er met een kruisje én met wat eraan
te doen is.

`SafetyScreenTest` legt dat vast: de spamcontrole en de alarmering worden
allebei getest in de stand aan én uit.

## Wat erop staat

### De cijfers, over dertig dagen

| Cijfer                 | Waar het vandaan komt                             | Waarom het er staat                       |
| ---------------------- | ------------------------------------------------- | ----------------------------------------- |
| Mislukte inlogpogingen | `security_events`, mislukte login en mislukte 2FA | Een paar is normaal; een piek niet        |
| Tegengehouden pogingen | spam, rate limiting, lockouts                     | Dit is goed nieuws: wat er níet door kwam |
| Verstuurde mail        | `mail_logs`                                       | Doet de mail het                          |
| Mail die niet aankwam  | bounces en mislukte verzendingen                  | Dan is er iets om na te kijken            |

**Nul is hier een goed getal**, en dan staat er een andere zin: niet de
uitleg wat het cijfer betekent, maar "niemand heeft geprobeerd binnen te
komen". Een uitlegregel onder een nul leest als een waarschuwing die er geen
is.

### Wat er voor je geregeld is

Zeven punten in gewone taal: dat er één account is en nergens een
aanmeldformulier, de versleutelde verbinding met de beveiligingskoppen, de
lagen op het contactformulier, de mailverzending, dat er elk uur wordt
meegekeken, dat er niets is dat bezoekers volgt, en de bewaartermijnen van
de logboeken.

**Geen vinkjes en geen kruisjes, en dat is een correctie.** Eerst stond bij
elk punt de echte stand, met een vinkje of een kruis in een gekleurd
rondje. Technisch klopte dat, maar zo las het scherm als een keuring:
zeven, acht regels onder elkaar waarvan er een paar oranje staan, over
dingen waar de eigenaar niets aan kan doen of die alleen lokaal uitstaan.
Zijn woorden:

> eigenlijk wilde ik dat je dat gewoon simpel gebruikte, dus wat we
> allemaal aan veiligheid gebruiken, om de klant tot rust te stellen --
> niet per se een echte check

Het tekentje links is nu het **onderwerp** en geen oordeel: het pictogram
van waar het punt over gaat, in de accentkleur.

#### Rustig van toon is niet hetzelfde als onwaar

Dit scherm mag niet gaan beweren wat niet gebeurt. Een scherm dat zegt dat
de spamcontrole aanstaat terwijl de sleutel leeg is, is erger dan geen
scherm -- dan denkt de eigenaar beschermd te zijn.

Daarom komen er nog steeds vier waarden van de server, en die bepalen
**welke zin** erbij hoort in plaats van welk kleurtje. Staat de
Turnstile-sleutel niet ingevuld, dan gaat de tekst over twee lagen en niet
over drie. Dezelfde aanpak als op de
[privacyverklaring](../architecture/bezoekcijfers.md#de-privacyverklaring),
waar Cloudflare alleen wordt genoemd als hij er echt is.

`SafetyScreenTest::test_the_screen_is_not_a_checklist` legt de toon vast en
`test_both_versions_of_the_spam_text_exist` de eerlijkheid. Die eerste leest
het component, want de toon zit in het sjabloon en niet in een prop.

### Wat je zelf kunt aanzetten

Tweestapsverificatie en passkeys staan **apart** onderaan, met een rustig
woordje erbij of ze aanstaan.

Ze stonden eerst tussen de rest met een kruisje ervoor, en dat was de
verkeerde toon: tweestapsverificatie die uitstaat is geen defect maar een
knop die de eigenaar nog niet heeft ingedrukt. Staat het uit, dan vertelt de
tekst wat het je oplevert in plaats van wat je mist, en er staat een link
naar Beveiliging onder.

> **Passkeys werken echt.** `laravel/passkeys` zit erin, de Fortify-feature
> staat aan, alle acht routes draaien en het beheer staat op het scherm
> Beveiliging. Twee dingen om te weten: het werkt alleen op **https** (dus
> niet op een `.test`-adres), en de relying party komt uit `APP_URL`. Zie
> [deployment](../operations/deployment.md).

## Waarom het "Veiligheid" heet en niet "Beveiliging"

Er bestaat al een scherm **Beveiliging** in de instellingen, en dat gaat
over iets anders: daar stelt de eigenaar zijn wachtwoord, zijn
authenticator-app en zijn passkeys in. Dit scherm legt uit hoe het **geheel**
beschermd is en verwijst daarheen.

Twee regels in dezelfde lijst die allebei "Beveiliging" heten is
onbruikbaar, dus heeft het nieuwe scherm de bredere naam gekregen.

Het staat ook **niet** achter een wachtwoordbevestiging zoals Beveiliging.
Daar wijzig je iets dat je niet wilt terugdraaien; hier lees je alleen. Een
extra drempel voor lezen zou betekenen dat hij het nooit opent.

## Wat waar staat

| Bestand                                                                        | Wat het doet                                                   |
| ------------------------------------------------------------------------------ | -------------------------------------------------------------- |
| [`SafetyController`](../../app/Http/Controllers/Settings/SafetyController.php) | Verzamelt de cijfers en de standen.                            |
| [`Veiligheid.vue`](../../resources/js/pages/settings/Veiligheid.vue)           | Wat de eigenaar ziet, in gewone taal.                          |
| [`SafetyScreenTest`](../../tests/Feature/Settings/SafetyScreenTest.php)        | Dat er niets verzonnen wordt, en dat het scherm niets wijzigt. |

Zie ook [verzoeken van bezoekers](verzoeken-van-bezoekers.md) voor het
scherm waar een verzoek om inzage of verwijdering wordt afgehandeld, en
[logging](logging.md) voor wat er precies in de logboeken terechtkomt.
