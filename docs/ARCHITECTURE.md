# VoteSys SOA Architecture

VoteSys is an independent Laravel 12 microservice in the DEORIS ecosystem.

## Service identity

| Setting | Config key / env |
|---------|------------------|
| Service name | `VOTESYS_SERVICE_NAME` |
| Service key | `VOTESYS_SERVICE_KEY` (`votesys-service`) |
| API version | `VOTESYS_API_VERSION` (`v1`) |
| Portal URL | `APP_PORTAL_URL` |
| Event signing | `VOTESYS_EVENT_SECRET` |

## Boundaries

- Owns MySQL schema (`votesys_db`) — no cross-service DB access
- REST API: `/api/v1/*`
- Legacy UI API: `/votesys/api/*` (bootstrap, vote)
- Events published to `event_outbox` with HMAC-SHA256 signatures

## Roles

| Role | Purpose |
|------|---------|
| `admin` | Full election lifecycle |
| `election_officer` | Approve candidates, manage elections, monitor |
| `student` | Cast vote, view results |
| `candidate` | Apply for candidacy |

Legacy portal role `hr` maps to `election_officer`.

## Election workflow

`draft` → `candidate_registration` → `candidate_review` → `approved` → `voting_open` → `voting_closed` → `result_processing` → `completed` → `archived`

Transition via: `POST /api/v1/elections/{id}/transition`

## Security

- One vote per voter per position (unique constraint + locked ballots)
- Vote SHA-256 hashes stored on `votes.vote_hash`
- Immutable audit in `vote_logs`
- RBAC middleware on all routes
- Rate limit: 120 req/min per voter IP/id

## Queues (Redis)

Configure `QUEUE_CONNECTION=redis` and workers for:

- `votesys-elections`
- `votesys-voting`
- `votesys-notifications`
- `votesys-analytics`
- `votesys-events`

## DEORIS integration

1. Portal embeds VoteSys iframe
2. `module-bridge.js` exchanges SSO token
3. VoteSys stores principal in session from `X-VoteSys-*` headers
4. Events written to outbox for Event Hub consumption
