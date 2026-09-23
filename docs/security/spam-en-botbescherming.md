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
'cf-turnstile-response' => ['nullable', 'string', new TurnstileRule],
```

De widget voegt zelf een verborgen veld met die naam toe aan het formulier.

### Fail closed

Twee gevallen waarin er bewust niets doorheen komt:

- **Geen secret ingesteld, buiten local en testing.** Een productieformulier
  zonder botcheck is erger dan een kapot formulier.
- **Cloudflare niet bereikbaar** (timeout, netwerkfout, foutcode). Anders is
  de bescherming te omzeilen door Cloudflare plat te leggen.

In `local` en `testing` zonder secret wordt de check **overgeslagen**, zodat je
zonder Cloudflare-account kunt ontwikkelen. Dat is iets anders dan "geslaagd":
`TurnstileResult` houdt `checked` en `allowed` apart, zodat je in logs en tests
ziet of er echt iets is gecontroleerd.

### Instellen

1. Maak een Turnstile-widget aan in het Cloudflare-dashboard.
2. Zet `TURNSTILE_SITE_KEY` en `TURNSTILE_SECRET_KEY` in `.env`.

De site key is publiek en gaat via Inertia naar de browser. De secret key
blijft op de server.

## En verder

De basisbescherming die altijd aan staat en die je niet moet uitzetten:

- **CSRF** op alle browserroutes. Alleen webhooks zijn uitgezonderd, en die
  hebben een handtekeningcontrole in de plaats.
- **Server-side validatie** in FormRequests. De frontend mag meedenken, maar
  niets in de validatie is optioneel omdat de browser het al zou hebben
  gecontroleerd.
- **Versleutelde sessiecookies** (`SESSION_ENCRYPT=true`).
