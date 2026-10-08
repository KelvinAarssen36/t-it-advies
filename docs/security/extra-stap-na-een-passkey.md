# De extra stap na een passkey

Een passkey is één handeling: je vinger of je gezicht, en je bent binnen.
Bij een wachtwoordlogin komt daarna altijd nog de authenticator; bij een
passkey niet, want de passkey ís al twee factoren -- het apparaat dat je
hebt plus de ontgrendeling ervan.

Dat klopt, en toch is het niet voor iedereen genoeg. Wie zijn laptop
ontgrendeld laat staan, heeft met een passkey niets meer tussen een
voorbijganger en het portaal. Daarom kan de eigenaar **zelf** kiezen om er
een authenticator-code achteraan te zetten.

**Standaard staat hij uit.** Een slot dat je niet hebt gekozen is geen
beveiliging maar een verrassing.

## De vorm in het kort

|           |                                                                  |
| --------- | ---------------------------------------------------------------- |
| Kolom     | `users.passkey_requires_two_factor` (boolean, standaard `false`) |
| Lezen     | `User::vraagtExtraCodeNaEenPasskey()`                            |
| Omzetten  | `App\Http\Controllers\Settings\PasskeyStepController`            |
| Afdwingen | `App\Http\Responses\PasskeyLoginResponse`                        |
| Scherm    | `components/PasskeyExtraStap.vue` op `settings/Security`         |
| Tests     | `tests/Feature/Security/PasskeyExtraStapTest.php`                |

## Hoe het wordt afgedwongen

Dit is het deel waar de keuze zit, dus lees het voordat je eraan sleutelt.

`Laravel\Passkeys\Http\Controllers\PasskeyLoginController::store()` logt de
gebruiker **zelf** in en geeft daarna het antwoord terug dat in de
container staat. Dat inloggen zit in het pakket; daar komen we niet
tussen. Wat we wél kunnen, is dat antwoord vervangen -- en dat doen we.

Onze `PasskeyLoginResponse`:

1. kijkt of de eigenaar de extra stap aan heeft staan;
2. logt hem meteen weer uit met `logoutCurrentDevice()`;
3. zet `login.id` en `login.remember` in de sessie;
4. stuurt hem naar `two-factor.login` -- de challenge van Fortify.

Daarmee belandt hij in **precies dezelfde toestand** als iemand die met
een wachtwoord inlogt en 2FA aan heeft staan. Het scherm, de recovery
codes, de begrenzing en de "waar wilde je ook alweer heen"-omleiding zijn
allemaal die van Fortify. Wij voegen niets toe behalve de afslag ernaartoe.

### Waarom niet gewoon middleware

De kortste weg zou zijn: laat hem ingelogd en hou met een middleware elk
scherm dicht tot de code klopt. Dat is niet gedaan, en met opzet.

Dan bestaat er namelijk een toestand waarin iemand **ingelogd is maar nog
niet bevestigd**, en dan is elke route die je vergeet aan die middleware
te hangen een gat. Dit project heeft vier routegroepen met
`two-factor.required` erop; een vijfde vergeten is een kwestie van tijd.

In de oplossing hierboven bestaat die toestand niet. `Auth::check()` is
onwaar tot de code klopt, en dat is wat `PasskeyExtraStapTest` ook toetst
-- niet "hij ziet een scherm", maar "hij is niet ingelogd".

### `logoutCurrentDevice()` en niet `logout()`

`logout()` ververst het remember-token, en dat logt de eigenaar uit op
**andere** apparaten. Een geslaagde passkey op je laptop zou je dan van je
telefoon gooien.

`logoutCurrentDevice()` doet dat niet, maar wist wel de recaller-cookie van
dít apparaat -- precies de combinatie die we hier nodig hebben. Zou die
cookie blijven staan, dan zou de gebruiker bij het volgende verzoek
gewoon weer ingelogd zijn en was de hele stap voor niets.

## De schakelaar

Route: `PUT settings/security/passkey-stap` (`security.passkey-stap`),
achter `throttle:sensitive-action`.

**De authenticator-code zit in het verzoek**, niet in de middleware
`2fa.confirm`. Dat is een weloverwogen afwijking van de rest van het
portaal, en hij komt uit een fout:

> Eerst stond `2fa.confirm` wél op deze route. Die middleware kan een PUT
> niet onthouden -- hij bewaart alleen de pagina waar je vandaan kwam en
> gooit het verzoek weg. Je zette het schuifje om, vulde je code in, kwam
> terug, en het stond weer zoals het stond. Dan moest je het nog een keer
> omzetten. En alleen als je vorige bevestiging al verlopen was, dus het
> gebeurde "soms" -- het soort fout dat je alleen in de browser ziet.

De controle zelf is **niet opnieuw geschreven**. Die staat in
`App\Support\Security\Authenticator`, en het codescherm van `2fa.confirm`
gebruikt precies dezelfde klasse: dezelfde regel over recovery codes,
dezelfde logregels, en een geslaagde code zet daar net zo goed de
bevestiging in de sessie. Zie
[gevoelige acties](gevoelige-acties.md#de-middleware-werkt-niet-overal).

**Allebei de kanten op achter die code**, en de belangrijkste is
_uitzetten_. Zou alleen aanzetten een code vragen, dan kan wie achter een
open scherm gaat zitten de extra stap er gewoon af halen en daarna rustig
met de passkey naar binnen.

Op het scherm krijgt uitzetten de **rode** knop en aanzetten de blauwe;
zie `drie-handelingskleuren` in
[formulieren en schuifbalken](../architecture/formulieren-en-schuifbalken.md).
Dat is geen willekeur: uitzetten haalt een slot weg, en dat hoort net zo
te voelen als iets weggooien. Bij uitzetten staat er bovendien een
waarschuwing in het venster; bij aanzetten niet, want daar valt niets te
verliezen.

**Het schuifje beweegt pas als de server het heeft omgezet.** Het is
gebonden aan de prop en niet aan een eigen waarde: zou het meteen
meespringen, dan toont het even iets wat nog niet waar is, en bij een
verkeerde code of een geannuleerd venster moet het weer terug. Een
schuifje dat terugspringt leest als een storing -- precies wat hierboven
misging.

### Eén code werkt één keer

Fortify weigert een TOTP-code die al is gebruikt, en dat klopt: zo'n code
is eenmalig. Zet je de schakelaar om en meteen daarna weer terug, dan moet
je dus de volgende code van je app gebruiken. In de praktijk kijk je toch
op je telefoon, waar er dan al een nieuwe staat.

De foutmelding is in dat geval wel misleidend -- hij zegt "Deze code klopt
niet". Fortify geeft bij een hergebruikte en een verkeerde code hetzelfde
antwoord, dus we kunnen dat verschil hier niet benoemen. Dat geldt overal
in dit portaal waar om een code wordt gevraagd, niet alleen hier.

## De twee voorwaarden

`vraagtExtraCodeNaEenPasskey()` kijkt naar twee dingen:

```php
return $this->passkey_requires_two_factor === true
    && $this->two_factor_confirmed_at !== null;
```

Die tweede is het punt. Zonder bevestigde authenticator is er geen code om
te vragen, en zou de eigenaar voor een challenge-scherm staan waar hij
niets kan invullen -- buitengesloten door een beveiliging die hij zelf
aanzette. Nu wordt de schakelaar in dat geval stil genegeerd.

Het schuifje op het scherm leest daarentegen de **ruwe kolom**, niet deze
methode. Het hoort te laten zien wat de eigenaar heeft gekozen; zou het
de methode lezen, dan springt het uit zichzelf terug zodra 2FA er even
niet is, en dan lijkt het stuk.

## Wat er in het logboek komt

| Soort                          | Wanneer                                                 |
| ------------------------------ | ------------------------------------------------------- |
| `auth.passkey_step_changed`    | de schakelaar ging aan of uit (met `aan` in de context) |
| `auth.passkey_step_challenged` | de stap werd daadwerkelijk gevraagd na een passkey      |

Het omzetten is de regel die je achteraf zoekt: "sinds wanneer staat dit
uit, en wie heeft dat gedaan".

## Het challenge-scherm

`auth/TwoFactorChallenge.vue` krijgt een prop `viaPasskey`, uit
`login.via_passkey` in de sessie. Staat die aan, dan staat er boven het
codeveld waarom hij er is.

Zonder die regel staat de eigenaar voor een codeveld terwijl hij net dacht
klaar te zijn, en leest dat als een mislukte passkey. De prop komt uit
`session()->get()` en niet `pull()`: ververst hij de pagina of typt hij
zich een keer mis, dan hoort de uitleg er nog steeds te staan. Fortify
ruimt `login.*` zelf op zodra de code klopt.

## Het onthaal na een passkey

Het antwoord van het pakket stuurt je naar `passkeys.redirect`, en dat
staat standaard op `/` -- de publieke website. Verder zet het niets in de
sessie. Daardoor miste een passkey-login drie dingen die bij elke andere
login wél gebeuren:

|                           | Wachtwoord | Passkey (voor de reparatie)         |
| ------------------------- | ---------- | ----------------------------------- |
| Begroeting "Welkom terug" | ja         | **nee**                             |
| Laadscherm `portal.enter` | ja         | **nee**                             |
| Onthouden bestemming      | ja         | alleen via `intended()`, anders `/` |

`PasskeyLoginResponse::naarHetLaadscherm()` doet nu hetzelfde als de
`LoginResponse` van Fortify: `portal.welcome` in de sessie en door naar
het laadscherm. **`url.intended` blijft met rust**, want het laadscherm
leest hem zelf -- `redirect()->intended()` zou hem opmaken en je op het
dashboard afleveren in plaats van waar je heen wilde.

### Eén ding gaat met opzet níet mee

`auth.password_confirmed_at` wordt na een passkey **niet** gezet, ook niet
in de stroom met de extra stap. Een passkey bewijst dat je het apparaat
hebt, niet dat je het wachtwoord kent -- en dat laatste is precies wat
`RequirePassword` op het scherm Beveiliging wil weten. Log je met een
passkey in en ga je daarheen, dan vraagt het portaal dus alsnog je
wachtwoord. Dat is geen vergeten geval maar de bedoeling.

Zonder die uitzondering zou het bovendien averechts werken: de stroom met
de extra stap eindigt in de challenge van Fortify, en daar wordt de
bevestiging normaal wél gezet. De strengere instelling gaf dan het
zwakkere resultaat.

### Twee inlogregels in het logboek

Met de extra stap aan staan er voor één login **twee** regels
`auth.login` in het beveiligingslogboek, vlak na elkaar. Dat klopt en is
niet te vermijden: het pakket logt in voordat wij aan de beurt zijn, wij
loggen weer uit, en Fortify logt na de code opnieuw in. Elke `login()`
stuurt een gebeurtenis die `RecordSecurityEvents` oppikt.

Lees je het logboek terug, dan hoort daar `auth.passkey_step_challenged`
tussen te staan; dat is het teken dat die twee bij elkaar horen.

## Bewust niet gedaan

- **Geen tweede schakelaar voor "ook bij een wachtwoord".** Daar komt de
  authenticator altijd al; dat is geen keuze en hoort het ook niet te
  worden.
- **Geen uitzondering voor "dit apparaat onthouden".** Dat zou de stap
  precies op het apparaat overslaan dat het vaakst onbeheerd blijft
  liggen.
- **Recovery codes worden hier wél geaccepteerd**, anders dan bij een
  gevoelige actie. Je bent hier aan het inloggen, en dit portaal heeft één
  account: een eigenaar die zijn telefoon kwijt is moet er nog in kunnen.
  Zie [gevoelige acties](gevoelige-acties.md) voor waarom dat daar
  andersom is.

## Zie ook

- [Authenticatie en 2FA](authenticatie-en-2fa.md)
- [Gevoelige acties](gevoelige-acties.md)
- [Het inlogadres wijzigen](inlogadres-wijzigen.md)
