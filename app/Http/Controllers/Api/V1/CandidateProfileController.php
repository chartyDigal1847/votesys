<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Candidate;
use App\Models\CandidateProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateProfileController extends ApiController
{
    public function show(Request $request, Candidate $candidate): JsonResponse
    {
        $this->access($request)->authorize('candidates.view');

        return $this->json([
            'data' => $candidate->profile ?? new CandidateProfile(['candidate_id' => $candidate->id]),
        ]);
    }

    public function upsert(Request $request, Candidate $candidate): JsonResponse
    {
        $access = $this->access($request);
        $access->authorize('candidates.update');

        if ($access->principal->role->canonical() === 'student'
            && $candidate->applicant_external_id !== $access->principal->id) {
            abort(403, 'Students can update only their own candidate profile.');
        }

        $data = $request->validate([
            'tagline'        => ['nullable', 'string', 'max:255'],
            'platform'       => ['nullable', 'string'],
            'campaign_links' => ['nullable', 'array'],
            'campaign_links.*' => ['url', 'max:512'],
        ]);

        $profile = CandidateProfile::updateOrCreate(
            ['candidate_id' => $candidate->id],
            $data,
        );

        return $this->json(['data' => $profile]);
    }
}
