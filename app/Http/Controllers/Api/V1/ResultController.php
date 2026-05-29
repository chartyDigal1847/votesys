<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Election;
use App\Models\ElectionResult;
use App\Services\Elections\ResultComputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultController extends ApiController
{
    public function __construct(
        private readonly ResultComputationService $results,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $access = $this->access($request);
        $access->authorize('results.view');

        $electionId = $request->query('election_id');

        if ($electionId) {
            $election = Election::findOrFail($electionId);
            $live = $this->results->liveSummary($election);
            $stored = ElectionResult::query()
                ->where('election_id', $election->id)
                ->orderBy('position_id')
                ->orderBy('rank')
                ->get();

            return $this->json([
                'election_id' => (int) $electionId,
                'live' => $live,
                'released' => $stored,
            ]);
        }

        return $this->json([
            'data' => ElectionResult::query()->latest('computed_at')->limit(100)->get(),
        ]);
    }
}
