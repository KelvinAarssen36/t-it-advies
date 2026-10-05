<?php

namespace App\Support\Juridisch;

use App\Models\ContactSubmission;
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
     * Zoek een persoon in het beveiligingslogboek, op e-mailadres of IP.
     *
     * **Dit was de enige zoekopdracht en dat is het niet meer.** Zolang
     * een bericht uit het contactformulier de website verliet zodra het
     * verstuurd was, was `security_events` de enige tabel waarin een
     * bezoeker terug te vinden was. Sinds de aanvragen worden bewaard zijn
     * het er twee; zie `aanvragenVan()`. Het scherm doorzoekt ze allebei
     * met dezelfde zoekterm.
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

            /*
             * De contactaanvragen. **De eerste plek die zowel zoekbaar als
             * wisbaar is**, en daarmee de eerste waar een verzoek om
             * verwijdering in het portaal is af te handelen in plaats van
             * alleen in de mailbox.
             *
             * Hier staat inhoud van een bezoeker: zijn naam, zijn adres en
             * zijn bericht. Dat is bewust zo, met een bewaartermijn van een
             * jaar, en de privacyverklaring beschrijft het. Zie
             * docs/architecture/modules/contact.md.
             */
            [
                'sleutel' => 'aanvragen',
                'bewaartermijn' => __(':aantal dagen', [
                    'aantal' => (int) config('site.contact.retention_days'),
                ]),
                'zoekbaar' => true,
                'wisbaar' => true,
                'aantal' => $this->aanvragen(),
            ],

            /*
             * Het mailoverzicht. **Hier stond eerder dat er alleen het
             * eigen adres van de eigenaar als ontvanger in staat**, en dat
             * was waar tot er een bevestigingsmail naar de bezoeker ging.
             * Zijn adres staat daar nu als ontvanger in, en is dus terug
             * te vinden -- vandaar `zoekbaar`.
             */
            [
                'sleutel' => 'mail',
                'bewaartermijn' => __(':aantal dagen', [
                    'aantal' => (int) config('mail.log_retention_days'),
                ]),
                'zoekbaar' => true,
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
     * **Dit was de enige plek waar een bericht van een bezoeker in onze
     * database kon blijven liggen; nu is het de enige plek waar dat
     * ongemerkt gebeurt.** De aanvragen staan sinds de module Contact
     * netjes in het portaal onder Beheer → Aanvragen; een mislukte poging
     * is nergens te zien en blijft hier veertien dagen staan, mét naam,
     * adres en inhoud. Daarom staat hij op dit scherm.
     *
     * Overigens een verbetering van die module: een bericht raakt niet meer
     * kwijt als de mailprovider eruit ligt, want de aanvraag staat al
     * opgeslagen voordat de mail de wachtrij in gaat.
     */
    public function mislukteMail(): int
    {
        return DB::table('failed_jobs')->count();
    }

    /** Hoeveel contactaanvragen er op dit moment bewaard worden. */
    public function aanvragen(): int
    {
        return ContactSubmission::query()->count();
    }

    /**
     * De contactaanvragen van één persoon.
     *
     * **Dit is waarom het scherm Juridisch beter is geworden.** Een
     * verzoek om verwijdering ging eerst altijd over de mailbox, en daar
     * kan de applicatie niets. Nu kan de eigenaar de aanvraag hier vinden
     * en hier weghalen.
     *
     * Alleen op e-mailadres en niet op naam: een naam is niet uniek, en
     * iemand die om zijn gegevens vraagt geeft het adres waarmee hij
     * schreef. Zoeken op naam zou de aanvragen van een naamgenoot
     * opleveren.
     *
     * @return Collection<int, ContactSubmission>
     */
    public function aanvragenVan(?string $term): Collection
    {
        if ($term === null || mb_strlen($term) < 3) {
            return new Collection;
        }

        $patroon = Zoekterm::patroon($term);

        return ContactSubmission::query()
            ->whereRaw("email like ? escape '".Zoekterm::TEKEN."'", [$patroon])
            ->nieuwsteEerst()
            ->limit(self::MAXIMUM)
            ->get();
    }

    /**
     * Die aanvragen zoals het scherm ze toont.
     *
     * **Zonder het bericht.** Dit scherm is er om te kunnen zeggen dát er
     * iets van iemand staat en om het te kunnen verwijderen, niet om het
     * te lezen -- daarvoor is het scherm Aanvragen. Het bericht van een
     * bezoeker hoeft niet op twee schermen te staan.
     *
     * @param  Collection<int, ContactSubmission>  $aanvragen
     * @return array<int, array<string, mixed>>
     */
    public function aanvraagregels(Collection $aanvragen): array
    {
        return $aanvragen
            ->map(fn (ContactSubmission $aanvraag) => [
                'id' => $aanvraag->id,
                'wanneer' => Datum::tijdstip($aanvraag->created_at),
                'naam' => $aanvraag->name,
                'email' => $aanvraag->email,
                'onderwerp' => $aanvraag->subject_text,
            ])
            ->all();
    }
}
