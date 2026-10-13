# Spam- en botbescherming

Openbare formulieren hebben drie lagen bescherming, in deze volgorde. De
volgorde is niet willekeurig: de goedkoopste controle staat vooraan, zodat een
bot die er toch niet doorheen komt zo min mogelijk kost.

```
rate limiting  →  honeypot  →  Turnstile (in de validatie)  →  verwerking
```

## 1. Rate limiting

Het goedkoopst: er is geen netwerkverkeer en nauwelijks rekentijd voor nodig.

Het contactformulier staat op `throttle:contact`: drie inzendingen per minuut
en twintig per dag, per IP-adres. Zie `AppServiceProvider::configureRateLimiting()`.

Wordt de grens geraakt, dan komt er een regel `throttle.limited` in het
[beveiligingslogboek](logging.md).

## 2. Honeypot

Via [spatie/laravel-honeypot](https://github.com/spatie/laravel-honeypot).
Twee onzichtbare velden in het formulier:

- een leeg tekstveld dat een mens nooit ziet maar een bot wel invult,
- een versleutelde tijdstempel, waarmee inzendingen worden geweigerd die
  onmenselijk snel binnenkomen (standaard binnen twee seconden).

De veldnamen worden per request willekeurig gemaakt en komen via de gedeelde
Inertia-props binnen. Het component is
[`HoneypotFields.vue`](../../resources/js/components/HoneypotFields.vue).

Twee dingen om niet te verprutsen bij het maken van een nieuw formulier:

- Verberg de velden met CSS-positionering, niet met `type="hidden"`. Een
  verborgen invoerveld is juist het signaal waar een slimme bot op filtert.
- Zet `aria-hidden="true"` en `tabindex="-1"`, zodat schermlezers en
  toetsenbordgebruikers er niet in belanden.

### Wat er gebeurt bij een treffer

De standaardresponder van het package geeft een lege pagina terug. Dat werkt
slecht in een Inertia-app en vertelt de bot bovendien dat hij betrapt is.
Daarom gebruiken we
[`LogSpamResponder`](../../app/Support/Security/LogSpamResponder.php): die legt
de poging vast als `spam.blocked` en doet net alsof het gelukt is. De bot
verspilt zijn tijd, wij zien het terug in het beveiligde gedeelte.

> **De prijs van die stilte: een valse treffer is onzichtbaar.** Vult een
> mens het formulier binnen twee seconden -- met een wachtwoordmanager of
> autofill -- dan krijgt hij "Aanvraag gelukt" te zien terwijl er niets is
> opgeslagen en niets is verstuurd. Dat is geen fout maar het hele punt:
> zou hij een andere melding krijgen dan een bot, dan weet de bot waar hij
> op moet letten. De tweede helft van de afweging staat in
> `security_events`: zo'n inzending staat er mét IP als `spam.blocked`, dus
> je kunt achteraf wél zien dát het gebeurde. Verhoog de drempel niet om
> dit te "verhelpen" -- dan laat je juist de snelle bots door.

## 3. Cloudflare Turnstile

De echte botcheck, en de enige die iets kost (een aanroep naar Cloudflare).
Daarom staat hij achteraan.

- Frontend: [`TurnstileWidget.vue`](../../resources/js/components/TurnstileWidget.vue).
  Het component laadt het script van Cloudflare pas als er een site key is
  ingesteld, en ruimt zichzelf op bij een Inertia-navigatie.
- Backend: [`Turnstile`](../../app/Support/Security/Turnstile.php) en de
  validatieregel [`TurnstileRule`](../../app/Rules/TurnstileRule.php).

Voeg hem toe aan een formulier met:

```php
'cf-turnstile-response' => TurnstileRule::veld(),
```

De widget voegt zelf een verborgen veld met die naam toe aan het formulier.

> **Schrijf die regels nooit zelf uit.** Hier stond eerst
> `['nullable', 'string', new TurnstileRule]` als voorbeeld om na te typen,
> en dat voorbeeld was fout -- zie _Het gat dat `nullable` maakte_
> hieronder. `veld()` bestaat zodat die keuze op één plek staat en er geen
> snippet rondgaat die je verkeerd kunt overnemen.

### Het gat dat `nullable` maakte

**Een maandenlang bestaand gat, en het zag er in elke test goed uit.**

Laravel slaat een validatieregel die niet "impliciet" is over zodra het veld
afwezig of leeg is. Dat staat in `presentOrRuleIsImplicit()` in de Validator:
bij een lege string of een ontbrekend veld loopt alleen een impliciete regel,
en een eigen `ValidationRule` is dat niet.

Gevolg: met `nullable` was de hele botcheck te omzeilen door het token
**simpelweg niet mee te sturen**. Gemeten, met een secret ingesteld:

| Wat er werd verstuurd         | Uitkomst                                   |
| ----------------------------- | ------------------------------------------ |
| geen `cf-turnstile-response`  | 302, géén foutmelding, aanvraag opgeslagen |
| leeg `cf-turnstile-response`  | 302, géén foutmelding, aanvraag opgeslagen |
| `cf-turnstile-response=onzin` | geweigerd, 0 aanvragen                     |

Dat laatste geval is waarom het niet opviel: een _onjuist_ token werd netjes
geweigerd en gelogd, dus alles wat je zou testen werkte.

`ImplicitRule` implementeren zou het ook oplossen -- die interface wint zelfs
van `nullable` -- maar hij is in deze Laravel-versie `@deprecated` en erft van
het oude `Rule`-contract, dus dan moeten `passes()` en `message()` terug. Daarom
is het `required` geworden, met één uitzondering die `veld()` zelf afhandelt:
staat Turnstile uit (lokaal zonder secret), dan mag het veld weg zijn.

### Fail closed

Drie gevallen waarin er bewust niets doorheen komt:

- **Geen secret ingesteld, buiten local en testing.** Een productieformulier
  zonder botcheck is erger dan een kapot formulier.
- **Cloudflare niet bereikbaar** (timeout, netwerkfout, foutcode). Anders is
  de bescherming te omzeilen door Cloudflare plat te leggen.
- **Geen token meegestuurd.** Dat is het geval hierboven. Het gebeurt ook
  zonder kwade bedoelingen: laadt het script van Cloudflare niet, dan rendert
  de widget niets en is er geen veld. De bezoeker krijgt dan "De verificatie
  kon niet worden geladen. Ververs de pagina en probeer het opnieuw."

> Dat derde geval betekent dat een storing bij Cloudflare het formulier
> onbruikbaar maakt. Dat is de keuze, maar daarom staat het **e-mailadres
> onder het formulier**: zie `ContactFormulier.vue`, prop `email`. Een
> bezoeker mag nooit met lege handen staan omdat een derde partij eruit ligt.

In `local` en `testing` zonder secret wordt de check **overgeslagen**, zodat je
zonder Cloudflare-account kunt ontwikkelen. Dat is iets anders dan "geslaagd":
`TurnstileResult` houdt `checked` en `allowed` apart, zodat je in logs en tests
ziet of er echt iets is gecontroleerd.

### Instellen

1. Maak een Turnstile-widget aan in het Cloudflare-dashboard.
2. Zet `TURNSTILE_SITE_KEY` en `TURNSTILE_SECRET_KEY` in `.env`.

De site key is publiek en gaat via Inertia naar de browser. De secret key
blijft op de server.

### De reset na een inzending

**Een token werkt één keer.** Verstuurde iemand twee berichten zonder de
pagina te verversen, dan faalde de tweede op de verificatie -- met een
melding over een controle waar de bezoeker niets aan kon doen, en die hij
alleen kon oplossen door de pagina te verversen zonder dat iemand hem dat
vertelde.

`TurnstileWidget` had daar altijd een `reset()` voor; die werd alleen nooit
aangeroepen. Zie
[`ContactFormulier.vue`](../../resources/js/components/site/ContactFormulier.vue).

**Hij hangt aan `@error` en niet alleen aan `@success`**, en dat is de hele
werking. Bij succes neemt het bevestigingsvak de plek van het formulier in, dus
de widget wordt opgeruimd -- resetten van iets dat verdwijnt helpt niemand. Het
geval dat hem écht nodig heeft is een **mislukte validatie**: het token is dan
al verbruikt bij Cloudflare, het formulier blijft staan, en de volgende poging
struikelt over de verificatie terwijl de bezoeker alleen een te kort bericht
had.

`@success` blijft er wel aan hangen: het kost niets, en zodra er ooit een "stuur
nog een bericht"-knop bij komt is hij daar nodig.

> Dit is het soort fout dat je pas ziet als iemand twee keer schrijft, en
> daarom stond hij er maanden in. De eerste reparatie zat op de verkeerde
> gebeurtenis, en dat viel om dezelfde reden niet op: lokaal zonder secret
> wordt Turnstile overgeslagen, dus er valt niets te verbruiken.

## Een uitgezet veld is geen ingang

Sinds [de module Contact](../architecture/modules/contact.md) bepaalt de
eigenaar welke velden op het formulier staan. Dat mag nooit betekenen dat een
bezoeker iets kan meesturen wat niet gevraagd is.

Een veld dat uitstaat krijgt **`exclude`** in de validatieregels, en niet
"geen regel": dan haalt de validator de waarde weg en kan geen enkele latere
aanroep er nog bij. Zonder regel blijft hij weliswaar buiten `safe()`, maar
dan hangt het ervan af wie `safe()` gebruikt en wie `all()`.

Bewust géén `prohibited`. Dat zou een bezoeker wiens formulier tien minuten
openstond een foutmelding geven over een veld dat hij netjes heeft ingevuld
terwijl de eigenaar het ondertussen uitzette.

En drie velden staan vast -- naam, e-mailadres en bericht. Dat is afgedwongen
in `ContactVeld::vast()` en dus in de code, niet in de database: een rij die
met de hand wordt omgezet, wordt genegeerd in plaats van dat het formulier
opengaat. `ContactFieldTest` houdt dat op drie manieren vast.

## En verder

De basisbescherming die altijd aan staat en die je niet moet uitzetten:

- **CSRF** op alle browserroutes. Alleen webhooks zijn uitgezonderd, en die
  hebben een handtekeningcontrole in de plaats.
- **Server-side validatie** in FormRequests. De frontend mag meedenken, maar
  niets in de validatie is optioneel omdat de browser het al zou hebben
  gecontroleerd.
- **Versleutelde sessiecookies** (`SESSION_ENCRYPT=true`).
