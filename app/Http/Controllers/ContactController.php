<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessageMail;
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

        return back()->with('status', __('Bedankt voor je bericht. We nemen snel contact op.'));
    }
}
