<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Het dashboard van het portaal.
 *
 * De begroeting na het inloggen zat hier eerst, maar die hoort bij elke
 * portaalpagina en niet alleen bij deze: wie met een onthouden bestemming
 * binnenkomt landt ergens anders en werd dan niet begroet. Hij wordt nu
 * gedeeld vanuit HandleInertiaRequests.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard');
    }
}
