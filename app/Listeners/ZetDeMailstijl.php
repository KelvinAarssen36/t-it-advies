<?php

namespace App\Listeners;

use App\Models\SiteSetting;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Laat ook de mail die Laravel zelf stuurt de gekozen mailstijl volgen.
 *
 * De naam is met opzet niet `VolgtDeMailstijl`: zo heet de trait op onze
 * eigen mailables, en twee verschillende dingen met dezelfde naam zoekt
 * niemand ooit terug.
 *
 * **Dit gat zat er echt in.** De eigenaar kiest onder Instellingen →
 * Weergave tussen licht en de huisstijl, en onze vier mailables pakken die
 * keuze op via de trait `App\Concerns\VolgtDeMailstijl`. Maar twee mails
 * komen niet van ons: "bevestig je e-mailadres" en "kies een nieuw
 * wachtwoord" zijn notificaties van het framework. Die bouwen een
 * `MailMessage`, en die pakt het thema uit `config('mail.markdown.theme')`
 * -- een vaste waarde. Dus zette hij de huisstijl aan, werd alles
 * donkerblauw, en bleven precies de twee mails die je krijgt als je
 * buitengesloten bent wit.
 *
 * **Waarom een listener en niet gewoon in een service provider.** Daar zou
 * je de instelling bij élk verzoek uit de database lezen, ook bij de
 * duizenden waarin geen mail wordt verstuurd. Erger: een provider draait
 * ook tijdens `migrate` op een verse database, en dan bestaat de tabel nog
 * niet. Zo kost het een query op het moment dat er daadwerkelijk een
 * notificatie uitgaat, en nul daarbuiten.
 *
 * `NotificationSending` is het juiste moment: Laravel vuurt hem in
 * `shouldSendNotification()`, vóórdat het kanaal de mail opbouwt en
 * rendert. Zie Illuminate\Notifications\NotificationSender.
 *
 * Zie docs/architecture/mail-en-queues.md.
 */
class ZetDeMailstijl
{
    public function handle(NotificationSending $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $thema = $this->thema();

        if ($thema === null) {
            return;
        }

        config(['mail.markdown.theme' => $thema]);
    }

    /**
     * Het thema dat de eigenaar heeft gekozen, of `null` als we het niet
     * kunnen weten.
     *
     * **Een mail die uitgaat mag hier nooit op stuklopen.** Deze code
     * draait ook in een queue worker en in opdrachtregels, en de reden om
     * de mail te versturen staat los van de vraag hoe hij eruitziet. Kan
     * de instelling niet gelezen worden -- de tabel bestaat nog niet, de
     * database is even weg -- dan blijft de vaste waarde uit de config
     * staan, en dat is de lichte stijl: die komt overal goed aan.
     */
    private function thema(): ?string
    {
        try {
            if (! Schema::hasTable('site_settings')) {
                return null;
            }

            return SiteSetting::mailstijl()->thema();
        } catch (Throwable) {
            return null;
        }
    }
}
