<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use App\Models\Candidate;
use App\Services\Deoris\EventPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateApiController extends ApiController
{
    public function __construct(
        private readonly EventPublisher $events,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->access($request)->authorize('candidates.view');

        $query = Candidate::query()->with(['position.election']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->json([
            'data' => $query->orderBy('name')->paginate((int) $request->query('per_page', 20)),
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $access = $this->access($request);
        $access->authorize('candidates.apply');

        $data = $request->validate([
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'name' => ['required', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
        ]);

        $candidate = Candidate::query()->create([
            ...$data,
            'status' => 'pending',
            'applicant_external_id' => $access->principal->id,
        ]);

        ActivityLog::record('Candidacy application submitted', 'blue', 'candidate.applied', null, $access->principal->id);
        $this->events->publish('CandidateApplied', ['candidate_id' => $candidate->id]);

        return $this->json(['data' => $candidate], 201);
    }

    public function approve(Request $request, Candidate $candidate): JsonResponse
    {
        $access = $this->access($request);
        $access->authorize('candidates.approve');

        $candidate->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by_external_id' => $access->principal->id,
            'rejection_reason' => null,
        ]);

        ActivityLog::record("Candidate '{$candidate->name}' approved", 'green', 'candidate.approved', null, $access->principal->id);
        $this->events->publish('CandidateApproved', ['candidate_id' => $candidate->id]);

        return $this->json(['data' => $candidate->fresh()]);
    }

    public function reject(Request $request, Candidate $candidate): JsonResponse
    {
        $access = $this->access($request);
        $access->authorize('candidates.approve');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $candidate->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'],
        ]);

        ActivityLog::record("Candidate '{$candidate->name}' rejected", 'red', 'candidate.rejected', null, $access->principal->id);

        return $this->json(['data' => $candidate->fresh()]);
    }

    public function destroy(Request $request, Candidate $candidate): JsonResponse
    {
        $this->access($request)->authorize('candidates.delete');

        $name = $candidate->name;
        $candidate->delete();

        ActivityLog::record("Candidate '{$name}' deleted", 'red', 'candidate.deleted', null, $this->access($request)->principal->id);

        return $this->json(['message' => 'Candidate removed.']);
    }
}
