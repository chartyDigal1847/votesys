<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SOA service identity
    |--------------------------------------------------------------------------
    */

    'service_name' => env('VOTESYS_SERVICE_NAME', 'VoteSys'),
    'service_key' => env('VOTESYS_SERVICE_KEY', 'votesys-service'),
    'service_url' => env('VOTESYS_SERVICE_URL', env('APP_URL', 'http://localhost')),
    'api_version' => env('VOTESYS_API_VERSION', 'v1'),
    'trusted_portal_url' => env('APP_PORTAL_URL', 'https://deoris.test'),
    'auth_service_url' => env('AUTH_SERVICE_URL', env('APP_PORTAL_URL', 'https://deoris.test')),
    'auth_verify_ssl' => filter_var(
        env('AUTH_SERVICE_VERIFY_SSL', env('APP_ENV', 'production') !== 'local'),
        FILTER_VALIDATE_BOOLEAN
    ),
    'event_secret' => env('VOTESYS_EVENT_SECRET', env('APP_KEY')),
    'event_schema_version' => '1.0',

    'redis_channels' => [
        'election_events' => env('VOTESYS_REDIS_CHANNEL_ELECTIONS', 'election.events'),
        'voting_notifications' => env('VOTESYS_REDIS_CHANNEL_VOTING', 'voting.notifications'),
        'results_live' => env('VOTESYS_REDIS_CHANNEL_RESULTS', 'results.live'),
    ],

    'queues' => [
        'elections' => env('VOTESYS_QUEUE_ELECTIONS', 'votesys-elections'),
        'voting' => env('VOTESYS_QUEUE_VOTING', 'votesys-voting'),
        'notifications' => env('VOTESYS_QUEUE_NOTIFICATIONS', 'votesys-notifications'),
        'analytics' => env('VOTESYS_QUEUE_ANALYTICS', 'votesys-analytics'),
        'events' => env('VOTESYS_QUEUE_EVENTS', 'votesys-events'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles (DEORIS portal synchronizes role on SSO)
    |--------------------------------------------------------------------------
    */

    'roles' => [
        'admin',
        'election_officer',
        'student',
        'candidate',
    ],

    'role_labels' => [
        'admin'            => 'Administrator',
        'election_officer' => 'Election Officer',
        'student'          => 'Student Voter',
        'candidate'        => 'Candidate',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission matrix
    |--------------------------------------------------------------------------
    */

    'permissions' => [
        // Elections lifecycle
        'elections.view'          => ['admin', 'election_officer', 'student', 'candidate'],
        'elections.create'        => ['admin'],
        'elections.update'        => ['admin', 'election_officer'],
        'elections.delete'        => ['admin'],
        'elections.archive'       => ['admin'],
        'elections.manage_status' => ['admin', 'election_officer'],

        // Positions
        'positions.view'   => ['admin', 'election_officer', 'student', 'candidate'],
        'positions.manage' => ['admin', 'election_officer'],

        // Candidates
        'candidates.view'    => ['admin', 'election_officer', 'student', 'candidate'],
        'candidates.manage'  => ['admin', 'election_officer'],
        'candidates.apply'   => ['candidate'],
        'candidates.create'  => ['admin', 'election_officer'],
        'candidates.update'  => ['admin', 'election_officer', 'candidate'],
        'candidates.delete'  => ['admin'],
        'candidates.approve' => ['admin', 'election_officer'],

        // Voting
        'vote.cast'     => ['student'],
        'vote.view_own' => ['student'],

        // Results & analytics
        'results.view'    => ['admin', 'election_officer', 'student', 'candidate'],
        'results.release' => ['admin', 'election_officer'],
        'analytics.view'  => ['admin', 'election_officer'],

        // Operations
        'activity.view'      => ['admin', 'election_officer'],
        'audit.view'         => ['admin', 'election_officer'],
        'notifications.view' => ['admin', 'election_officer', 'student', 'candidate'],
        'search.use'         => ['admin', 'election_officer', 'student', 'candidate'],
    ],

    'allow_dev_headers' => env('VOTESYS_ALLOW_DEV_HEADERS', true),
    'session_key' => 'votesys_principal',

    /*
    |--------------------------------------------------------------------------
    | Election status workflow
    |--------------------------------------------------------------------------
    */

    'election_statuses' => [
        'draft',
        'candidate_registration',
        'candidate_review',
        'approved',
        'voting_open',
        'voting_closed',
        'result_processing',
        'completed',
        'archived',
    ],

    'votable_statuses' => ['voting_open'],

    'candidate_statuses' => [
        'pending',
        'approved',
        'rejected',
    ],

];
