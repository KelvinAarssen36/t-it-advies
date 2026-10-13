# Het inlogadres wijzigen

Dit portaal heeft **één account**. Registratie staat uit, er is geen tweede
beheerder, en het e-mailadres op dat account is tegelijk de inlognaam en de
plek waar elke herstelmail heen gaat.

Dat maakt dit de gevoeligste handeling van de hele applicatie. Niet omdat
een aanvaller er iets mee wint -- hij heeft wachtwoord én authenticator
nodig -- maar omdat de eigenaar zichzelf eruit kan werken met één typefout.

Hiervoor stond het e-mailadres gewoon als invoerveld in het
profielformulier. Opslaan, uitloggen, en er was niemand meer die binnenkwam.

## De vorm in het kort

|            |                                                                                    |
| ---------- | ---------------------------------------------------------------------------------- |
| Tabel      | `email_changes`                                                                    |
| Model      | `App\Models\EmailChange`                                                           |
| Controller | `App\Http\Controllers\Settings\EmailChangeController`                              |
| Scherm     | `settings/Profile.vue` + `components/settings/InlogadresDialoog.vue`               |
| Mails      | `InlogadresBevestigenMail`, `InlogadresAangevraagdMail`, `InlogadresGewijzigdMail` |
| Tests      | `tests/Feature/Settings/EmailChangeTest.php`                                       |

## De drie stappen

### 1. Aanvragen

Route: `POST settings/inlogadres` (`inlogadres.store`), achter
`2fa.confirm` en `throttle:inlogadres`.

Er gaan drie sloten voor:

1. **Een verse authenticator-code.** Dezelfde middleware als bij het
   verwijderen van gegevens; zie [Gevoelige acties](gevoelige-acties.md).
   Een gestolen sessie komt hier niet langs.
2. **Het huidige wachtwoord**, in `EmailChangeRequest`. Dat staat er
   náást de code, want een code bewijst dat je de telefoon hebt, niet dat
   jíj achter dit scherm zit. Een open sessie op een onbeheerde laptop
   komt langs de code als die vijftien minuten geleden is ingetypt.
3. **Het nieuwe adres moet bestaan**, en dat bewijst het zelf in stap 2.

De aanvraag **verandert niets aan het account**. Er komt een rij in
`email_changes` met twee verse tokens, en er gaan twee mails uit:

- naar het **nieuwe** adres: de bevestigingslink, een uur geldig;
- naar het **oude** adres: een waarschuwing met een knop om het af te
  breken, veertien dagen geldig.

Een openstaande aanvraag vervalt zodra er een nieuwe komt. Twee geldige
bevestigingslinks tegelijk zou betekenen dat wie het eerst klikt bepaalt
waar de post heen gaat.

### 2. Bevestigen

Route: `GET inlogadres/bevestigen/{token}` (`inlogadres.bevestigen`),
**zonder inloggen**, achter `throttle:inlogadres-link`.

Hier wisselt het adres op `users`, en wordt `email_verified_at` meteen
gezet: de link bewijst dat dit postvak bestaat, dus een tweede
verificatiemail voor hetzelfde postvak zou onzin zijn.

Daarna gaat er nog een mail naar het **oude** adres: het hoort te weten dat
het zijn account kwijt is, en hoe het dat ongedaan maakt.

Dat deze route zonder inloggen werkt is met opzet. De link bewijst precies
wat hij moet bewijzen -- dat dat postvak bereikbaar is. Wie de aanvrager
is, is in stap 1 al bewezen met wachtwoord én code, en het token is
eenmalig.

### 3. Terugdraaien

Route: `GET inlogadres/terugdraaien/{token}` (`inlogadres.terugdraaien`),
ook zonder inloggen, ook begrensd.

**Eén link voor twee gevallen.** Vóór de bevestiging breekt hij de
aanvraag af; erna zet hij het oude adres terug. Dat is geen gemakzucht:
wie hem opent bedoelt hetzelfde -- "nee, dit wilde ik niet" -- en of de
wijziging op dat moment al is doorgevoerd weet hij niet. Twee verschillende
links zouden hem dwingen dat zelf uit te zoeken, op precies het moment
waarop hij in paniek is.

Geen inlogscherm ervoor, want dit is het vangnet voor de situatie waarin
je juist niet meer binnenkomt. Een herstellink achter een inlogscherm is
geen herstellink.

**De termijn gaat pas lopen bij de bevestiging.** Veertien dagen vanaf het
moment dat het adres echt wisselde, en niet vanaf de aanvraag -- merk je
het pas na een week omdat je zelden inlogt, dan moet die link het nog doen.

## De tokens

Allebei `Str::random(48)`, **gehasht bewaard** met SHA-256, zoals een
wachtwoord. Het platte token bestaat op één plek: in de mail. Wie de
database leest kan er dus niets mee, en er belandt ook nooit een token in
een logregel.

Geen zout en geen bcrypt: de invoer is al 48 willekeurige tekens, dus er
valt niets te raden en een trage hash levert hier niets op.

`EmailChangeTest` toetst dat allebei de tokens niet in platte vorm in de
database staan, en dat ze niet in `security_events` terechtkomen.

## Wat er in het logboek komt

Drie soorten in `SecurityEventType`:

| Soort                         | Wanneer                                     |
| ----------------------------- | ------------------------------------------- |
| `auth.email_change_requested` | de aanvraag is gedaan                       |
| `auth.email_change_confirmed` | het adres is gewisseld (of een link faalde) |
| `auth.email_change_reverted`  | het is afgebroken of teruggedraaid          |

Alle drie apart, want bij een vraag achteraf wil je weten wáár het misging.
Het adres mag erbij: dit is het account van de eigenaar en niet van een
bezoeker. Het token niet.

## De begrenzing

| Begrenzer         | Ruimte                  | Waarom                                                                                                                 |
| ----------------- | ----------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `inlogadres`      | 3 per uur per gebruiker | elke poging stuurt twee mails, waaronder een naar het oude postvak -- precies het postvak dat je wil kunnen vertrouwen |
| `inlogadres-link` | 10 per minuut per IP    | iemand klikt een link weleens twee keer, maar tokens raden mag niet                                                    |

## Het scherm

Op `settings/Profile` staat het adres als **tekst in een vak**, niet in een
invoerveld. Een invoerveld nodigt uit om erin te typen.

De knop _Wijzigen_ gaat eerst langs `GET settings/inlogadres`
(`inlogadres.create`). Die route doet zelf niets en stuurt meteen terug
naar het profiel, maar hij staat achter `2fa.confirm`. Daardoor wordt de
authenticator gevraagd **voordat** de eigenaar iets intypt. Zonder die
omweg zou de middleware de code pas bij het versturen vragen, en komt hij
na het invoeren ervan terug op een leeg formulier -- precies het moment
waarop iemand denkt dat er iets stuk is.

Loopt er een aanvraag, dan staat dat onder het adres, met het adres waar de
bevestiging ligt en tot wanneer. Zonder dat vak lijkt er niets te gebeuren:
het adres erboven staat immers nog op het oude, en dat is de bedoeling.

## Bewust niet gedaan

- **Geen opruimtaak op `email_changes`.** Er is één account en drie
  aanvragen per uur; de tabel groeit niet. Een rij waarvan de termijn om
  is doet niets meer, en de tokens staan er gehasht in.
- **Andere sessies worden niet uitgelogd** bij een wijziging of een
  terugdraaiing. Dat is een bewuste grens en geen volledigheid: wie dit
  kan aanvragen heeft het wachtwoord én de authenticator al, dus een open
  sessie elders is niet het gat waar deze stroom over gaat. De mails
  raden aan het wachtwoord te wijzigen.

    > **Let op**, want dit is een echte beperking: het wachtwoord wijzigen
    > logt in deze applicatie bestaande sessies _niet_ uit. Er staat geen
    > `AuthenticateSession`-middleware en `logoutOtherDevices()` wordt
    > nergens aangeroepen. Wil je dat erbij, dan is dat een eigen wijziging
    > op het scherm Beveiliging -- niet hier.

- **Geen tweede herstelmail na de bevestiging.** Het hersteltoken staat
  niet meer in platte vorm in de database, dus die zou een nieuw token
  nodig hebben. Eén link die blijft werken is robuuster dan twee die elkaar
  opvolgen -- zie `InlogadresGewijzigdMail`.
- **Het adres wordt niet gecontroleerd op bereikbaarheid vóór de
  aanvraag.** Dat ís stap 2.

## Zie ook

- [Gevoelige acties](gevoelige-acties.md) -- de middleware `2fa.confirm`.
- [Authenticatie en 2FA](authenticatie-en-2fa.md).
- [Logging](logging.md) -- wat er nooit in een logregel mag.
- [Mail en queues](../architecture/mail-en-queues.md) -- waarom deze drie
  mails niet in de wachtrij gaan.
