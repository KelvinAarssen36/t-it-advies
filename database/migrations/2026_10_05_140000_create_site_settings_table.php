<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instellingen die over de hele site gaan, in één rij.
 *
 * Nu één kolom: hoe de mail van deze website eruitziet.
 *
 * **Waarom niet bij `contact_settings`.** Dat was de kortste weg -- daar
 * staat de tekst van de bevestigingsmail al in -- maar de mailstijl geldt
 * voor élke mail die de site verstuurt, ook de beveiligingsmeldingen die
 * niets met contact te maken hebben. Een instelling die overal over gaat
 * in een tabel die "contact" heet is het soort ding waar je een jaar later
 * naar zoekt op de verkeerde plek.
 *
 * **En niet bij de gebruiker.** De tijdzone van het dashboard staat wél op
 * `users`, want dat is een voorkeur van wie kijkt. Dit is geen voorkeur
 * maar een eigenschap van de website: de mail gaat naar bezoekers, en die
 * hebben geen account.
 *
 * Eén rij, zoals `contact_settings`. Komt er een tweede site-brede
 * instelling, dan is dit de plek.
 *
 * Zie docs/architecture/mail-en-queues.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();

            /*
             * `licht` als standaard: die komt in elk mailprogramma goed
             * aan. De huisstijl is mooier maar riskanter, en dat hoort een
             * bewuste keuze te zijn. Zie App\Enums\MailStijl.
             */
            $table->string('mail_style', 16)->default('licht');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
