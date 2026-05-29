<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CandidateController extends Controller
{
    public function index(): View
    {
        $candidates = Candidate::query()
            ->with(['position.election'])
            ->orderBy('position_id')
            ->orderBy('name')
            ->get();

        return view('candidates.index', compact('candidates'));
    }

    public function create(): View
    {
        $positions = Position::query()
            ->with('election')
            ->orderBy('election_id')
            ->orderBy('id')
            ->get();

        return view('candidates.create', compact('positions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->resolveStaticPosition($request);

        $data = $this->validated($request);
        if ($path = $this->storeProfilePhoto($request)) {
            $data['profile_photo'] = $path;
        }
        // Election officers add candidates directly — auto-approve so students
        // can vote immediately without a separate approval step.
        $access = \App\VoteSys\VoteSysAccess::fromRequest($request);
        $data['status'] = 'approved';
        $data['approved_at'] = now();
        $data['approved_by_external_id'] = $access->principal->id;

        $candidate = Candidate::query()->create($data);

        \App\Models\ActivityLog::record(
            "Candidate '{$candidate->name}' added and auto-approved by election officer",
            'green',
            'candidate.approved',
            null,
            $access->principal->id
        );

        return redirect()
            ->route('votesys', ['panel' => 'candidates'])
            ->with('status', 'Candidate added and approved.');
    }

    public function edit(Candidate $candidate): View
    {
        $positions = Position::query()
            ->with('election')
            ->orderBy('election_id')
            ->orderBy('id')
            ->get();

        return view('candidates.edit', compact('candidate', 'positions'));
    }

    public function update(Request $request, Candidate $candidate): RedirectResponse
    {
        $this->resolveStaticPosition($request);

        $data = $this->validated($request);
        if ($path = $this->storeProfilePhoto($request, $candidate)) {
            $data['profile_photo'] = $path;
        }
        $candidate->update($data);

        return redirect()
            ->route('votesys', ['panel' => 'candidates'])
            ->with('status', 'Candidate updated.');
    }

    public function destroy(Candidate $candidate): RedirectResponse
    {
        if ($candidate->profile_photo) {
            Storage::disk('public')->delete($candidate->profile_photo);
        }
        $candidate->delete();

        return redirect()
            ->route('votesys', ['panel' => 'candidates'])
            ->with('status', 'Candidate removed.');
    }

    /**
     * @return array{position_id: int, name: string, party: ?string, course: ?string, bio: ?string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'name' => ['required', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
        ]);
    }

    private function storeProfilePhoto(Request $request, ?Candidate $existing = null): ?string
    {
        if (! $request->hasFile('profile_photo')) {
            return null;
        }

        $path = $request->file('profile_photo')->store('candidates', 'public');

        if ($existing?->profile_photo && $existing->profile_photo !== $path) {
            Storage::disk('public')->delete($existing->profile_photo);
        }

        return $path;
    }

    private function resolveStaticPosition(Request $request): void
    {
        $positionInput = $request->input('position_id');
        if ($positionInput && !is_numeric($positionInput)) {
            $positionName = str_starts_with($positionInput, 'static:')
                ? substr($positionInput, 7)
                : $positionInput;

            $election = \App\Models\Election::query()
                ->where('is_active', true)
                ->latest()
                ->first()
                ?? \App\Models\Election::query()->latest()->first();

            if (! $election) {
                $election = \App\Models\Election::query()->create([
                    'name' => 'General Student Council Election '.date('Y'),
                    'is_active' => true,
                    'starts_at' => now(),
                    'ends_at' => now()->addDays(7),
                ]);
            }

            $position = \App\Models\Position::query()->firstOrCreate([
                'election_id' => $election->id,
                'name' => $positionName,
            ], [
                'max_selections' => 1,
            ]);

            $request->merge(['position_id' => $position->id]);
        }
    }
}
