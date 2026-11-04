<?php

use App\Http\Controllers\Api\V1\GalleryIndexController;
use App\Http\Controllers\Api\V1\ManifestController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PhotoDeleteController;
use App\Http\Controllers\Api\V1\PhotoUploadController;
use App\Http\Controllers\Api\V1\TokenExchangeController;
use App\Http\Controllers\Api\V1\TokenRevokeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| External API v1
|--------------------------------------------------------------------------
|
| Generic contract for external clients (Picstome Sync desktop today, mobile
| apps, scripts tomorrow). Authentication uses Sanctum personal access tokens
| obtained through the browser `/authorize` flow.
|
*/

Route::prefix('v1')->group(function () {
    Route::post('/tokens/exchange', TokenExchangeController::class)
        ->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/tokens/revoke', TokenRevokeController::class);
        Route::get('/me', MeController::class);
        Route::get('/galleries', GalleryIndexController::class);
        Route::get('/manifest', ManifestController::class);
        Route::post('/photos', PhotoUploadController::class);
        Route::delete('/photos', PhotoDeleteController::class)
            ->middleware('throttle:30,1');
    });
});
