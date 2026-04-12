-- =============================================================================
-- projector_processed_events — idempotency table for event projectors
-- =============================================================================
-- Purpose:
--   Tracks which (projector_name, aggregate_id, event_playhead) tuples have
--   already been projected. Prevents duplicate projection on message re-delivery
--   or consumer restart.
--
-- Critical design note (Bug #15):
--   The PRIMARY KEY includes event_playhead. A naive (projector_name, aggregate_id)
--   PK would silently ignore every event after the first one for a given aggregate,
--   because the INSERT would conflict on the second event and be skipped.
--
-- Usage pattern (PHP):
--   $rowsAffected = $connection->executeStatement(
--       'INSERT INTO projector_processed_events ... ON CONFLICT ... DO NOTHING',
--       [...]
--   );
--   if ($rowsAffected === 0) { /* already processed — skip */ }
--
-- Schema migration:
--   This file is SQL documentation only. The consuming project must create a
--   Doctrine migration (see TASK-04-02 in Phase 24 plan).
-- =============================================================================

CREATE TABLE projector_processed_events (
    projector_name  VARCHAR(255) NOT NULL,
    aggregate_id    UUID         NOT NULL,
    event_playhead  INT          NOT NULL,
    processed_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    PRIMARY KEY (projector_name, aggregate_id, event_playhead)
);

-- Index on processed_at supports the cleanup command (VP-3b / TASK-01-04)
-- which purges old records beyond a configurable retention window.
CREATE INDEX ix_projector_processed_events_processed_at
    ON projector_processed_events (processed_at);
