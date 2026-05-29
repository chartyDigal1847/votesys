<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\CandidateApiController;
use App\Http\Controllers\Api\V1\CandidateProfileController;
use App\Http\Controllers\Api\V1\ElectionController;
use App\Http\Controllers\Api\V1\ElectionOfficerController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ResultController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\VoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VoteSys SOA REST API  (versioned, independent service boundary)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')
    ->middleware(['api', 'votesys.principal', 'throttle:api'])
    ->group(function () {

        // ── Elections ─────────────────────────────────────────────────────
        Route::get('/elections', [ElectionController::class, 'index'])
            ->middleware('votesys.permission:elections.view');
        Route::get('/elections/{election}', [ElectionController::class, 'show'])
            ->middleware('votesys.permission:elections.view');
        Route::post('/elections', [ElectionController::class, 'store'])
            ->middleware('votesys.permission:elections.create');
        Route::put('/elections/{election}', [ElectionController::class, 'update'])
            ->middleware('votesys.permission:elections.update');
        Route::delete('/elections/{election}', [ElectionController::class, 'destroy'])
            ->middleware('votesys.permission:elections.delete');
        Route::post('/elections/{election}/transition', [ElectionController::class, 'transition'])
            ->middleware('votesys.permission:elections.manage_status');

        // ── Election Officers ─────────────────────────────────────────────
        Route::get('/elections/{election}/officers', [ElectionOfficerController::class, 'index'])
            ->middleware('votesys.permission:elections.manage_status');
        Route::post('/elections/{election}/officers', [ElectionOfficerController::class, 'store'])
            ->middleware('votesys.permission:elections.manage_status');
        Route::delete('/elections/{election}/officers/{officer}', [ElectionOfficerController::class, 'destroy'])
            ->middleware('votesys.permission:elections.manage_status');

        // ── Candidates ────────────────────────────────────────────────────
        Route::get('/candidates', [CandidateApiController::class, 'index'])
            ->middleware('votesys.permission:candidates.view');
        Route::post('/candidates/apply', [CandidateApiController::class, 'apply'])
            ->middleware('votesys.permission:candidates.apply');
        Route::post('/candidates/{candidate}/approve', [CandidateApiController::class, 'approve'])
            ->middleware('votesys.permission:candidates.approve');
        Route::post('/candidates/{candidate}/reject', [CandidateApiController::class, 'reject'])
            ->middleware('votesys.permission:candidates.approve');
        Route::delete('/candidates/{candidate}', [CandidateApiController::class, 'destroy'])
            ->middleware('votesys.permission:candidates.delete');

        // ── Candidate Profiles ────────────────────────────────────────────
        Route::get('/candidates/{candidate}/profile', [CandidateProfileController::class, 'show'])
            ->middleware('votesys.permission:candidates.view');
        Route::put('/candidates/{candidate}/profile', [CandidateProfileController::class, 'upsert'])
            ->middleware('votesys.permission:candidates.update');

        // ── Voting — tighter per-voter rate limit (10 attempts / minute) ──
        Route::post('/vote', [VoteController::class, 'store'])
            ->middleware(['votesys.permission:vote.cast', 'throttle:voting']);

        // ── Results ───────────────────────────────────────────────────────
        Route::get('/results', [ResultController::class, 'index'])
            ->middleware('votesys.permission:results.view');

        // ── Analytics ─────────────────────────────────────────────────────
        Route::get('/analytics', [AnalyticsController::class, 'index'])
            ->middleware('votesys.permission:analytics.view');

        // ── Federated Search ──────────────────────────────────────────────
        Route::get('/search', [SearchController::class, 'index'])
            ->middleware('votesys.permission:search.use');

        // ── Notifications ─────────────────────────────────────────────────
        Route::get('/notifications', [NotificationController::class, 'index'])
            ->middleware('votesys.permission:notifications.view');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
            ->middleware('votesys.permission:notifications.view');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
            ->middleware('votesys.permission:notifications.view');
    });
