<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ConfessionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public Settings Routes
Route::prefix('settings')->group(function () {
    Route::get('/', [SettingController::class, 'index'])->name('api.settings.index');
    Route::get('/group/{group}', [SettingController::class, 'getByGroup'])->name('api.settings.group');
    Route::get('/{key}', [SettingController::class, 'show'])->name('api.settings.show');
});

// Confession Routes (Protected by auth:sanctum)
Route::middleware('auth:sanctum')->prefix('v1/confessions')->group(function () {
    Route::get('/', [ConfessionController::class, 'index']);
    Route::get('/favorites', [ConfessionController::class, 'favorites']);
    Route::post('/', [ConfessionController::class, 'store']);
    Route::get('/{id}', [ConfessionController::class, 'show']);
    Route::put('/{id}', [ConfessionController::class, 'update']);
    Route::delete('/{id}', [ConfessionController::class, 'destroy']);

    // Actions
    Route::post('/{id}/favorite', [ConfessionController::class, 'toggleFavorite']);
    Route::post('/{id}/reveal-identity', [ConfessionController::class, 'revealIdentity']);
    Route::post('/{id}/like', [ConfessionController::class, 'like']);
    Route::delete('/{id}/like', [ConfessionController::class, 'unlike']);
});
