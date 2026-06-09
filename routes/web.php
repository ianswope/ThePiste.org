<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\FencerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CalendarController::class, 'index'])->name('calendar');

Route::middleware('auth')->group(function () {
    Route::get('/fencers/create', [FencerController::class, 'create'])->name('fencers.create');
    Route::post('/fencers', [FencerController::class, 'store'])->name('fencers.store');
    Route::get('/fencers/{fencer}/edit', [FencerController::class, 'edit'])->name('fencers.edit');
    Route::put('/fencers/{fencer}', [FencerController::class, 'update'])->name('fencers.update');
    Route::post('/fencers/{fencer}/select', [FencerController::class, 'select'])->name('fencers.select');
});
