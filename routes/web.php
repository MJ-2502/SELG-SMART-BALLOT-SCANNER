<?php

use App\Http\Controllers\CandidateController;
use App\Http\Controllers\PositionController;
use App\Http\Middleware\IsAdviser;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

Route::middleware([IsAdviser::class])->group(function () {
    Route::get('/admin', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::resource('positions', PositionController::class)->except(['show']);
    Route::resource('candidates', CandidateController::class)->except(['show']);
});
