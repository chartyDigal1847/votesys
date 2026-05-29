<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('
            CREATE OR REPLACE VIEW v_election_vote_summary AS
            SELECT
                e.id AS election_id,
                e.name AS election_name,
                e.status AS election_status,
                p.id AS position_id,
                p.name AS position_name,
                c.id AS candidate_id,
                c.name AS candidate_name,
                COUNT(v.id) AS vote_count
            FROM elections e
            INNER JOIN positions p ON p.election_id = e.id
            INNER JOIN candidates c ON c.position_id = p.id AND c.deleted_at IS NULL
            LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = e.id
            WHERE e.deleted_at IS NULL
            GROUP BY e.id, e.name, e.status, p.id, p.name, c.id, c.name
        ');

        DB::unprepared('
            CREATE OR REPLACE VIEW v_voter_turnout AS
            SELECT
                e.id AS election_id,
                e.name AS election_name,
                COUNT(DISTINCT v.voter_external_id) AS unique_voters,
                COUNT(v.id) AS total_ballots
            FROM elections e
            LEFT JOIN votes v ON v.election_id = e.id
            WHERE e.deleted_at IS NULL
            GROUP BY e.id, e.name
        ');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP VIEW IF EXISTS v_voter_turnout');
        DB::unprepared('DROP VIEW IF EXISTS v_election_vote_summary');
    }
};
