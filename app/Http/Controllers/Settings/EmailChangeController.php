<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\EmailChangeRequest;
use App\Mail\InlogadresAangevraagdMail;
use App\Mail\InlogadresBevestigenMail;
use App\Mail\InlogadresGewijzigdMail;
use App\Models\EmailChange;
use App\Models\User;
use App\Support\Security\SecurityLogger;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Het inlogadres wijzigen, in drie stappen.
 *
 * **Het adres op `users` verandert pas als het nieuwe zich heeft
 * bewezen.** Dat is de kern. Een typefout in het e-mailveld kan de
 * eigenaar dus nooit buitensluiten: tot de bevestiging logt hij gewoon nog
 * in met zijn oude adres.
 *
 * Hiervoor stond het e-mailadres gewoon in het profielformulier. Eén
 * verkeerde toetsaanslag en het inlogadres van het portaal was een adres
 * dat niet bestaat -- zonder wachtwoord, zonder code, zonder bevestiging,
 * en zonder weg terug.
 *
 * ## De stappen
 *
 * 1. **Aanvragen** (`store`). Achter een verse authenticator-code
 *    (`2fa.confirm` op de route) en het huidige wachtwoord. Er gaan twee
 *    mails uit: een bevestigingslink naar het nieuwe adres, en een
 *    waarschuwing naar het oude met een link om het af te breken.
 * 2. **Bevestigen** (`confirm`). Pas hier wisselt het adres. De link is
 *    een uur geldig en werkt één keer. Daarna gaat er nog een mail naar
 *    het oude adres, met de weg terug.
 * 3. **Terugdraaien** (`revert`). Eén link voor twee gevallen: vóór de
 *    bevestiging breekt hij de aanvraag af, erna zet hij het oude adres
 *    terug. Veertien dagen geldig.
 *
 * **Stap 2 en 3 werken zonder inloggen**, en dat is met opzet. Stap 3 is
 * het vangnet voor precies de situatie waarin je niet meer binnenkomt;
 * een herstellink achter een inlogscherm is geen herstellink. De identiteit
 * is in stap 1 al bewezen met wachtwoord én code, en het token is
 * eenmalig.
 *
 * Zie docs/security/inlogadres-wijzigen.md.
 */
class EmailChangeController extends Controller
{
    /**
     * Het venster openen, met de authenticator ervoor.
     *
     * Deze actie bewaart niets en toont niets eigens; `2fa.confirm` op de
     * route doet het werk. Slaagt die, dan komt de eigenaar terug op zijn
     * profiel met het venster open -- en heeft hij daarna de volle
     * geldigheidsduur om rustig in te typen.
     *
     * Zonder deze omweg zou de code pas bij het versturen worden gevraagd
     * en was het ingevulde formulier weg.
     */
    public function create(): RedirectResponse
    {
        return to_route('profile.edit')->with('inlogadresVenster', true);
    }

    public function store(
        EmailChangeRequest $request,
        SecurityLogger $logboek,
    ): RedirectResponse {
        /** @var User $gebruiker */
        $gebruiker = $request->user();
        $nieuw = $request->nieuwAdres();

        [$bevestigPlat, $bevestigHash] = EmailChange::versToken();
        [$herstelPlat, $herstelHash] = EmailChange::versToken();

        $nu = Carbon::now();

        /*
         * Een openstaande aanvraag vervalt. Twee tegelijk zou betekenen
         * dat er twee geldige bevestigingslinks rondgaan naar twee
         * verschillende adressen, en dan bepaalt wie het eerst klikt waar
         * je post heen gaat.
         */
        $wijziging = DB::transaction(function () use (
            $gebruiker,
            $nieuw,
            $bevestigHash,
            $herstelHash,
            $nu,
        ): EmailChange {
            EmailChange::query()
                ->where('user_id', $gebruiker->id)
                ->openstaand()
                ->update(['expires_at' => $nu]);

            return EmailChange::query()->create([
                'user_id' => $gebruiker->id,
                'from_email' => $gebruiker->email,
                'to_email' => $nieuw,
                'confirm_token' => $bevestigHash,
                'revert_token' => $herstelHash,
                'requested_at' => $nu,
                'expires_at' => $nu->copy()->addMinutes(EmailChange::BEVESTIGEN_GELDIG),
                /*
                 * De weg terug staat meteen open, nog vóór de bevestiging.
                 * Dan is dezelfde link in de waarschuwingsmail ook de knop
                 * om het af te breken.
                 */
                'revert_expires_at' => $nu->copy()->addDays(EmailChange::HERSTELLEN_GELDIG),
            ]);
        });

        Mail::to($nieuw)->send(new InlogadresBevestigenMail(
            naam: $gebruiker->name,
            oudAdres: $wijziging->from_email,
            nieuwAdres: $nieuw,
            link: route('inlogadres.bevestigen', $bevestigPlat),
            geldigeMinuten: EmailChange::BEVESTIGEN_GELDIG,
        ));

        Mail::to($wijziging->from_email)->send(new InlogadresAangevraagdMail(
            naam: $gebruiker->name,
            nieuwAdres: $nieuw,
            afbreekLink: route('inlogadres.terugdraaien', $herstelPlat),
        ));

        /*
         * Het adres mag in het logboek: dit is het account van de eigenaar
         * en geen bezoeker. Het token niet -- dat is de sleutel zelf.
         */
        $logboek->success(SecurityEventType::EmailChangeRequested, $gebruiker, [
            'naar' => $nieuw,
        ]);

        Toast::melding(
            __('Er staat een bevestiging klaar in :adres.', ['adres' => $nieuw]),
            (string) __('Je inlogadres verandert pas als je die link opent. Tot die tijd log je gewoon in met je huidige adres.'),
        );

        return back();
    }

    /**
     * De link uit het nieuwe postvak: hier wisselt het adres.
     *
     * Zonder inloggen, want de link bewijst wat hij moet bewijzen -- dat
     * dit postvak bestaat en bereikbaar is. Wie hij is, is in stap 1 al
     * bewezen.
     */
    public function confirm(string $token, SecurityLogger $logboek): RedirectResponse
    {
        $wijziging = EmailChange::query()
            ->where('confirm_token', EmailChange::hash($token))
            ->first();

        if ($wijziging === null || ! $wijziging->isOpen()) {
            $logboek->failure(SecurityEventType::EmailChangeConfirmed, null, [
                'reden' => $wijziging === null ? 'onbekend' : 'verlopen',
            ]);

            Toast::fout(
                __('Deze link werkt niet meer.'),
                (string) __('Hij is een uur geldig en kan maar één keer worden gebruikt. Vraag de wijziging opnieuw aan.'),
            );

            return to_route('login');
        }

        $gebruiker = $wijziging->user;
        $oud = $wijziging->from_email;
        $nu = Carbon::now();

        /*
         * Tussen de aanvraag en deze klik kan er een ander account op dit
         * adres zijn gezet -- via Beheer → Gebruikers. Zonder deze controle
         * loopt de `save()` hieronder op een unieke index stuk, en dat is
         * een foutpagina op een openbare link. Liever een uitleg.
         */
        $bezet = User::query()
            ->where('email', $wijziging->to_email)
            ->whereKeyNot($gebruiker->getKey())
            ->exists();

        if ($bezet) {
            $logboek->failure(SecurityEventType::EmailChangeConfirmed, $gebruiker, [
                'reden' => 'adres-in-gebruik',
                'naar' => $wijziging->to_email,
            ]);

            Toast::fout(
                __('Dit adres is inmiddels in gebruik.'),
                (string) __('Er hoort een ander account bij. Kies een ander adres en vraag de wijziging opnieuw aan.'),
            );

            return to_route('login');
        }

        DB::transaction(function () use ($wijziging, $gebruiker, $nu): void {
            $gebruiker->forceFill([
                'email' => $wijziging->to_email,
                /*
                 * Het adres is zojuist bewezen door deze link, dus het is
                 * geverifieerd. Op `null` zetten zou de eigenaar meteen
                 * naar een tweede verificatiemail sturen voor hetzelfde
                 * postvak.
                 */
                'email_verified_at' => $nu,
            ])->save();

            $wijziging->forceFill([
                'confirmed_at' => $nu,
                // De weg terug begint nu pas echt te tellen.
                'revert_expires_at' => $nu->copy()->addDays(EmailChange::HERSTELLEN_GELDIG),
            ])->save();
        });

        /*
         * Het oude adres hoort te weten dat het zijn account kwijt is, en
         * hoe het dat ongedaan maakt. Dit is het vangnet waar deze hele
         * stroom om draait.
         *
         * Het herstel-token staat niet meer in platte tekst in de
         * database, dus hij komt uit de eerste mail. Dat betekent: één
         * link, die eerst "afbreken" betekende en nu "terugdraaien". Zie
         * revert().
         */
        Mail::to($oud)->send(new InlogadresGewijzigdMail(
            naam: $gebruiker->name,
            oudAdres: $oud,
            nieuwAdres: $gebruiker->email,
            geldigeDagen: EmailChange::HERSTELLEN_GELDIG,
        ));

        $logboek->success(SecurityEventType::EmailChangeConfirmed, $gebruiker, [
            'van' => $oud,
            'naar' => $gebruiker->email,
        ]);

        Toast::bijgewerkt(
            __('Je inlogadres is nu :adres.', ['adres' => $gebruiker->email]),
            (string) __('Log voortaan in met dat adres.'),
        );

        return to_route('login');
    }

    /**
     * De link uit het oude postvak: afbreken of terugdraaien.
     *
     * **Eén link voor twee gevallen**, en dat is geen gemakzucht. Wie hem
     * opent bedoelt hetzelfde -- "nee, dit wilde ik niet" -- en of de
     * wijziging op dat moment al is doorgevoerd weet hij niet. Twee
     * verschillende links zouden hem dwingen dat zelf uit te zoeken, op
     * precies het moment waarop hij in paniek is.
     *
     * Zonder inloggen, want dit is het vangnet voor de situatie waarin je
     * niet meer binnenkomt.
     */
    public function revert(string $token, SecurityLogger $logboek): RedirectResponse
    {
        $wijziging = EmailChange::query()
            ->where('revert_token', EmailChange::hash($token))
            ->first();

        $bruikbaar = $wijziging !== null
            && $wijziging->reverted_at === null
            && $wijziging->revert_expires_at !== null
            && $wijziging->revert_expires_at->isFuture();

        if (! $bruikbaar) {
            $logboek->failure(SecurityEventType::EmailChangeReverted, null, [
                'reden' => $wijziging === null ? 'onbekend' : 'verlopen',
            ]);

            Toast::fout(
                __('Deze link werkt niet meer.'),
                (string) __('Hij is :dagen dagen geldig. Lukt het niet meer om in te loggen, neem dan contact op met je beheerder.', [
                    'dagen' => EmailChange::HERSTELLEN_GELDIG,
                ]),
            );

            return to_route('login');
        }

        /** @var EmailChange $wijziging */
        $afgebroken = $wijziging->confirmed_at === null;
        $nu = Carbon::now();

        DB::transaction(function () use ($wijziging, $afgebroken, $nu): void {
            if (! $afgebroken) {
                // Doorgevoerd: het oude adres gaat terug op het account.
                $wijziging->user->forceFill([
                    'email' => $wijziging->from_email,
                    'email_verified_at' => $nu,
                ])->save();
            }

            $wijziging->forceFill([
                'reverted_at' => $nu,
                // De bevestigingslink is hiermee dood.
                'expires_at' => $nu,
            ])->save();
        });

        $logboek->success(SecurityEventType::EmailChangeReverted, $wijziging->user, [
            'teruggezet_op' => $wijziging->from_email,
            'afgebroken' => $afgebroken,
        ]);

        Toast::bijgewerkt(
            $afgebroken
                ? __('De wijziging is afgebroken.')
                : __('Je inlogadres staat weer op :adres.', ['adres' => $wijziging->from_email]),
            (string) __('Log in met dat adres. Is dit niet door jou gedaan, wijzig dan meteen je wachtwoord.'),
        );

        return to_route('login');
    }
}
