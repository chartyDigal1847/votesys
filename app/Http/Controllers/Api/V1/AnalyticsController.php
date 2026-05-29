<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->access($request)->authorize('analytics.view');

        if (DB::connection()->getDriverName() === 'mysql') {
            $turnout = DB::table('v_voter_turnout')->get();
            $summary = DB::table('v_election_vote_summary')->limit(200)->get();
        } else {
            $turnout = [];
            $summary = [];
        }

        return $this->json([
            'turnout' => $turnout,
            'vote_summary' => $summary,
        ]);
    }
}
