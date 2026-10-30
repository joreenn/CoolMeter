<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CoolingController;
use App\Http\Controllers\JournalAuthController;
use App\Http\Controllers\HouseholdController;
use App\Http\Middleware\RequireJournalLogin;

Route::get('/login', [JournalAuthController::class, 'loginForm'])->name('login');
Route::post('/login', [JournalAuthController::class, 'login'])->middleware('throttle:journal-login');
Route::post('/logout', [JournalAuthController::class, 'logout'])->name('logout');
Route::get('/setup/{token}', [JournalAuthController::class, 'setupForm'])->where('token', '[A-Za-z0-9_-]{40,}');
Route::post('/setup/{token}', [JournalAuthController::class, 'setup'])->where('token', '[A-Za-z0-9_-]{40,}')->middleware('throttle:journal-setup');
Route::get('/join/{token}', [HouseholdController::class, 'joinForm'])->where('token', '[A-Za-z0-9]{64}')->name('household.join');
Route::post('/join/{token}', [HouseholdController::class, 'join'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:journal-join');

Route::middleware(RequireJournalLogin::class)->group(function () {
Route::get('/', [CoolingController::class, 'dashboard']);
Route::prefix('api/household')->controller(HouseholdController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('invites', 'invite')->middleware('throttle:household-manage');
    Route::delete('invites/{invite}', 'revokeInvite')->middleware('throttle:household-manage');
    Route::delete('members/{user}', 'removeMember')->middleware('throttle:household-manage');
});
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
