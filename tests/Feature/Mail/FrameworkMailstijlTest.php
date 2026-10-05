<?php

namespace Tests\Feature\Mail;

use App\Enums\MailStijl;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Ook de mail die Laravel zelf stuurt volgt de gekozen mailstijl.
 *
 * **Hier zat een gat.** Onze eigen vier mailables pakken de keuze van de
 * eigenaar op via de trait `App\Concerns\VolgtDeMailstijl`. Maar
 * "bevestig je e-mailadres" en "kies een nieuw wachtwoord" zijn
 * notificaties van het framework: die bouwen een `MailMessage` en pakken
 * hun thema uit `config('mail.markdown.theme')`, een vaste waarde. Dus
 * zette de eigenaar de huisstijl aan, werd alles donkerblauw, en bleven
 * precies de twee mails die je krijgt als je buitengesloten bent wit.
 *
 * Zie App\Listeners\ZetDeMailstijl.
 */
class FrameworkMailstijlTest extends TestCase
{
    use RefreshDatabase;

    private function zetStijl(MailStijl $stijl): void
    {
        SiteSetting::huidige()->fill(['mail_style' => $stijl])->save();
    }

    private function gebruiker(): User
    {
        return User::factory()->create(['email' => 'aarssen@atitadvies.nl']);
    }

    /**
     * De verificatiemail wordt donkerblauw als de eigenaar dat kiest.
     *
     * `#0a2340` is de kaart uit het huisstijlthema; die kleur staat niet in
     * het lichte thema. Op de gerenderde mail en niet op de config, want
     * die laatste zou een thema goedkeuren dat niet bestaat.
     */
    public function test_the_verification_mail_follows_the_house_style(): void
    {
        $this->zetStijl(MailStijl::Huisstijl);

        $gebruiker = $this->gebruiker();

        $html = $this->rendersVan($gebruiker, new VerifyEmail);

        $this->assertStringContainsString('#0a2340', $html);
    }

    /** En hij blijft licht als dat de keuze is. */
    public function test_and_stays_light_when_that_is_the_choice(): void
    {
        $this->zetStijl(MailStijl::Licht);

        $html = $this->rendersVan($this->gebruiker(), new VerifyEmail);

        $this->assertStringNotContainsString('#0a2340', $html);
        $this->assertStringContainsString('#ffffff', $html);
    }

    /** Hetzelfde voor de mail om een nieuw wachtwoord te kiezen. */
    public function test_the_password_reset_mail_follows_it_too(): void
    {
        $this->zetStijl(MailStijl::Huisstijl);

        $html = $this->rendersVan($this->gebruiker(), new ResetPassword('test-token'));

        $this->assertStringContainsString('#0a2340', $html);
    }

    /**
     * Zonder instelling blijft de vaste waarde staan.
     *
     * Een verse database heeft nog geen rij, en dan is licht de uitkomst --
     * dezelfde als `config('mail.markdown.theme')`. Een mail die uitgaat
     * mag nooit stuklopen op de vraag hoe hij eruitziet.
     */
    public function test_without_a_setting_it_falls_back_to_light(): void
    {
        $this->assertSame(0, SiteSetting::query()->count());

        $html = $this->rendersVan($this->gebruiker(), new VerifyEmail);

        $this->assertStringNotContainsString('#0a2340', $html);
    }

    /**
     * De mail echt laten versturen en de HTML teruggeven.
     *
     * Via `Notification::fake()` zou de listener wel draaien maar de mail
     * nooit worden opgebouwd, en dan meet je niets. Daarom de echte weg,
     * met de mailer op `array`: dan staat het bericht in de postbus van de
     * transport en kun je erin kijken.
     */
    private function rendersVan(User $gebruiker, object $notificatie): string
    {
        config(['mail.default' => 'array']);

        $postbus = app('mailer')->getSymfonyTransport();
        $gebruiker->notify($notificatie);

        $berichten = $postbus->messages();

        $this->assertNotEmpty($berichten, 'Er is geen mail verstuurd.');

        return (string) $berichten[count($berichten) - 1]->getOriginalMessage()->getHtmlBody();
    }
}
