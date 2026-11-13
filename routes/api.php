<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VeterinerApiController;
use App\Http\Controllers\Api\TransferFeeController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/veteriners/bulk', [VeterinerApiController::class, 'store']);
    Route::get('/veteriners', [VeterinerApiController::class, 'index']);
});

Route::post('/transfer/calculate-fee', [TransferFeeController::class, 'calculateFee'])->middleware('throttle:6,1')->name('transfer.calculate-fee');

Route::middleware([\App\Http\Middleware\PartnerApiAuth::class])->prefix('v1/partner')->name('partner.')->group(function () {
    Route::get('/hotels', [\App\Http\Controllers\Api\Partner\PartnerApiController::class, 'hotels'])->name('hotels');
    Route::post('/quote', [\App\Http\Controllers\Api\Partner\PartnerApiController::class, 'quote'])->name('quote');
    Route::post('/bookings', [\App\Http\Controllers\Api\Partner\PartnerApiController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{reference}', [\App\Http\Controllers\Api\Partner\PartnerApiController::class, 'show'])->name('bookings.show');
    Route::delete('/bookings/{reference}', [\App\Http\Controllers\Api\Partner\PartnerApiController::class, 'cancel'])->name('bookings.cancel');
});

Route::post('/v1/iot/telemetry', [\App\Http\Controllers\Api\IotTelemetryController::class, 'store'])->middleware('throttle:60,1')->name('iot.telemetry');
Route::post('/v1/webhooks/{provider}', [\App\Http\Controllers\Api\WebhookController::class, 'receive'])->middleware('throttle:120,1')->name('webhooks.receive');
Route::prefix('v1/mobile')->name('mobile.')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\MobileApiController::class, 'login'])->middleware('throttle:10,1')->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\MobileApiController::class, 'me'])->name('me');
        Route::get('/reservations', [\App\Http\Controllers\Api\MobileApiController::class, 'reservations'])->name('reservations');
        Route::get('/pets', [\App\Http\Controllers\Api\MobileApiController::class, 'pets'])->name('pets');
        Route::post('/logout', [\App\Http\Controllers\Api\MobileApiController::class, 'logout'])->name('logout');
    });
});

Route::prefix('v1/mobile')->name('mobile.')->middleware('auth:sanctum')->group(function () {
    Route::get('/devices', [\App\Http\Controllers\Api\MobileSecurityController::class, 'devices'])->name('devices');
    Route::post('/devices', [\App\Http\Controllers\Api\MobileSecurityController::class, 'registerDevice'])->middleware('throttle:30,1')->name('devices.register');
    Route::delete('/devices/{device}', [\App\Http\Controllers\Api\MobileSecurityController::class, 'revokeDevice'])->name('devices.revoke');
    Route::post('/devices/revoke-others', [\App\Http\Controllers\Api\MobileSecurityController::class, 'revokeOthers'])->middleware('throttle:10,1')->name('devices.revoke-others');
    Route::get('/security/login-history', [\App\Http\Controllers\Api\MobileSecurityController::class, 'loginHistory'])->name('security.login-history');
});

Route::middleware([\App\Http\Middleware\PartnerApiAuth::class])->prefix('v1/partner')->name('partner.')->group(function () {
    Route::get('/settlements', [\App\Http\Controllers\Api\Partner\PartnerSettlementApiController::class, 'index'])->name('settlements');
});

Route::prefix('v1/mobile')->name('mobile.')->middleware('auth:sanctum')->group(function () {
    Route::post('/assistant', [\App\Http\Controllers\Api\AssistantApiController::class, 'answer'])->middleware('throttle:30,1')->name('assistant');
});
