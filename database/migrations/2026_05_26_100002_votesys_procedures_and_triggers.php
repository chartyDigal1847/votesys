<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Creates MySQL stored procedures and triggers for VoteSys.
 *
 * Procedures:
 *   sp_detect_duplicate_vote   — checks if a voter has already voted for a position
 *   sp_compute_election_results — aggregates votes into election_results table
 *
 * Triggers:
 *   trg_votes_after_insert     — auto-refreshes election_results after each vote
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // ── Stored Procedure: duplicate vote detection ────────────────────
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_detect_duplicate_vote');
        DB::unprepared('
            CREATE PROCEDURE sp_detect_duplicate_vote(
                IN  p_election_id       BIGINT UNSIGNED,
                IN  p_position_id       BIGINT UNSIGNED,
                IN  p_voter_external_id VARCHAR(64),
                OUT p_has_voted         TINYINT
            )
            BEGIN
                SELECT COUNT(*) INTO p_has_voted
                FROM votes
                WHERE election_id       = p_election_id
                  AND position_id       = p_position_id
                  AND voter_external_id = p_voter_external_id
                  AND is_locked         = 1;
            END
        ');

        // ── Stored Procedure: result computation ─────────────────────────
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_compute_election_results');
        DB::unprepared('
            CREATE PROCEDURE sp_compute_election_results(
                IN p_election_id BIGINT UNSIGNED
            )
            BEGIN
                DECLARE done        INT DEFAULT FALSE;
                DECLARE v_pos_id    BIGINT UNSIGNED;
                DECLARE v_cand_id   BIGINT UNSIGNED;
                DECLARE v_cnt       INT UNSIGNED;
                DECLARE v_pos_total INT UNSIGNED;
                DECLARE v_pct       DECIMAL(5,2);
                DECLARE v_rank      SMALLINT UNSIGNED;

                DECLARE cur CURSOR FOR
                    SELECT position_id, candidate_id, COUNT(*) AS vote_count
                    FROM votes
                    WHERE election_id = p_election_id
                    GROUP BY position_id, candidate_id
                    ORDER BY position_id, vote_count DESC;

                DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

                -- Clear existing results for this election
                DELETE FROM election_results WHERE election_id = p_election_id;

                SET v_rank = 0;
                SET v_pos_id = 0;

                OPEN cur;
                read_loop: LOOP
                    FETCH cur INTO v_pos_id, v_cand_id, v_cnt;
                    IF done THEN
                        LEAVE read_loop;
                    END IF;

                    -- Get total votes for this position
                    SELECT COUNT(*) INTO v_pos_total
                    FROM votes
                    WHERE election_id = p_election_id
                      AND position_id = v_pos_id;

                    IF v_pos_total > 0 THEN
                        SET v_pct = ROUND((v_cnt / v_pos_total) * 100, 2);
                    ELSE
                        SET v_pct = 0.00;
                    END IF;

                    -- Rank resets per position (handled by ORDER BY in cursor)
                    SET v_rank = v_rank + 1;

                    INSERT INTO election_results
                        (election_id, position_id, candidate_id, vote_count, vote_percentage, rank, computed_at, created_at, updated_at)
                    VALUES
                        (p_election_id, v_pos_id, v_cand_id, v_cnt, v_pct, v_rank, NOW(), NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        vote_count       = v_cnt,
                        vote_percentage  = v_pct,
                        rank             = v_rank,
                        computed_at      = NOW(),
                        updated_at       = NOW();
                END LOOP;
                CLOSE cur;
            END
        ');

        // ── Trigger: auto-update results after each vote insert ───────────
        DB::unprepared('DROP TRIGGER IF EXISTS trg_votes_after_insert');
        DB::unprepared('
            CREATE TRIGGER trg_votes_after_insert
            AFTER INSERT ON votes
            FOR EACH ROW
            BEGIN
                -- Update the live vote count view data by refreshing the
                -- election_results row for the affected candidate/position.
                INSERT INTO election_results
                    (election_id, position_id, candidate_id, vote_count, vote_percentage, rank, computed_at, created_at, updated_at)
                SELECT
                    NEW.election_id,
                    NEW.position_id,
                    NEW.candidate_id,
                    COUNT(*),
                    ROUND(COUNT(*) * 100.0 / NULLIF(
                        (SELECT COUNT(*) FROM votes
                         WHERE election_id = NEW.election_id
                           AND position_id = NEW.position_id), 0
                    ), 2),
                    0,
                    NOW(),
                    NOW(),
                    NOW()
                FROM votes
                WHERE election_id = NEW.election_id
                  AND position_id = NEW.position_id
                  AND candidate_id = NEW.candidate_id
                ON DUPLICATE KEY UPDATE
                    vote_count = VALUES(vote_count),
                    vote_percentage = VALUES(vote_percentage),
                    computed_at = NOW(),
                    updated_at = NOW();
            END
        ');

        // ── Trigger: prevent duplicate locked votes ───────────────────────
        DB::unprepared('DROP TRIGGER IF EXISTS trg_votes_before_insert');
        DB::unprepared('
            CREATE TRIGGER trg_votes_before_insert
            BEFORE INSERT ON votes
            FOR EACH ROW
            BEGIN
                DECLARE v_existing INT DEFAULT 0;

                IF NEW.voter_external_id IS NOT NULL THEN
                    SELECT COUNT(*) INTO v_existing
                    FROM votes
                    WHERE election_id       = NEW.election_id
                      AND position_id       = NEW.position_id
                      AND voter_external_id = NEW.voter_external_id
                      AND is_locked         = 1;

                    IF v_existing > 0 THEN
                        SIGNAL SQLSTATE "45000"
                            SET MESSAGE_TEXT = "Duplicate vote detected: voter has already cast a locked vote for this position.";
                    END IF;
                END IF;
            END
        ');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_votes_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_votes_after_insert');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_compute_election_results');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_detect_duplicate_vote');
    }
};
