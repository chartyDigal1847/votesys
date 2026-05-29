<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ElectionStatus;
use App\Http\Resources\ElectionResource;
use App\Models\Election;
use App\Services\Elections\ElectionWorkflowService;
use App\Services\Elections\ResultComputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElectionController extends ApiController
{
    public function __construct(
        private readonly ElectionWorkflowService $workflow,
        private readonly ResultComputationService $results,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->access($request)->authorize('elections.view');

        $query = Election::query()->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $elections = $query->paginate((int) $request->query('per_page', 15));

        return $this->json([
            'data' => ElectionResource::collection($elections),
            'pagination' => [
                'total' => $elections->total(),
                'per_page' => $elections->perPage(),
                'current_page' => $elections->currentPage(),
            ],
        ]);
    }

    public function show(Request $request, Election $election): JsonResponse
    {
        $this->access($request)->authorize('elections.view');

        return $this->json(['data' => new ElectionResource($election)]);
    }

    public function store(Request $request): JsonResponse
    {
        $access = $this->access($request);
        $access->authorize('elections.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $election = $this->workflow->create($data, $access->principal);

        return $this->json(['data' => new ElectionResource($election)], 201);
    }

    public function update(Request $request, Election $election): JsonResponse
    {
        $access = $this->access($request);
        $access->authorize('elections.update');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $election->update($data);

        return $this->json(['data' => new ElectionResource($election->fresh())]);
    }

    public function destroy(Request $request, Election $election): JsonResponse
    {
        $this->access($request)->authorize('elections.delete');
        $election->delete();

        return $this->json(['message' => 'Election archived.']);
    }

    public function transition(Request $request, Election $election): JsonResponse
    {
        $access = $this->access($request);
        $access->authorize('elections.manage_status');

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', config('votesys.election_statuses'))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->workflow->transition(
            $election,
            ElectionStatus::from($data['status']),
            $access->principal,
            $data['notes'] ?? null,
        );

        if ($updated->status === ElectionStatus::ResultProcessing->value) {
            $this->results->compute($updated);
            $this->workflow->transition($updated, ElectionStatus::Completed, $access->principal);
            $updated = $updated->fresh();
        }

        return $this->json(['data' => new ElectionResource($updated)]);
    }
}
