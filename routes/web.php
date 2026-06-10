<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\FencerController;
use App\Http\Controllers\PlanShareController;
use App\Livewire\BudgetTracker;
use App\Livewire\PrepTracker;
use App\Livewire\ResultsTracker;
use App\Livewire\SeasonBuilder;
use Illuminate\Support\Facades\Route;

// Guests get the landing page; signed-in users go straight to their season.
Route::get('/', function () {
    return auth()->check() ? redirect()->route('calendar') : view('landing');
})->name('home');

// Public sample season (the Farren example), with a sign-up invitation.
Route::get('/demo', [CalendarController::class, 'demo'])->name('demo');

// Public read-only season plan + calendar feed (unguessable slug).
Route::get('/p/{slug}.ics', [PlanShareController::class, 'ics'])->name('plan.ics');
Route::get('/p/{slug}', [PlanShareController::class, 'show'])->name('plan.share');

// Single-event calendar download (catalog facts are public).
Route::get('/events/{tournament}.ics', [CalendarController::class, 'ics'])->name('event.ics');

Route::middleware('auth')->group(function () {
    Route::get('/season', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/season/build', SeasonBuilder::class)->name('season.build');
    Route::get('/season/results', ResultsTracker::class)->name('season.results');
    Route::get('/season/budget', BudgetTracker::class)->name('season.budget');
    Route::get('/season/prep', PrepTracker::class)->name('season.prep');

    Route::get('/fencers/create', [FencerController::class, 'create'])->name('fencers.create');
    Route::post('/fencers', [FencerController::class, 'store'])->name('fencers.store');
    Route::get('/fencers/{fencer}/edit', [FencerController::class, 'edit'])->name('fencers.edit');
    Route::put('/fencers/{fencer}', [FencerController::class, 'update'])->name('fencers.update');
    Route::post('/fencers/{fencer}/select', [FencerController::class, 'select'])->name('fencers.select');
});
