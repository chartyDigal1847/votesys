<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VoteSysApiController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\SsoController;

/*
|--------------------------------------------------------------------------
| VoteSys Routes
|--------------------------------------------------------------------------
| No auth middleware — role is resolved from the centralized module bridge.
| SSO identity flow: portal → module-bridge.js → portal /api/sso/exchange
|                    → window.PORTAL_USER/module:ready → bootApp()
|--------------------------------------------------------------------------
*/

// ── Root route — ALWAYS returns 200, works standalone AND with ?embedded=1
Route::get('/', fn () => view('votesys'))->name('votesys');
Route::post('/sso/exchange', [SsoController::class, 'exchange'])->name('sso.exchange');

// ── VoteSys JSON API — prefixed to avoid collisions on the portal
Route::prefix('votesys/api')->middleware('votesys.principal')->group(function () {
    Route::get('/bootstrap', [VoteSysApiController::class, 'bootstrap']);
    Route::post('/vote', [VoteSysApiController::class, 'submitVote']);
    Route::post('/election/end', [VoteSysApiController::class, 'endElection']);
    Route::delete('/candidates/{candidate}', [VoteSysApiController::class, 'deleteCandidate']);
});

// ── Candidate management (admin blade forms — standalone/admin use)
Route::middleware('votesys.permission:candidates.manage')->group(function () {
    Route::get('/votesys/candidates', [CandidateController::class, 'index'])->name('candidates.index');
    Route::get('/votesys/candidates/create', [CandidateController::class, 'create'])->name('candidates.create');
    Route::post('/votesys/candidates', [CandidateController::class, 'store'])->name('candidates.store');
    Route::get('/votesys/candidates/{candidate}/edit', [CandidateController::class, 'edit'])->name('candidates.edit');
    Route::put('/votesys/candidates/{candidate}', [CandidateController::class, 'update'])->name('candidates.update');
    Route::delete('/votesys/candidates/{candidate}', [CandidateController::class, 'destroy'])->name('candidates.destroy');
});

