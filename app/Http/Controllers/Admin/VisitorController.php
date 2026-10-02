<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Bezoek\Bezoekcijfers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De bezoekcijfers van de website.
 *
 * Het enige scherm in het beheergedeelte dat over de **website** gaat en
 * niet over het portaal. Dat het hier staat en niet onder Website is een
 * keuze: de schermen daar zijn er om iets te wijzigen, en dit is er om iets
 * na te kijken -- net als de logboeken ernaast.
 *
 * **Wat er wordt gemeten en wat niet staat in Bezoekteller**, inclusief de
 * reden dat er geen cookiebanner bij hoort. Lees dat voordat je dit scherm
 * uitbreidt: elk cijfer dat je erbij wil, moet daar gemeten kunnen worden
 * zonder iets op het apparaat van de bezoeker op te slaan.
 *
 * Zie docs/architecture/bezoekcijfers.md.
 */
class VisitorController extends Controller
{
    public function index(Request $request, Bezoekcijfers $cijfers): Response
    {
        /*
         * De periode staat in de URL en niet in de sessie. Dan kan de
         * eigenaar een link naar "de laatste negentig dagen" bewaren, en
         * levert vernieuwen hetzelfde scherm op.
         */
        $dagen = (int) $request->integer('dagen', Bezoekcijfers::STANDAARD);

        return Inertia::render('admin/Bezoekers', [
            'cijfers' => $cijfers->overzicht($dagen),
            'perioden' => Bezoekcijfers::PERIODEN,
        ]);
    }
}
