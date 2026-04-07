<?php

use App\Http\Controllers\CandidateController;
use App\Http\Controllers\BallotLayoutController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\IsAdviser;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    if (auth()->user()?->isAdviser()) {
        return redirect()->route('admin.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', IsAdviser::class])->group(function () {
    Route::get('/admin', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('positions', PositionController::class)->except(['show']);
    Route::get('candidates/partylist/create', [CandidateController::class, 'createPartylist'])->name('candidates.partylist.create');
    Route::post('candidates/partylist', [CandidateController::class, 'storePartylist'])->name('candidates.partylist.store');
    Route::resource('candidates', CandidateController::class)->except(['show']);

    Route::get('/admin/ballot-layout', [BallotLayoutController::class, 'index'])->name('admin.ballot-layout.index');
    Route::post('/admin/ballot-layout/generate', [BallotLayoutController::class, 'generate'])->name('admin.ballot-layout.generate');
    Route::get('/admin/ballot-layout/print', [BallotLayoutController::class, 'print'])->name('admin.ballot-layout.print');
});

Route::middleware('auth')->group(function () {
    Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner.index');
    Route::post('/scanner/scan', [ScannerController::class, 'scan'])->name('scanner.scan');
    Route::post('/scanner/submit', [ScannerController::class, 'submit'])->name('scanner.submit');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
