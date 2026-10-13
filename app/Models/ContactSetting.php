<?php

namespace App\Models;

use App\Enums\ContactWeergave;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * De instellingen van de module Contact. Eén rij.
 *
 * Twee dingen: hoe het contact op de website wordt aangeboden, en de tekst
 * van de bevestigingsmail aan de bezoeker.
 *
 * Zie docs/architecture/modules/contact.md.
 *
 * @property int $id
 * @property ContactWeergave $display
 * @property string|null $confirmation_subject_nl
 * @property string|null $confirmation_subject_en
 * @property string|null $confirmation_body_nl
 * @property string|null $confirmation_body_en
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'display',
    'confirmation_subject_nl',
    'confirmation_subject_en',
    'confirmation_body_nl',
    'confirmation_body_en',
    'machine_translated_at',
])]
class ContactSetting extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display' => ContactWeergave::class,
            'machine_translated_at' => 'datetime',
        ];
    }

    /**
     * De instellingen, of een verse met de standaardtekst erin.
     *
     * Niet opgeslagen als hij nieuw is: dit wordt ook aangeroepen bij het
     * tonen van de publieke site, en een GET hoort niets weg te schrijven.
     * De seeder legt de echte rij aan. Zelfde aanpak als
     * `SectionHeading::voor()`.
     */
    public static function huidige(): self
    {
        return self::query()->first() ?? new self(self::standaard());
    }

    /**
     * De tekst waarmee de bevestigingsmail begint.
     *
     * **Een startpunt en geen definitieve tekst.** De klant schrijft hem
     * om zodra hij weet hoe hij wil klinken; dat is precies waarom het
     * venster bestaat. Maar er hoort iets te staan dat je zonder schamen
     * kunt versturen op de dag dat de site live gaat.
     *
     * Hij staat hier en niet in de seeder, zodat de twee niet uit elkaar
     * kunnen lopen; `ContactSeeder` haalt hem hiervandaan.
     *
     * @return array<string, mixed>
     */
    public static function standaard(): array
    {
        return [
            'display' => ContactWeergave::OpDePagina,

            'confirmation_subject_nl' => 'We hebben je bericht ontvangen',
            'confirmation_subject_en' => 'We have received your message',

            'confirmation_body_nl' => "Bedankt voor je bericht. Het is goed aangekomen en ik lees het zo snel mogelijk.\n\nJe hoeft verder niets te doen -- ik neem zelf contact met je op. Heb je er iets aan toe te voegen, dan kun je gewoon op deze mail antwoorden.",

            'confirmation_body_en' => "Thank you for your message. It arrived safely and I will read it as soon as I can.\n\nThere is nothing more you need to do -- I will get in touch with you. If you would like to add anything, you can simply reply to this email.",
        ];
    }

    /** Het onderwerp van de bevestiging, in de taal van de bezoeker. */
    public function bevestigingOnderwerp(string $taal): string
    {
        $tekst = $taal === 'en'
            ? $this->confirmation_subject_en
            : $this->confirmation_subject_nl;

        // Terugval op het Nederlands: een mail zonder onderwerp komt in
        // een spammap terecht, en dat is erger dan de verkeerde taal.
        return filled($tekst)
            ? (string) $tekst
            : (string) ($this->confirmation_subject_nl ?? self::standaard()['confirmation_subject_nl']);
    }

    /** De tekst van de bevestiging, in de taal van de bezoeker. */
    public function bevestigingTekst(string $taal): string
    {
        $tekst = $taal === 'en'
            ? $this->confirmation_body_en
            : $this->confirmation_body_nl;

        return filled($tekst)
            ? (string) $tekst
            : (string) ($this->confirmation_body_nl ?? self::standaard()['confirmation_body_nl']);
    }

    public static function activityName(): string
    {
        return __('Instellingen van het contactformulier');
    }

    public function activityLabel(): string
    {
        return __('Contact');
    }
}
