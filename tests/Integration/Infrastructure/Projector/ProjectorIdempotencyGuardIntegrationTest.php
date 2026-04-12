<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Integration\Infrastructure\Projector;

use Doctrine\DBAL\DriverManager;
use MicroModule\Base\Infrastructure\Projector\ProjectorIdempotencyGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for ProjectorIdempotencyGuard.
 *
 * Uses an SQLite in-memory database (no external infrastructure required).
 *
 * SQLite differences from PostgreSQL:
 *  - UUID column mapped to TEXT (SQLite has no native UUID type)
 *  - TIMESTAMPTZ mapped to TEXT (SQLite stores dates as TEXT)
 *  - NOW() replaced with CURRENT_TIMESTAMP (portable)
 *  - ON CONFLICT DO NOTHING is supported since SQLite 3.24.0 (2018)
 *
 * The test verifies that the UNIQUE constraint is enforced at the DB level,
 * not just in PHP. This is the critical guarantee: even if the PHP guard is
 * bypassed, the DB rejects duplicates.
 */
#[CoversClass(ProjectorIdempotencyGuard::class)]
final class ProjectorIdempotencyGuardIntegrationTest extends TestCase
{
    private const string PROJECTOR_A = 'App\\Projector\\NewsProjector';
    private const string AGGREGATE_1 = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
    private const int PLAYHEAD_5 = 5;

    private \Doctrine\DBAL\Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->applySchema();
    }

    protected function tearDown(): void
    {
        $this->connection->close();

        parent::tearDown();
    }

    /**
     * Verifies that the first markIfNotProcessed call returns true and inserts a row.
     */
    #[Test]
    public function firstCallInsertsRowAndReturnsTrue(): void
    {
        $guard = new ProjectorIdempotencyGuard($this->connection);

        $result = $guard->markIfNotProcessed(self::PROJECTOR_A, self::AGGREGATE_1, self::PLAYHEAD_5);

        self::assertTrue($result);
        self::assertSame(1, $this->countRows());
    }

    /**
     * Verifies that a duplicate call returns false and does NOT insert a second row.
     * This confirms the UNIQUE constraint is enforced at the DB level.
     */
    #[Test]
    public function duplicateCallReturnsFalseAndDoesNotInsertRow(): void
    {
        $guard = new ProjectorIdempotencyGuard($this->connection);

        $firstResult = $guard->markIfNotProcessed(self::PROJECTOR_A, self::AGGREGATE_1, self::PLAYHEAD_5);
        $secondResult = $guard->markIfNotProcessed(self::PROJECTOR_A, self::AGGREGATE_1, self::PLAYHEAD_5);

        self::assertTrue($firstResult, 'First call must return true (row inserted)');
        self::assertFalse($secondResult, 'Duplicate call must return false (ON CONFLICT DO NOTHING)');
        self::assertSame(1, $this->countRows(), 'DB must contain exactly one row after duplicate call');
    }

    /**
     * Verifies that different event_playhead values for the same projector+aggregate
     * are treated as distinct rows (composite PK includes playhead — Bug #15 fix).
     */
    #[Test]
    public function differentPlayheadIsDistinctRow(): void
    {
        $guard = new ProjectorIdempotencyGuard($this->connection);

        $result1 = $guard->markIfNotProcessed(self::PROJECTOR_A, self::AGGREGATE_1, 5);
        $result2 = $guard->markIfNotProcessed(self::PROJECTOR_A, self::AGGREGATE_1, 6);

        self::assertTrue($result1);
        self::assertTrue($result2);
        self::assertSame(2, $this->countRows());
    }

    /**
     * Verifies that reset() removes all rows for the given projector.
     */
    #[Test]
    public function resetRemovesAllRowsForProjector(): void
    {
        $guard = new ProjectorIdempotencyGuard($this->connection);

        $guard->markIfNotProcessed(self::PROJECTOR_A, self::AGGREGATE_1, 1);
        $guard->markIfNotProcessed(self::PROJECTOR_A, self::AGGREGATE_1, 2);
        $guard->markIfNotProcessed('App\\Projector\\OtherProjector', self::AGGREGATE_1, 1);

        self::assertSame(3, $this->countRows(), 'Pre-condition: 3 rows inserted');

        $guard->reset(self::PROJECTOR_A);

        self::assertSame(1, $this->countRows(), 'Only the other projector row remains');
    }

    /**
     * Verifies that after reset(), the same tuple can be re-inserted successfully.
     * This validates the rebuild workflow: reset then re-project.
     */
    #[Test]
    public function afterResetSameTupleCanBeReInserted(): void
    {
        $guard = new ProjectorIdempotencyGuard($this->connection);

        $guard->markIfNotProcessed(self::PROJECTOR_A, self::AGGREGATE_1, self::PLAYHEAD_5);
        $guard->reset(self::PROJECTOR_A);
        $result = $guard->markIfNotProcessed(self::PROJECTOR_A, self::AGGREGATE_1, self::PLAYHEAD_5);

        self::assertTrue($result, 'After reset, the same tuple must be insertable again');
        self::assertSame(1, $this->countRows());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function applySchema(): void
    {
        // SQLite-compatible schema. UUID and TIMESTAMPTZ are stored as TEXT.
        // ON CONFLICT DO NOTHING is supported since SQLite 3.24.0 (June 2018).
        $this->connection->executeStatement(
            'CREATE TABLE projector_processed_events (
                projector_name  TEXT    NOT NULL,
                aggregate_id    TEXT    NOT NULL,
                event_playhead  INTEGER NOT NULL,
                processed_at    TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (projector_name, aggregate_id, event_playhead)
            )'
        );
    }

    private function countRows(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM projector_processed_events'
        );
    }
}
