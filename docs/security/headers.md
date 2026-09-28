# Beveiligingskoppen

Beveiliging die je gratis van de browser krijgt, mits je erom vraagt.

Elke kop hieronder sluit een categorie aanvallen af die je anders in de
applicatie zelf zou moeten afvangen -- of, vaker, zou vergeten af te vangen.
Ze staan in
[`SecurityHeaders`](../../app/Http/Middleware/SecurityHeaders.php).

## Wat er staat, en waarom

| Kop                         | Waarde                            | Wat het tegenhoudt                                               |
| --------------------------- | --------------------------------- | ---------------------------------------------------------------- |
| `X-Frame-Options`           | `SAMEORIGIN`                      | Onze pagina in een onzichtbaar frame over andermans knoppen.     |
| `X-Content-Type-Options`    | `nosniff`                         | Een bestand dat de browser zelf als script gaat uitvoeren.       |
| `Referrer-Policy`           | `strict-origin-when-cross-origin` | Een pad met een token erin dat in de logs van een derde belandt. |
| `Permissions-Policy`        | camera, microfoon, locatie uit    | Een binnengeslopen script dat om de camera vraagt.               |
| `Strict-Transport-Security` | twee jaar, alleen op https        | Een bezoeker die op `http://` binnenkomt en afgeluisterd wordt.  |

Een paar keuzes die uitleg verdienen:

**`SAMEORIGIN` en niet `DENY`.** Onze eigen pagina's mogen elkaar insluiten,
mocht er ooit een voorbeeldweergave van de website in het portaal komen. Dat
is precies het soort ding dat we gaan bouwen.

**`Referrer-Policy` is niet cosmetisch.** Een wachtwoordherstel-link is
`/reset-password/<token>`. Klikt iemand vanaf die pagina op een externe
link, dan krijgt die site zonder deze kop het volledige adres -- inclusief
het token.

**HSTS staat uit op http en buiten productie.** Op http doet de browser er
toch niets mee. Maar zet je hem lokaal, dan onthoudt je browser twee jaar
lang dat `*.test` alleen via https mag -- en dan kom je er niet meer bij
zonder je browsergegevens te wissen. Dat is een middag zoeken, en
[`SecurityHeadersTest`](../../tests/Feature/Security/SecurityHeadersTest.php)
bewaakt het.

## Waarom de middleware globaal staat

Niet op de `web`-groep maar op de globale stapel. Deze koppen horen ook op
een JSON-antwoord, op een webhook en op een foutpagina te staan -- juist
daar, want dat zijn de antwoorden die buiten de gewone stroom vallen. Een
404 die buiten de middlewaregroep om wordt gegooid, zou ze anders missen.
Daar staat een test op.

## Wat er níet staat: de Content-Security-Policy

Bewust. Een CSP is de krachtigste van deze koppen en tegelijk de enige die
je site stukmaakt als je hem half instelt -- en dat merk je niet in de
tests, want die draaien zonder browser.

Wat er in deze applicatie van buiten geladen wordt, en dus in zo'n beleid
moet staan:

- **Turnstile** van Cloudflare, op het contactformulier: script en frame.
- **Bunny Fonts**, alleen tijdens de build -- de bestanden worden
  meegeleverd, dus in productie is er geen extern lettertype. Dit mag er dus
  níet in.
- **Vite** in ontwikkeling, dat van een eigen poort serveert met een
  websocket erbij. Een beleid dat dat niet toelaat breekt het herladen.
- **Inline stijl** in `app.blade.php`, dat de achtergrondkleur zet voordat
  de stylesheet er is. Dat heeft een nonce of een hash nodig.

Zolang dat niet is uitgezocht is een half beleid slechter dan geen beleid:
je denkt dat je beschermd bent en je hebt het vooral moeilijker gemaakt om
een echte fout te vinden. Het staat als open punt in
[wat er nog open staat](../openstaand.md).
