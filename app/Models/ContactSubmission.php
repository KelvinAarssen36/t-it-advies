<?php

namespace App\Models;

use App\Enums\ContactVeld;
use App\Support\Datum;
use Database\Factories\ContactSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eén aanvraag die via het contactformulier binnenkwam.
 *
 * **Dit is de enige plek in dit project waar inhoud van een bezoeker
 * blijft staan.** Dat is een bewuste keuze met gevolgen die buiten dit
 * bestand liggen:
 *
 * - er is een bewaartermijn van een jaar (`config('site.contact.retention_days')`),
 *   opgeruimd door `PruneContactSubmissions`;
 * - de privacyverklaring beschrijft het, en zei eerder het tegendeel;
 * - het scherm Juridisch kan hierin zoeken op e-mailadres en kan een
 *   aanvraag verwijderen op verzoek van de bezoeker.
 *
 * Zie docs/architecture/modules/contact.md en
 * docs/security/verzoeken-van-bezoekers.md.
 *
 * **Er staat met opzet geen `LogsActivity` op.** Die trait legt bij het
 * aanmaken alle invulbare velden vast, dus naam, adres en het volledige
 * bericht zouden een tweede keer in `activity_entries` komen -- met een
 * eigen bewaartermijn, op een scherm dat niet over contact gaat, en
 * `redact()` in config/security.php vangt `message` niet af. Alleen het
 * **verwijderen** wordt gelogd, expliciet via `SecurityLogger`, omdat
 * daar gegevens door verdwijnen.
 *
 * **En met opzet geen IP-adres.** Dat zou een tweede beveiligingslogboek
 * maken met een andere termijn en zonder doel: een geblokkeerde poging
 * staat al mét IP in `security_events`, en een geslaagde aanvraag hoeft
 * niet tot een verbinding herleidbaar te zijn.
 *
 * @property int $id
 * @property string $locale
 * @property string $name
 * @property string $email
 * @property string|null $company
 * @property string|null $phone
 * @property int|null $subject_id
 * @property string $subject_text
 * @property bool $subject_custom
 * @property string $message
 * @property array<int, string> $shown
 * @property Carbon|null $read_at
 * @property Carbon|null $answered_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'locale',
    'name',
    'email',
    'company',
    'phone',
    'subject_id',
    'subject_text',
    'subject_custom',
    'message',
    'shown',
    'read_at',
    'answered_at',
])]
class ContactSubmission extends Model
{
    /** @use HasFactory<ContactSubmissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_custom' => 'boolean',
            'shown' => 'array',
            'read_at' => 'datetime',
            'answered_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeNieuwsteEerst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOngelezen(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOnbeantwoord(Builder $query): Builder
    {
        return $query->whereNull('answered_at');
    }

    /** @return BelongsTo<ContactSubject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(ContactSubject::class);
    }

    public function gelezen(): bool
    {
        return $this->read_at !== null;
    }

    public function beantwoord(): bool
    {
        return $this->answered_at !== null;
    }

    /**
     * Of het gekozen onderwerp inmiddels verwijderd is.
     *
     * De tekst staat er nog, de verwijzing niet meer. Het scherm kan dan
     * zeggen "dit onderwerp bestaat niet meer" in plaats van de aanvraag
     * als zelfbedacht te tonen.
     */
    public function onderwerpVerdwenen(): bool
    {
        return ! $this->subject_custom && $this->subject_id === null;
    }

    /**
     * Het onderwerp zoals de eigenaar het hoort te lezen.
     *
     * **`subject_text` is het archief en niet het scherm.** Daarin staat
     * wat de bezoeker zág, dus bij een Engelse bezoeker Engels. Dat is
     * precies goed om te bewaren -- en precies verkeerd om aan de eigenaar
     * te tonen: zijn postbus stond vol Nederlandse regels met daartussen
     * "Informal conversation", en de keuzelijst om op te filteren zei
     * "Vrijblijvend gesprek" voor datzelfde onderwerp. Dan zoek je op wat
     * je ziet en vind je niets.
     *
     * Bestaat het onderwerp nog, dan komt de naam dus uit het onderwerp
     * zelf, in de taal van de lezer. Is het verwijderd of typte de
     * bezoeker er zelf een, dan is de bewaarde tekst het enige dat er is
     * -- en wat iemand zelf intypte vertalen we nooit.
     */
    public function onderwerpVoorDeEigenaar(?string $taal = null): string
    {
        return $this->subject?->naam($taal) ?? $this->subject_text;
    }

    /**
     * Of een veld aan stond toen deze aanvraag binnenkwam.
     *
     * Het verschil tussen "de bezoeker liet het leeg" en "dit veld bestond
     * toen niet" is een ander verhaal, en zonder dit kan het scherm die
     * twee niet onderscheiden.
     */
    public function stondAan(ContactVeld $veld): bool
    {
        return in_array($veld->value, $this->shown, true);
    }

    /**
     * De aanvraag zoals het beheerscherm hem toont.
     *
     * @return array<string, mixed>
     */
    public function voorHetScherm(): array
    {
        return [
            'id' => $this->id,
            'naam' => $this->name,
            'email' => $this->email,
            'bedrijf' => $this->company,
            'telefoon' => $this->phone,
            // In de taal van het portaal; zie onderwerpVoorDeEigenaar().
            'onderwerp' => $this->onderwerpVoorDeEigenaar(),
            'onderwerpZelf' => $this->subject_custom,
            'onderwerpVerdwenen' => $this->onderwerpVerdwenen(),

            /*
             * Of het onderwerp door de eigenaar is uitgelicht.
             *
             * **Daar is uitlichten voor.** Hij zet een vinkje bij een
             * onderwerp -- spoed bijvoorbeeld -- en dan springt een
             * aanvraag met dat onderwerp eruit in zijn postbus. Het
             * verandert niets op de site; de bezoeker merkt er niets van.
             *
             * `?->` want het onderwerp kan verwijderd zijn. Dan is er niets
             * uitgelicht, en dat is juist: de eigenaar heeft het onderwerp
             * zelf weggehaald.
             */
            'onderwerpUitgelicht' => (bool) $this->subject?->featured,
            'bericht' => $this->message,
            'taal' => $this->locale,
            'wanneer' => Datum::tijdstip($this->created_at),
            'gelezen' => $this->gelezen(),
            'beantwoord' => $this->beantwoord(),
            'velden' => $this->shown,
        ];
    }
}
