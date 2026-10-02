<?php

namespace App\Support\Juridisch;

use App\Models\SecurityEvent;
use App\Support\Datum;
use App\Support\Zoekterm;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Waar in deze applicatie gegevens van één persoon kunnen staan.
 *
 * Dit is de basis onder het scherm **Beheer → Juridisch**: een verzoek om
 * inzage of verwijdering kun je niet beantwoorden als je niet weet waar je
 * moet kijken.
 *
 * **De lijst met plekken komt uit de instellingen en niet uit een tekst.**
 * Dat is het hele punt: een met de hand getypte opsomming van
 * bewaartermijnen klopt tot de dag dat iemand er één wijzigt, en dan geeft
 * de eigenaar een antwoord dat niet waar is. Zie `plekken()`.
 *
 * **Wat hier met opzet níet in zit, is een register van verzoeken.** Dat
 * zou betekenen dat we de naam en het adres van iemand die om verwijdering
 * vraagt gaan vastleggen -- nieuwe persoonsgegevens aanmaken om een
 * verzoek over persoonsgegevens af te handelen. De AVG vraagt dat ook niet.
 * Een verzoek komt per mail binnen en wordt per mail beantwoord.
 *
 * Zie docs/security/verzoeken-van-bezoekers.md.
 */
class Gegevensoverzicht
{
    /**
     * Hoeveel dagen een mislukte mailpoging blijft staan.
     *
     * Dit getal staat in `routes/console.php` als `--hours=336` op
     * `queue:prune-failed`. Verander je het daar, verander het dan hier --
     * het staat op het scherm en in een antwoord aan een bezoeker.
     */
    public const MISLUKTE_MAIL_DAGEN = 14;

    /** Hoeveel regels we hoogstens teruggeven bij een zoekopdracht. */
    private const MAXIMUM = 100;

    /**
     * Zoek een persoon op e-mailadres of IP-adres.
     *
     * Alleen het beveiligingslogboek komt hieruit, en dat is geen
     * onvolledigheid maar de uitkomst: dat is de **enige** tabel in deze
     * applicatie waarin een bezoeker terug te vinden is. Zie `plekken()`
     * voor waarom de andere plekken niets opleveren.
     *
     * **De jokertekens van LIKE gaan er via [`Zoekterm`](../Zoekterm.php)
     * uit, en juist hier is dat geen formaliteit.** Een underscore is in
     * een LIKE "één willekeurig teken" en staat in heel veel
     * e-mailadressen, dus `jan_de_vries@...` vond ook `janXdeYvries@...`.
     * Je mist er niemand door, maar je krijgt de regels van een ander te
     * zien -- op het ene scherm waar dat het meest ongewenst is.
     *
     * **Dit levert de modellen op en geen kant-en-klare regels.** De
     * antwoordtekst heeft ze óók nodig, in allebei de talen, en die kan
     * niets met labels die al in één taal zijn gezet. Zie `regels()` voor
     * wat het scherm ervan maakt en
     * [`Antwoordtekst`](Antwoordtekst.php) voor de andere kant.
     *
     * @return Collection<int, SecurityEvent>
     */
    public function zoek(?string $term): Collection
    {
        if ($term === null || mb_strlen($term) < 3) {
            return new Collection;
        }

        $patroon = Zoekterm::patroon($term);

        return SecurityEvent::query()
            ->where(fn ($query) => $query
                ->whereRaw("email like ? escape '".Zoekterm::TEKEN."'", [$patroon])
                ->orWhereRaw("ip_address like ? escape '".Zoekterm::TEKEN."'", [$patroon]))
            ->latest('created_at')
            ->limit(self::MAXIMUM)
            ->get();
    }

    /**
     * De gevonden gebeurtenissen zoals het scherm ze toont.
     *
     * Hier mogen de labels wél in de taal van het portaal staan: dit is
     * wat de eigenaar zelf leest, en niet wat de bezoeker terugkrijgt.
     *
     * @param  Collection<int, SecurityEvent>  $gebeurtenissen
     * @return array<int, array<string, mixed>>
     */
    public function regels(Collection $gebeurtenissen): array
    {
        return $gebeurtenissen
            ->map(fn (SecurityEvent $gebeurtenis) => [
                'id' => $gebeurtenis->id,
                'wanneer' => Datum::tijdstip($gebeurtenis->created_at),
                'wat' => $gebeurtenis->label(),
                'uitkomst' => $gebeurtenis->outcome->label(),
                'email' => $gebeurtenis->email,
                'ip' => $gebeurtenis->ip_address,
            ])
            ->all();
    }

    /**
     * Elke plek waar gegevens van een bezoeker kunnen staan, met de
     * bewaartermijn uit de instelling die hem bepaalt.
     *
     * **Ook de plekken waar niets te vinden is staan erin**, en dat is
     * geen opvulling: "ik heb gezocht en er staat niets" is een antwoord
     * dat je moet kunnen geven, en dan moet je weten dat je op de goede
     * plekken hebt gekeken.
     *
     * `zoekbaar` zegt of een persoon er terug te vinden is, `wisbaar` of
     * de eigenaar er iets uit kan verwijderen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function plekken(): array
    {
        return [
            [
                'sleutel' => 'beveiliging',
                'bewaartermijn' => __(':aantal dagen', [
                    'aantal' => (int) config('security.logging.retention_days'),
                ]),
                'zoekbaar' => true,
                'wisbaar' => false,
                'aantal' => null,
            ],
            [
                'sleutel' => 'mail',
                'bewaartermijn' => __(':aantal dagen', [
                    'aantal' => (int) config('mail.log_retention_days'),
                ]),
                'zoekbaar' => false,
                'wisbaar' => false,
                'aantal' => null,
            ],
            [
                'sleutel' => 'mislukte-mail',
                'bewaartermijn' => __(':aantal dagen', [
                    'aantal' => self::MISLUKTE_MAIL_DAGEN,
                ]),
                'zoekbaar' => false,
                'wisbaar' => true,
                'aantal' => $this->mislukteMail(),
            ],
            [
                'sleutel' => 'sessies',
                'bewaartermijn' => __(':aantal minuten', [
                    'aantal' => (int) config('session.lifetime'),
                ]),
                'zoekbaar' => false,
                'wisbaar' => false,
                'aantal' => null,
            ],
            [
                'sleutel' => 'bezoekcodes',
                'bewaartermijn' => __('Eén dag'),
                'zoekbaar' => false,
                'wisbaar' => false,
                'aantal' => null,
            ],
            [
                'sleutel' => 'bezoekcijfers',
                'bewaartermijn' => __('Onbeperkt'),
                'zoekbaar' => false,
                'wisbaar' => false,
                'aantal' => null,
            ],
            [
                'sleutel' => 'activiteit',
                'bewaartermijn' => __(':aantal dagen', [
                    'aantal' => (int) config('security.logging.activity_retention_days'),
                ]),
                'zoekbaar' => false,
                'wisbaar' => false,
                'aantal' => null,
            ],
        ];
    }

    /**
     * Hoeveel mailpogingen er zijn mislukt en nog klaarstaan.
     *
     * **Dit is de enige plek waar een bericht van een bezoeker in onze
     * database kan blijven liggen**, en hij is nergens in het portaal te
     * zien. Daarom staat hij op dit scherm: een bericht dat niet verstuurd
     * kon worden blijft hier veertien dagen staan, mét naam, adres en
     * inhoud.
     */
    public function mislukteMail(): int
    {
        return DB::table('failed_jobs')->count();
    }
}
