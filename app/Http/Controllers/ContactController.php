<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessageMail;
use App\Support\Bezoek\Bezoekteller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Neemt berichten van het contactformulier aan.
 *
 * De keten op de route is: rate limiting -> honeypot -> validatie met
 * Turnstile -> queue. Zie routes/web.php en
 * docs/security/spam-en-botbescherming.md.
 */
class ContactController extends Controller
{
    public function __construct(private readonly Bezoekteller $teller) {}

    public function store(ContactRequest $request): RedirectResponse
    {
        $validated = $request->safe();

        Mail::to(config('mail.contact_address', config('mail.from.address')))
            ->queue(new ContactMessageMail(
                senderName: $validated->string('name')->toString(),
                senderEmail: $validated->string('email')->toString(),
                senderSubject: $validated->string('subject')->toString(),
                body: $validated->string('message')->toString(),
            ));

        /*
         * Eén bij de dagteller, en niets van het bericht zelf.
         *
         * Dit is het enige cijfer dat zegt of de site zijn wérk doet in
         * plaats van alleen bekeken te worden. Het staat hier en niet in
         * de mailable, want een bericht is verstuurd op het moment dat de
         * bezoeker op de knop drukt -- niet pas wanneer de queue eraan
         * toekomt. Zie docs/architecture/bezoekcijfers.md.
         */
        $this->teller->telContact();

        return back()->with('status', __('Bedankt voor je bericht. We nemen snel contact op.'));
    }
}
