<?php

namespace App\Support\Contact;

use App\Enums\ContactVeld;
use App\Enums\ContactVeldStatus;
use App\Enums\PageSectionKey;
use App\Models\ContactField;
use App\Models\ContactSubject;
use App\Models\PageSection;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Het contactformulier zoals het op dit moment is ingesteld.
 *
 * **Eén plek die weet welke velden er zijn, want er zijn drie afnemers die
 * het niet oneens mogen worden**: de validatie op de server, het formulier
 * dat de bezoeker ziet, en het beheerscherm. Zou elk van die drie zijn
 * eigen lijst opbouwen, dan is "verplicht in de browser" vroeg of laat
 * iets anders dan `required` op de server -- en dan krijgt een bezoeker
 * een foutmelding over een veld dat er niet staat.
 *
 * ```
 * ContactVeld (enum)        wat een veld ís
 *         +
 * contact_fields (db)       of het aan staat
 *         ↓
 * Contactformulier          hier
 *      ↙        ↘
 * de validatie   de props voor het formulier
 * ```
 *
 * Zie docs/architecture/modules/contact.md.
 */
class Contactformulier
{
    /** @var Collection<string, ContactField>|null */
    private ?Collection $rijen = null;

    /** @var Collection<int, ContactSubject>|null */
    private ?Collection $onderwerpen = null;

    /** @return Collection<string, ContactField> */
    public function rijen(): Collection
    {
        return $this->rijen ??= ContactField::query()
            ->opVolgorde()
            ->get()
            ->keyBy(fn (ContactField $rij) => $rij->key->value);
    }

    /**
     * De onderwerpen die een bezoeker kan kiezen.
     *
     * @return Collection<int, ContactSubject>
     */
    public function onderwerpen(): Collection
    {
        return $this->onderwerpen ??= ContactSubject::query()
            ->online()
            ->opVolgorde()
            ->get();
    }

    /**
     * De stand van één veld.
     *
     * Ontbreekt de rij -- een enum-case die nog niet geseed is -- dan valt
     * een vast veld terug op verplicht en de rest op uit. Dat is de veilige
     * kant op: liever een veld dat even ontbreekt dan een formulier dat
     * omvalt op een rij die er nog niet is.
     */
    public function stand(ContactVeld $veld): ContactVeldStatus
    {
        return $this->rijen()->get($veld->value)?->stand()
            ?? $veld->standaardStatus();
    }

    /**
     * Of het contactformulier op de site staat.
     *
     * **Nodig op twee plekken, en daarom hier.** De aparte contactpagina
     * geeft een 404 als het onderdeel uit staat, en `ContactController`
     * weigert dan een inzending. Dat tweede ontbrak: het formulier
     * verdween netjes van de site, maar het adres waar het naartoe stuurde
     * bleef aannemen en mailen. Een oud tabblad of een bot met die URL
     * kwam er dus nog door, terwijl de eigenaar dacht dat hij het had
     * uitgezet.
     *
     * De regel is dezelfde als op de landingspagina, en met opzet letterlijk
     * dezelfde: staat er nog geen enkele rij, dan is er nooit geseed en valt
     * alles terug op de volgorde uit de code -- anders levert een vergeten
     * `db:seed` een site zonder contactformulier op. Zie
     * `HomeController::secties()`.
     */
    public function staatAan(): bool
    {
        if (PageSection::query()->count() === 0) {
            return true;
        }

        return PageSection::query()
            ->aangezet()
            ->where('key', PageSectionKey::Contact)
            ->exists();
    }

    /** Of de bezoeker een eigen onderwerp mag typen. */
    public function eigenOnderwerpToegestaan(): bool
    {
        $rij = $this->rijen()->get(ContactVeld::Onderwerp->value);

        // Zonder onderwerpen om uit te kiezen is een vrij veld de enige
        // manier om er een te geven, wat de instelling ook zegt.
        return $this->onderwerpen()->isEmpty() || ($rij?->eigenToegestaan() ?? true);
    }

    /**
     * De validatieregels, voor élk veld uit de enum.
     *
     * **Een uitgezet veld krijgt `exclude` en niet "geen regel".** Zonder
     * regel blijft de waarde weliswaar buiten `safe()`, maar dan hangt het
     * ervan af wie `safe()` gebruikt en wie `all()`. Met `exclude` haalt de
     * validator hem weg en kan geen enkele latere aanroep er nog bij.
     *
     * **En niet `prohibited`.** Dat zou een bezoeker wiens formulier tien
     * minuten openstond een foutmelding geven over een veld dat hij netjes
     * heeft ingevuld, terwijl de eigenaar het ondertussen uitzette. Stil
     * weglaten is daar het juiste antwoord.
     *
     * @return array<string, array<int, mixed>>
     */
    public function regels(): array
    {
        $regels = [];

        foreach (ContactVeld::cases() as $veld) {
            if ($veld === ContactVeld::Onderwerp) {
                $regels += $this->onderwerpRegels();

                continue;
            }

            $stand = $this->stand($veld);

            $regels[$veld->value] = $stand->zichtbaar()
                ? [$stand->verplicht() ? 'required' : 'nullable', ...$veld->regels()]
                : ['exclude'];
        }

        return $regels;
    }

    /**
     * Het onderwerp is twee velden: een keuze en een eigen tekst.
     *
     * **`exists` zonder `where('published', true)`.** Een onderwerp dat de
     * eigenaar net heeft uitgezet terwijl de bezoeker zat te typen, is geen
     * aanval maar een wedloop -- en een onderwerp is geen geheim. Hem
     * accepteren kost niets; hem weigeren kost een aanvraag.
     *
     * @return array<string, array<int, mixed>>
     */
    private function onderwerpRegels(): array
    {
        $stand = $this->stand(ContactVeld::Onderwerp);

        if (! $stand->zichtbaar()) {
            return [
                'subject_id' => ['exclude'],
                'subject_text' => ['exclude'],
            ];
        }

        $bestaat = Rule::exists('contact_subjects', 'id');

        if (! $this->eigenOnderwerpToegestaan()) {
            return [
                'subject_id' => [
                    $stand->verplicht() ? 'required' : 'nullable',
                    'integer',
                    $bestaat,
                ],
                // De lijst staat dicht, dus een getypt onderwerp is geen
                // geldige invoer. `exclude` en niet `prohibited`: de regel
                // hierboven geeft al een melding die de bezoeker kán
                // oplossen.
                'subject_text' => ['exclude'],
            ];
        }

        return [
            'subject_id' => ['nullable', 'integer', $bestaat],
            'subject_text' => [
                $stand->verplicht() ? 'required_without:subject_id' : 'nullable',
                'string',
                'max:'.ContactVeld::Onderwerp->maximum(),
            ],
        ];
    }

    /**
     * De namen die in een foutmelding worden gebruikt.
     *
     * @return array<string, string>
     */
    public function attributen(): array
    {
        $namen = [];

        foreach (ContactVeld::cases() as $veld) {
            $namen[$veld->value] = $veld->attribuut();
        }

        $namen['subject_id'] = ContactVeld::Onderwerp->attribuut();
        $namen['subject_text'] = ContactVeld::Onderwerp->attribuut();

        return $namen;
    }

    /**
     * Welke velden er op dit moment aan staan.
     *
     * Gaat mee met de aanvraag als momentopname; zie
     * `ContactSubmission::$shown`.
     *
     * @return array<int, string>
     */
    public function aanstaandeVelden(): array
    {
        return array_values(array_map(
            fn (ContactVeld $veld) => $veld->value,
            array_filter(
                ContactVeld::cases(),
                fn (ContactVeld $veld) => $this->stand($veld)->zichtbaar(),
            ),
        ));
    }

    /**
     * De velden zoals de browser ze nodig heeft.
     *
     * Hieruit tekent `ContactSection.vue` zijn formulier. Dat het uit
     * dezelfde klasse komt als `regels()` is het hele punt: het sterretje,
     * het `required`-attribuut, de maximale lengte en de servervalidatie
     * kunnen daarmee niet meer uiteenlopen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function voorDeSite(): array
    {
        $velden = [];

        foreach (ContactVeld::cases() as $veld) {
            $stand = $this->stand($veld);

            if (! $stand->zichtbaar()) {
                continue;
            }

            $velden[] = [
                'naam' => $veld->value,
                'label' => $veld->label(),
                'type' => $veld->type()->value,
                'verplicht' => $stand->verplicht(),
                'autocomplete' => $veld->autocomplete(),
                'maximum' => $veld->maximum(),

                /*
                 * De plek uit de database, of die uit de enum als de rij er
                 * nog niet is. `isset` en geen `?->position ?? ...`: de
                 * kolom is niet nullable, dus de statische analyse ziet die
                 * tweede tak als onbereikbaar.
                 */
                'positie' => isset($this->rijen()[$veld->value])
                    ? $this->rijen()[$veld->value]->position
                    : $veld->standaardPositie(),
            ];
        }

        usort($velden, fn (array $a, array $b) => $a['positie'] <=> $b['positie']);

        return $velden;
    }

    /**
     * De onderwerpen voor de keuzelijst, in de taal van de bezoeker.
     *
     * @return array<int, array<string, mixed>>
     */
    public function onderwerpenVoorDeSite(): array
    {
        return $this->onderwerpen()
            /*
             * Alleen het id en de naam.
             *
             * **`featured` gaat hier bewust níet mee.** Uitlichten is iets
             * van het scherm Aanvragen: de eigenaar wil een bínnengekomen
             * aanvraag met zo'n onderwerp beter zien. Het formulier is voor
             * de bezoeker, en dat hoort een neutrale lijst te blijven --
             * een onderwerp dat eruit springt duwt hem een kant op die niet
             * de zijne is.
             */
            ->map(fn (ContactSubject $onderwerp) => [
                'id' => $onderwerp->id,
                'naam' => $onderwerp->naam(),
            ])
            ->values()
            ->all();
    }

    /**
     * Een vingerafdruk van het formulier zoals de bezoeker het kreeg.
     *
     * Gaat als verborgen veld mee. Komt er een andere waarde terug, dan
     * heeft de eigenaar het formulier gewijzigd terwijl de bezoeker zat te
     * typen -- en dan hoort daar één nette waarschuwing bij in plaats van
     * foutmeldingen over velden die niet op zijn scherm stonden. Zie
     * ContactRequest::after().
     *
     * **Hij hasht de inhoud en niet de tijdstempels, en dat is een
     * correctie.** Eerst stond hier `max('updated_at')` van twee tabellen
     * plus een rijaantal. Die kolommen hebben secondeprecisie, dus een
     * wijziging in dezelfde seconde als de vorige leverde dezelfde
     * vingerafdruk op -- de controle keek dan weg. Dat is precies het soort
     * stilte waar dit veld tegen moest beschermen.
     *
     * Door de werkelijke stand te hashen klopt hij altijd, en bovendien:
     *
     * - **drie queries minder.** Wat hier wordt gehasht is al ingelezen
     *   door `rijen()` en `onderwerpen()`; de aggregaten waren extra werk.
     * - **minder valse treffers.** Een onderwerp dat offline staat zat wel
     *   in `max('updated_at')` maar staat niet op het formulier. De
     *   eigenaar die daaraan sleutelde onderbrak een bezoeker die er
     *   niets van zou merken.
     *
     * Alleen wat de bezoeker daadwerkelijk ziet of mag versturen zit erin.
     */
    public function versie(): string
    {
        $velden = ContactVeld::cases();

        // De plek hoort erbij: wie de velden alleen hersleept verandert
        // wél wat de bezoeker voor zich heeft.
        $stand = array_map(
            fn (ContactVeld $veld) => implode(':', [
                $veld->value,
                $this->stand($veld)->value,
                (string) (isset($this->rijen()[$veld->value])
                    ? $this->rijen()[$veld->value]->position
                    : $veld->standaardPositie()),
            ]),
            $velden,
        );

        /*
         * Het id en de naam, en niet of hij uitgelicht is: uitlichten
         * verandert niets aan wat de bezoeker voor zich heeft. Het is een
         * merkje op het scherm Aanvragen.
         */
        $onderwerpen = $this->onderwerpen()
            ->map(fn (ContactSubject $onderwerp) => $onderwerp->id.':'.$onderwerp->naam())
            ->all();

        return substr(hash('xxh128', implode('|', [
            ...$stand,
            ...$onderwerpen,
            $this->eigenOnderwerpToegestaan() ? 'eigen' : 'vast',
        ])), 0, 12);
    }
}
