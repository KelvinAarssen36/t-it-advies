<?php

namespace App\Http\Controllers\Settings;

use App\Enums\MailStijl;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Instellingen → Weergave.
 *
 * Twee dingen op één scherm, en ze lijken alleen op elkaar:
 *
 * 1. **Licht of donker in het portaal.** Een voorkeur van wie kijkt, die in
 *    de browser wordt bewaard. De publieke site blijft altijd donker; dat
 *    is de huisstijl en geen instelling.
 * 2. **De stijl van de uitgaande mail.** Een eigenschap van de website,
 *    want die mail gaat naar bezoekers. Staat daarom in de database en niet
 *    bij de gebruiker.
 *
 * Dat dit één scherm is komt doordat het voor de klant één vraag is: hoe
 * ziet het eruit. Dat het technisch twee verschillende dingen zijn staat
 * hierboven, zodat niemand ze later per ongeluk gelijktrekt.
 *
 * **Dit was een `Route::inertia`.** Dat kon niet meer toen de mailstijl een
 * instelling werd: het scherm moet weten welke stijl er nu staat.
 *
 * Zie docs/architecture/mail-en-queues.md.
 */
class WeergaveController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('settings/Appearance', [
            'mailStijl' => SiteSetting::mailstijl()->value,
            'mailStijlen' => MailStijl::opties(),
        ]);
    }
}
