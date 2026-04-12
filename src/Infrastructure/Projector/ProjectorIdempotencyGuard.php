<?php

declare(strict_types=1);

namespace MicroModule\Base\Infrastructure\Projector;

use Doctrine\DBAL\Connection;

/**
 * Atomic idempotency guard for event projectors.
 *
 * Uses a composite primary key (projector_name, aggregate_id, event_playhead)
 * to track which events have already been projected. The composite PK is
 * intentional — a naive (projector_name, aggregate_id) PK would drop all
 * but the first event per aggregate (Bug #15).
 *
 * The guard MUST be wired to the write connection in the DI configuration
 * so that it participates in the same transaction as the projector writes.
 *
 * Schema: docs/schema/projector_processed_events.sql
 */
final readonly class ProjectorIdempotencyGuard
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * Atomic check-and-mark using INSERT … ON CONFLICT DO NOTHING.
     *
     * Returns true  when the event was NOT previously processed — caller
     * should proceed with the projection.
     * Returns false when the event was already processed — caller should skip.
     *
     * The INSERT is a single atomic statement; PostgreSQL enforces the UNIQUE
     * constraint at the storage layer, making this safe under concurrent
     * consumers.
     */
    public function markIfNotProcessed(
        string $projectorName,
        string $aggregateId,
        int $eventPlayhead,
    ): bool {
        $rowsAffected = $this->connection->executeStatement(
            'INSERT INTO projector_processed_events
                (projector_name, aggregate_id, event_playhead, processed_at)
             VALUES (:projector, :aggregate_id, :playhead, CURRENT_TIMESTAMP)
             ON CONFLICT (projector_name, aggregate_id, event_playhead) DO NOTHING',
            [
                'projector' => $projectorName,
                'aggregate_id' => $aggregateId,
                'playhead' => $eventPlayhead,
            ]
        );

        return $rowsAffected > 0;
    }

    /**
     * Deletes all processed-event records for the given projector.
     *
     * Used by rebuild tooling before truncating and re-projecting a read
     * model from scratch. Prefer calling this inside the same transaction
     * that resets the read-model table.
     */
    public function reset(string $projectorName): void
    {
        $this->connection->executeStatement(
            'DELETE FROM projector_processed_events WHERE projector_name = :projector',
            ['projector' => $projectorName]
        );
    }
}
