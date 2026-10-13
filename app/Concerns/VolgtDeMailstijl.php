<?php

namespace App\Concerns;

use App\Models\SiteSetting;

/**
 * Zet de mailstijl die de eigenaar heeft gekozen op deze mail.
 *
 * **In de constructor en niet bij de aanroeper**, om dezelfde reden als bij
 * de taal: zo kan niemand het vergeten. Een mail die per ongeluk het
 * standaardthema van Laravel gebruikt valt niet op in een test -- hij komt
 * gewoon aan, alleen in een andere stijl dan de rest.
 *
 * `Mailable::$theme` wint van `config('mail.markdown.theme')`; zie
 * `Mailable::markdownRenderer()`. De config blijft dus staan als terugval
 * voor een mail die deze trait niet gebruikt.
 *
 * **Eén query per mail, en die is het waard.** De stijl staat in de
 * database omdat de klant hem zelf moet kunnen omzetten, en een mail die
 * in de wachtrij wordt opgebouwd heeft geen verzoek om hem uit te lezen.
 * Hij in de config zetten zou betekenen dat de klant een
 * configuratiebestand moet aanpassen, en dat is precies wat dit portaal
 * moet voorkomen.
 *
 * Zie docs/architecture/mail-en-queues.md en App\Enums\MailStijl.
 */
trait VolgtDeMailstijl
{
    protected function pasDeMailstijlToe(): void
    {
        $this->theme = SiteSetting::mailstijl()->thema();
    }
}
