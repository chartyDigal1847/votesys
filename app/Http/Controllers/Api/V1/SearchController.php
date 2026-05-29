<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Candidate;
use App\Models\Election;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->access($request)->authorize('search.use');

        $q = trim((string) $request->query('q', ''));

        $elections = Election::query()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->limit(10)
            ->get(['id', 'name', 'status']);

        $candidates = Candidate::query()
            ->with('position:id,name')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('party', 'like', "%{$q}%")
                        ->orWhere('course', 'like', "%{$q}%");
                });
            })
            ->where('status', 'approved')
            ->limit(20)
            ->get();

        return $this->json([
            'query' => $q,
            'elections' => $elections,
            'candidates' => $candidates,
        ]);
    }
}
