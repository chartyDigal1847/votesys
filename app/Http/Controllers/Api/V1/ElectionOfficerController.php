<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Election;
use App\Models\ElectionOfficer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElectionOfficerController extends ApiController
{
    public function index(Request $request, Election $election): JsonResponse
    {
        $this->access($request)->authorize('elections.manage_status');

        return $this->json([
            'data' => $election->officers()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Election $election): JsonResponse
    {
        $this->access($request)->authorize('elections.manage_status');

        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:64'],
            'email'       => ['nullable', 'email', 'max:255'],
            'name'        => ['nullable', 'string', 'max:255'],
        ]);

        $officer = ElectionOfficer::updateOrCreate(
            ['election_id' => $election->id, 'external_id' => $data['external_id']],
            ['email' => $data['email'] ?? null, 'name' => $data['name'] ?? null],
        );

        return $this->json(['data' => $officer], 201);
    }

    public function destroy(Request $request, Election $election, ElectionOfficer $officer): JsonResponse
    {
        $this->access($request)->authorize('elections.manage_status');

        abort_if($officer->election_id !== $election->id, 404);

        $officer->delete();

        return $this->json(['message' => 'Officer removed from election.']);
    }
}
