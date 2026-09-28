<?php

namespace Tests\Feature\Security;

use App\Mail\CrashAlertMail;
use App\Support\Security\CrashReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * De melding die uitgaat als de applicatie omvalt.
 *
 * De grenzen zijn hier belangrijker dan de mail zelf. Een melder die te
 * veel stuurt wordt weggefilterd, en dan ben je slechter af dan met geen
 * melder -- want dan denk je dat je bewaking hebt.
 *
 * Zie docs/operations/monitoring.md.
 */
class CrashReporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Cache::flush();

        // De melder zwijgt in local en testing; dat is het hele punt van
        // die grens. Voor deze tests doen we alsof we in productie staan.
        $this->app->detectEnvironment(fn () => 'production');
        config(['security.alerts.address' => 'beheer@voorbeeld.test']);
    }

    private function melder(): CrashReporter
    {
        return app(CrashReporter::class);
    }

    /**
     * Een fout die altijd van dezelfde regel komt.
     *
     * Dat is geen truc maar de werkelijkheid nabootsen: tien bezoeken aan
     * een kapotte pagina leveren tien uitzonderingen op, maar allemaal van
     * dezelfde plek in de code. De afkoeltijd rekent daarop.
     */
    private function fout(string $melding): RuntimeException
    {
        return new RuntimeException($melding);
    }

    public function test_a_crash_is_reported(): void
    {
        $this->melder()->report($this->fout('Er ging iets grondig mis.'));

        Mail::assertSent(CrashAlertMail::class);
    }

    public function test_a_404_is_not_a_crash(): void
    {
        // Een 404 zegt dat het systeem werkt. Zou die een mail opleveren,
        // dan is de postbus binnen een dag onbruikbaar.
        $this->melder()->report(new NotFoundHttpException);

        Mail::assertNothingSent();
    }

    public function test_the_same_error_does_not_send_twice(): void
    {
        // Eén kapotte pagina die tien keer wordt bezocht is één probleem.
        $this->melder()->report($this->fout('Dezelfde fout.'));
        $this->melder()->report($this->fout('Dezelfde fout.'));

        Mail::assertSentCount(1);
    }

    public function test_without_an_address_it_stays_quiet(): void
    {
        // Geen adres ingesteld is geen reden om te crashen tijdens het
        // afhandelen van een crash.
        config(['security.alerts.address' => null]);

        $this->melder()->report($this->fout('Niemand om te mailen.'));

        Mail::assertNothingSent();
    }

    public function test_nothing_sensitive_ends_up_in_the_mail(): void
    {
        /*
         * Een foutmelding is vrije tekst en kan van alles bevatten. Deze
         * mail landt in een postbus die minder goed beveiligd is dan de
         * applicatie; zie regel 2 in AGENTS.md.
         */
        $this->melder()->report(
            $this->fout('Mislukt met password=geheimpje123 in de aanroep.')
        );

        Mail::assertSent(CrashAlertMail::class, function (CrashAlertMail $mail) {
            return ! str_contains($mail->melding, 'geheimpje123');
        });
    }
}
