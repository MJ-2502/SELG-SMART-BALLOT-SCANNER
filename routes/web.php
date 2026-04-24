<?php

use App\Http\Controllers\CandidateController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\BallotLayoutController;
use App\Http\Controllers\BallotManagementController;
use App\Http\Controllers\ElectionController;
use App\Http\Controllers\ElectionProgressController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
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
    Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    Route::resource('elections', ElectionController::class)->except(['show']);
    Route::post('elections/{election}/start', [ElectionController::class, 'start'])->name('elections.start');
    Route::post('elections/{election}/stop', [ElectionController::class, 'stop'])->name('elections.stop');

    Route::resource('facilitators', UserController::class)->except(['show']);
    Route::resource('positions', PositionController::class)->except(['show']);
    Route::get('candidates/partylist/create', [CandidateController::class, 'createPartylist'])->name('candidates.partylist.create');
    Route::post('candidates/partylist', [CandidateController::class, 'storePartylist'])->name('candidates.partylist.store');
    Route::delete('candidates/partylist', [CandidateController::class, 'destroyPartylist'])->name('candidates.partylist.destroy');
    Route::resource('candidates', CandidateController::class)->except(['show']);

    Route::get('/admin/ballot-generator', [BallotLayoutController::class, 'index'])->name('admin.ballot-generator.index');
    Route::post('/admin/ballot-generator/generate', [BallotLayoutController::class, 'generate'])->name('admin.ballot-generator.generate');
    Route::get('/admin/ballot-generator/print', [BallotLayoutController::class, 'print'])->name('admin.ballot-generator.print');

    Route::get('/admin/ballot-management', [BallotManagementController::class, 'index'])->name('admin.ballot-management.index');
    Route::delete('/admin/ballot-management/{ballot}', [BallotManagementController::class, 'destroy'])->name('admin.ballot-management.destroy');
    Route::get('/admin/progress', [ElectionProgressController::class, 'index'])->name('admin.progress');
    Route::get('/admin/reports', [ReportController::class, 'index'])->name('admin.reports.index');
    Route::post('/admin/reports/generate', [ReportController::class, 'store'])->name('admin.reports.store');
    Route::get('/admin/reports/{report}', [ReportController::class, 'show'])->name('admin.reports.show');
    Route::patch('elections/{election}/facilitators', [ElectionController::class, 'assignFacilitators'])->name('elections.facilitators.assign');
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
