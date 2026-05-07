<?php
// routes/api.php
// Reseller REST API — no session auth, uses API key instead

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api-key')->group(function () {
    Route::get('services', [ApiController::class, 'services']);
    Route::post('order',   [ApiController::class, 'placeOrder']);
    Route::get('status',   [ApiController::class, 'orderStatus']);
    Route::get('balance',  [ApiController::class, 'balance']);
    Route::post('refill',  [ApiController::class, 'refill']);
    Route::post('cancel',  [ApiController::class, 'cancel']);
});
