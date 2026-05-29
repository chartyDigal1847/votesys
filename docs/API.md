# VoteSys REST API v1

Base URL: `{APP_URL}/api/v1`

All endpoints require DEORIS SSO identity headers (or an active session principal):

| Header | Description |
|--------|-------------|
| `X-VoteSys-Role` | Role string: `admin`, `election_officer`, `student`, `candidate` |
| `X-VoteSys-User-Id` | External user ID from the DEORIS portal |
| `X-VoteSys-User-Email` | User email |
| `X-VoteSys-User-Name` | Display name |

All responses include a `meta` envelope:
```json
{ "meta": { "service": "votesys-service", "api_version": "v1" }, "data": ... }
```

---

## Elections

| Method | Path | Permission |
|--------|------|------------|
| GET | `/elections` | `elections.view` |
| GET | `/elections/{id}` | `elections.view` |
| POST | `/elections` | `elections.create` |
| PUT | `/elections/{id}` | `elections.update` |
| DELETE | `/elections/{id}` | `elections.delete` |
| POST | `/elections/{id}/transition` | `elections.manage_status` |

### Transition body
```json
{ "status": "voting_open", "notes": "Optional note" }
```
Valid statuses: `draft → candidate_registration → candidate_review → approved → voting_open → voting_closed → result_processing → completed → archived`

---

## Election Officers

| Method | Path | Permission |
|--------|------|------------|
| GET | `/elections/{id}/officers` | `elections.manage_status` |
| POST | `/elections/{id}/officers` | `elections.manage_status` |
| DELETE | `/elections/{id}/officers/{officer}` | `elections.manage_status` |

### POST body
```json
{ "external_id": "user-123", "email": "officer@school.edu", "name": "Jane Doe" }
```

---

## Candidates

| Method | Path | Permission |
|--------|------|------------|
| GET | `/candidates` | `candidates.view` |
| POST | `/candidates/apply` | `candidates.apply` |
| POST | `/candidates/{id}/approve` | `candidates.approve` |
| POST | `/candidates/{id}/reject` | `candidates.approve` |
| DELETE | `/candidates/{id}` | `candidates.delete` |

### Reject body
```json
{ "reason": "Does not meet GPA requirement." }
```

---

## Candidate Profiles

| Method | Path | Permission |
|--------|------|------------|
| GET | `/candidates/{id}/profile` | `candidates.view` |
| PUT | `/candidates/{id}/profile` | `candidates.update` |

### PUT body
```json
{ "tagline": "For a better tomorrow", "platform": "...", "campaign_links": ["https://..."] }
```

---

## Voting

| Method | Path | Permission | Rate Limit |
|--------|------|------------|------------|
| POST | `/vote` | `vote.cast` | 10 / min per voter |

### POST body
```json
{
  "student_id": "STU-2026-001",
  "election_id": 1,
  "selections": { "1": 3, "2": 7 }
}
```
`selections` is a map of `position_id → candidate_id`.

---

## Results

| Method | Path | Permission |
|--------|------|------------|
| GET | `/results?election_id={id}` | `results.view` |

Returns both live counts and stored `election_results` rows.

---

## Analytics

| Method | Path | Permission |
|--------|------|------------|
| GET | `/analytics` | `analytics.view` |

Queries `v_voter_turnout` and `v_election_vote_summary` MySQL views.

---

## Federated Search

| Method | Path | Permission |
|--------|------|------------|
| GET | `/search?q={query}` | `search.use` |

Searches elections and approved candidates.

---

## Notifications

| Method | Path | Permission |
|--------|------|------------|
| GET | `/notifications` | `notifications.view` |
| POST | `/notifications/{id}/read` | `notifications.view` |
| POST | `/notifications/read-all` | `notifications.view` |

---

## Legacy UI API (embedded frontend)

These routes are used by `votesys.js` and are equivalent in security to the versioned API.

| Method | Path | Notes |
|--------|------|-------|
| GET | `/votesys/api/bootstrap` | Returns election, positions, results, candidates, activity log |
| POST | `/votesys/api/vote` | Delegates to `VoteSubmissionService` — full security |
| DELETE | `/votesys/api/candidates/{id}` | Requires `candidates.delete` permission |

---

## WebSocket (Laravel Reverb)

Subscribe to the public channel `election.{id}` to receive live vote updates:

```js
window.Echo.channel(`election.${electionId}`)
    .listen(".vote.cast", (data) => {
        // data: { election_id, position_id, candidate_id, new_vote_count }
    });
```

---

## Error responses

| Status | Meaning |
|--------|---------|
| 401 | No valid principal resolved |
| 403 | Permission denied |
| 422 | Validation failed |
| 429 | Rate limit exceeded |
| 500 | Server error |
