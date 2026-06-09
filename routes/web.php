<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\FencerController;
use App\Livewire\SeasonBuilder;
use Illuminate\Support\Facades\Route;

// Guests get the landing page; signed-in users go straight to their season.
Route::get('/', function () {
    return auth()->check() ? redirect()->route('calendar') : view('landing');
})->name('home');

// Public sample season (the Farren example), with a sign-up invitation.
Route::get('/demo', [CalendarController::class, 'demo'])->name('demo');

Route::middleware('auth')->group(function () {
    Route::get('/season', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/season/build', SeasonBuilder::class)->name('season.build');

    Route::get('/fencers/create', [FencerController::class, 'create'])->name('fencers.create');
    Route::post('/fencers', [FencerController::class, 'store'])->name('fencers.store');
    Route::get('/fencers/{fencer}/edit', [FencerController::class, 'edit'])->name('fencers.edit');
    Route::put('/fencers/{fencer}', [FencerController::class, 'update'])->name('fencers.update');
    Route::post('/fencers/{fencer}/select', [FencerController::class, 'select'])->name('fencers.select');
});
