# Foutpagina's

Er komen er twee, en het verschil is wie ernaar kijkt.

| Kant        | Wie het ziet                                | Status                       |
| ----------- | ------------------------------------------- | ---------------------------- |
| **Portaal** | Onze klant, ingelogd, midden in zijn werk   | Klaar                        |
| **Landing** | De klanten van onze klant, meestal een gast | Nog niet gemaakt, komt later |

Dat onderscheid is geen luxe. Iemand die in het portaal op een dode link
klikt is ergens middenin en wil verder; een bezoeker op de landing weet niet
eens dat er een beheergedeelte bestaat en hoort daar ook niets van te merken.

## De foutpagina van het portaal

[`pages/Error.vue`](../../resources/js/pages/Error.vue). Er lopen **twee
wegen** naartoe, en dat is geen slordigheid maar noodzaak.

### Waarom twee wegen

Een 404 op een adres dat op **geen enkele route** past, wordt door de router
gegooid **voordat** de middlewaregroep `web` heeft gedraaid. Er is op dat
moment geen sessie, dus ook geen ingelogde gebruiker en geen gedeelde
Inertia-props.

Dat is precies de val waar dit eerst in liep. De foutpagina werd vanuit de
`respond`-callback gerenderd, die callback keek naar `$request->user()`, en
die was altijd leeg -- ook als je gewoon was ingelogd. Resultaat: een
ingelogde beheerder kreeg de kale standaardpagina van Laravel te zien. In de
test viel dat niet op, want `actingAs()` zet de gebruiker rechtstreeks op
het verzoek en heeft die sessie helemaal niet nodig. Een groene test bij een
kapot scherm.

Daarom:

| Situatie                                      | Weg                                                                                                                   |
| --------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| 404 op een onbekend adres                     | [`FallbackController`](../../app/Http/Controllers/FallbackController.php) via `Route::fallback()` in `routes/web.php` |
| 403, 429, 500, 503 vanuit een bestaande route | de `respond`-callback in [`bootstrap/app.php`](../../bootstrap/app.php)                                               |

Een fallback-route stáát in de groep `web`, dus daar zijn sessie, gebruiker
en gedeelde props gewoon beschikbaar. De andere codes worden binnen een
route gegooid, dus daar speelt het probleem niet.

[`ErrorPageTest`](../../tests/Feature/ErrorPageTest.php) controleert bij
allebei de wegen op `auth.user` in de props. Dat is de enige manier om van
buitenaf te zien dát die middleware heeft gedraaid -- en dus dat de zijbalk
er straks omheen staat.

### Hoe hij eruitziet

**Hij staat in de schil van het portaal**, dus met de zijbalk en het
accountmenu eromheen. Dat is het hele idee: een fout is geen reden om iemand
uit zijn omgeving te gooien. Je ziet waar je bent, je kunt gewoon
doorklikken, en het portaal blijft het portaal. Een foutpagina die het hele
venster overneemt voelt alsof je bent uitgelogd.

**De tekst noemt geen techniek.** "419" zegt niemand iets. Per code staat er
een zin die uitlegt wat er aan de hand is en wat je nu kunt doen. En geen
verontschuldigingen bij een 404: er is meestal niets misgegaan, je bent
gewoon ergens waar niets staat. Bij een 500 wél, want dan ligt het aan ons.

**De kleuren komen uit de rol-tokens**, dus de pagina klopt in de huisstijl
én in licht. Zie [huisstijl en kleuren](huisstijl-en-kleuren.md).

Er is **altijd een weg terug**, in een eigen vlak onderaan: "naar het
dashboard" en "terug naar de vorige pagina". Die tweede knop valt terug op
het dashboard als er geen geschiedenis is, bijvoorbeeld wanneer je de link
in een nieuw tabblad opent. Het vlak zegt er met zoveel woorden bij dat je
niet bent uitgelogd, want dat is wat een foutpagina onbedoeld suggereert.

### Welke codes, en welke niet

Eigen pagina: **403, 404, 429, 500, 503**.

Twee uitzonderingen, allebei met reden:

- **419 niet.** Dat is een verlopen CSRF-token, en Inertia vangt die zelf af
  met een verzoek om de pagina te herladen. Daar een eigen scherm overheen
  leggen maakt het alleen maar verwarrender.
- **500 niet als `APP_DEBUG` aanstaat.** Dan blijft de foutpagina van
  Laravel staan, want daar staat in wát er misging. Een nette "er ging iets
  mis" is precies wat je tijdens het ontwikkelen niet wilt zien.

### Alleen voor wie is ingelogd

Allebei de wegen kijken naar `$request->user()`. Is die er niet, dan gaat
het antwoord ongewijzigd door en krijg je de standaardpagina van Laravel.

Dat is bewust en tijdelijk: zo kan de landing later zijn eigen variant
krijgen zonder dat er iets aan het portaal verandert. Een gast die een
portaal-URL raadt komt sowieso niet zo ver -- die wordt door de middleware
naar het inlogscherm gestuurd.

Verzoeken die JSON verwachten gaan ook ongewijzigd door; zie
`shouldRenderJsonWhen` in hetzelfde bestand.

### De statuscode blijft staan

`Inertia::render(...)->toResponse($request)->setStatusCode($status)`. Zonder
die laatste stap krijg je een nette pagina met een 200 erachter, en dan is
het voor een zoekmachine en voor een monitor een bestaande pagina.
[`ErrorPageTest`](../../tests/Feature/ErrorPageTest.php) bewaakt dat.

## Wat er nog moet gebeuren

De variant voor de landing. Die hoort er duidelijk anders uit te zien dan de
portaalversie: in de schil van de publieke site, altijd in de huisstijl, met
een weg terug naar de homepage in plaats van naar het dashboard, en zonder
één woord over een beheergedeelte.

Als die er komt, verdwijnt de `$request->user() === null`-controle op
allebei de plekken en komt er een keuze tussen twee varianten voor in de
plaats. Let er dan op dat een 503 (onderhoud) geen sessie heeft: die valt
dan vanzelf naar de landingversie, en dat is goed, want tijdens onderhoud is
er ook geen portaal om naar terug te keren.
