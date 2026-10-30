<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CoolingController;
use App\Http\Controllers\JournalAuthController;
use App\Http\Middleware\RequireJournalLogin;

Route::get('/login', [JournalAuthController::class, 'loginForm'])->name('login');
Route::post('/login', [JournalAuthController::class, 'login'])->middleware('throttle:journal-login');
Route::post('/logout', [JournalAuthController::class, 'logout'])->name('logout');
Route::get('/setup/{token}', [JournalAuthController::class, 'setupForm'])->where('token', '[A-Za-z0-9_-]{40,}');
Route::post('/setup/{token}', [JournalAuthController::class, 'setup'])->where('token', '[A-Za-z0-9_-]{40,}')->middleware('throttle:journal-setup');

Route::middleware(RequireJournalLogin::class)->group(function () {
Route::view('/', 'dashboard');
Route::prefix('api')->controller(CoolingController::class)->group(function () {
    Route::get('state', 'state');
    Route::post('estimate', 'estimate');
    Route::post('sessions', 'store');
    Route::post('sessions/start', 'start');
    Route::put('sessions/{session}', 'update');
    Route::put('sessions/{session}/eco', 'eco');
    Route::post('sessions/{session}/stop', 'stop');
    Route::delete('sessions/{session}', 'destroy');
    Route::put('settings', 'settings');
    Route::get('export', 'export');
});
});
