<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Voting\VoteSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoteController extends ApiController
{
    public function __construct(
        private readonly VoteSubmissionService $votes,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $access = $this->access($request);
        $access->authorize('vote.cast');

        $data = $request->validate([
            'student_id' => ['nullable', 'string', 'min:1', 'max:64'],
            'election_id' => ['required', 'integer', 'exists:elections,id'],
            'selections' => ['required', 'array'],
            'selections.*' => ['nullable', 'integer', 'exists:candidates,id'],
        ]);

        $this->votes->submit($data, $access->principal, $request);

        return $this->json(['message' => 'Your vote has been recorded.']);
    }
}
