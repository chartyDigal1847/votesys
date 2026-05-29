@php
    $c = $candidate ?? null;
@endphp

<div class="form-group" style="margin-bottom: 18px;">
    <label class="form-label" for="position_id">Position</label>
    <select class="form-input" id="position_id" name="position_id" required>
        <option value="">-- Select position --</option>
        @if (!$positions->isEmpty())
            @foreach ($positions->groupBy('election.name') as $electionName => $electionPositions)
                <optgroup label="Election: {{ $electionName }}">
                    @foreach ($electionPositions as $p)
                        <option value="{{ $p->id }}" @selected(old('position_id', $c?->position_id) == $p->id)>
                            {{ $p->name }} &middot; select up to {{ $p->max_selections }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        @endif
        <optgroup label="Static Positions (Will be auto-created if needed)">
            @foreach (['President', 'Vice President', 'Secretary', 'Treasurer', 'Auditor', 'Public Information Officer', 'Protocol Officer'] as $sp)
                @php
                    $isCurrent = $c && $c->position && $c->position->name === $sp;
                @endphp
                <option value="static:{{ $sp }}" @selected(old('position_id') === "static:{$sp}" || $isCurrent)>
                    {{ $sp }}
                </option>
            @endforeach
        </optgroup>
    </select>
    <p class="field-help">Election officers can add candidates to any listed election position or select a standard static position.</p>

    @error('position_id')
        <div style="margin-top:8px; color:#b91c1c; font-size:0.9rem;">{{ $message }}</div>
    @enderror
</div>

<div class="form-group" style="margin-bottom: 18px;">
    <label class="form-label" for="name">Full name</label>
    <input class="form-input" type="text" id="name" name="name" value="{{ old('name', $c?->name) }}" required maxlength="255">
    @error('name')
        <div style="margin-top:8px; color:#b91c1c; font-size:0.9rem;">{{ $message }}</div>
    @enderror
</div>

<div class="form-group" style="margin-bottom: 18px;">
    <label class="form-label" for="profile_photo">Profile photo</label>
    <div style="display:flex; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:10px;">
        <img id="candidatePhotoPreview" src="{{ $c ? $c->profile_photo_url : asset('images/candidate-placeholder.svg') }}" alt="" width="176" height="220" style="width:176px;height:220px;object-fit:cover;object-position:center top;border-radius:12px 12px 8px 8px;border:1px solid var(--border-light, #e8e0d4);background:#e4e6eb;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
        <div style="flex:1; min-width:200px;">
            <input class="form-input" type="file" id="profile_photo" name="profile_photo" accept="image/*" onchange="window.previewCandidatePhoto && window.previewCandidatePhoto(this)">
            <p style="margin:8px 0 0; font-size:0.8rem; color:var(--text-secondary, #6b5b50);">Optional. JPEG, PNG, GIF, or WebP; max 2 MB.</p>
        </div>
    </div>
    @error('profile_photo')
        <div style="margin-top:8px; color:#b91c1c; font-size:0.9rem;">{{ $message }}</div>
    @enderror
</div>

<div class="form-group" style="margin-bottom: 18px;">
    <label class="form-label" for="party">Party / slate</label>
    <input class="form-input" type="text" id="party" name="party" value="{{ old('party', $c?->party) }}" maxlength="255">
    @error('party')
        <div style="margin-top:8px; color:#b91c1c; font-size:0.9rem;">{{ $message }}</div>
    @enderror
</div>

<div class="form-group" style="margin-bottom: 18px;">
    <label class="form-label" for="course">Course</label>
    <input class="form-input" type="text" id="course" name="course" value="{{ old('course', $c?->course) }}" maxlength="255">
    @error('course')
        <div style="margin-top:8px; color:#b91c1c; font-size:0.9rem;">{{ $message }}</div>
    @enderror
</div>

<div class="form-group" style="margin-bottom: 18px;">
    <label class="form-label" for="bio">Bio</label>
    <textarea class="form-input" id="bio" name="bio" rows="4" style="min-height:100px; resize:vertical;">{{ old('bio', $c?->bio) }}</textarea>
    @error('bio')
        <div style="margin-top:8px; color:#b91c1c; font-size:0.9rem;">{{ $message }}</div>
    @enderror
</div>
