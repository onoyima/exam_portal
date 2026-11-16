-- Server-authoritative (wall-clock) exam timing, lock pause and resume position.
--
-- The portal applies these changes by itself on the first request after the
-- update (see qe_ensure_schema / QE_SCHEMA_VERSION = 8 in
-- includes/question_engine.php). This file is for DBAs who prefer to run the
-- change by hand; run it once, then the automatic check will find nothing to do.
--
-- Why: time used to be accumulated from heartbeats. A long silence (power cut,
-- dropped Wi-Fi, browser closed) was treated as an interruption and forgiven,
-- so signing in again could hand back minutes that had really elapsed. Time is
-- now derived from started_at minus explicitly excused time, so it can never
-- move backwards. Locked time is excused through locked_at/excused_seconds.

ALTER TABLE objective_attempts
    ADD COLUMN excused_seconds INT NOT NULL DEFAULT 0 AFTER time_used,
    ADD COLUMN locked_at DATETIME NULL AFTER excused_seconds,
    ADD COLUMN last_question_index INT NULL AFTER locked_at;

-- Attempts that are locked right now have no recorded lock start; excuse from now.
UPDATE objective_attempts SET locked_at = NOW() WHERE is_locked = 1 AND locked_at IS NULL;

-- Bring stale open-attempt counters up to the wall clock. GREATEST keeps this
-- monotonic: an attempt can gain time here, never lose it.
UPDATE objective_attempts
   SET time_used = GREATEST(time_used, LEAST(GREATEST(
           TIMESTAMPDIFF(SECOND, started_at, NOW())
           - excused_seconds
           - IF(locked_at IS NOT NULL, TIMESTAMPDIFF(SECOND, locked_at, NOW()), 0)
       , 0), 86400))
 WHERE finished_at IS NULL AND started_at IS NOT NULL;
