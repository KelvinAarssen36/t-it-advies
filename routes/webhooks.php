<?php

use App\Http\Controllers\Webhooks\ResendWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooks van externe diensten
|--------------------------------------------------------------------------
|
| Deze routes worden aangeroepen door servers, niet door browsers. Ze zijn
| daarom uitgezonderd van CSRF (zie bootstrap/app.php) en beveiligen zichzelf
| met een handtekeningcontrole. Rate limiting blijft er wel op staan.
|
*/

Route::post('webhooks/resend', ResendWebhookController::class)
    ->middleware('throttle:webhook')
    ->name('webhooks.resend');
