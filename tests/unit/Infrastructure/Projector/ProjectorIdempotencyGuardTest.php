<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Infrastructure\Projector;

use Doctrine\DBAL\Connection;
use MicroModule\Base\Infrastructure\Projector\ProjectorIdempotencyGuard;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectorIdempotencyGuard::class)]
final class ProjectorIdempotencyGuardTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const string PROJECTOR_A = 'App\\Projector\\NewsProjector';
    private const string AGGREGATE_1 = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
    private const int PLAYHEAD_5 = 5;

    /**
     * Scenario 1: First call for a new (projector, aggregate, playhead) tuple.
     *
     * executeStatement returns 1 (row inserted) → markIfNotProcessed returns true,
     * meaning the caller should proceed with projection.
     */
    #[Test]
    public function markIfNotProcessedReturnsTrueWhenRowIsInserted(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection
            ->shouldReceive('executeStatement')
            ->once()
            ->with(
                Mockery::type('string'),
                [
                    'projector' => self::PROJECTOR_A,
                    'aggregate_id' => self::AGGREGATE_1,
                    'playhead' => self::PLAYHEAD_5,
                ]
            )
            ->andReturn(1);

        $guard = new ProjectorIdempotencyGuard($connection);

        $result = $guard->markIfNotProcessed(self::PROJECTOR_A, self::AGGREGATE_1, self::PLAYHEAD_5);

        self::assertTrue($result);
    }

    /**
     * Scenario 2: Duplicate call for the same (projector, aggregate, playhead) tuple.
     *
     * ON CONFLICT DO NOTHING causes executeStatement to return 0 (no row inserted) →
     * markIfNotProcessed returns false, signalling the caller should skip projection.
     */
    #[Test]
    public function markIfNotProcessedReturnsFalseWhenConflictOccurs(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection
            ->shouldReceive('executeStatement')
            ->once()
            ->with(
                Mockery::type('string'),
                [
                    'projector' => self::PROJECTOR_A,
                    'aggregate_id' => self::AGGREGATE_1,
                    'playhead' => self::PLAYHEAD_5,
                ]
            )
            ->andReturn(0);

        $guard = new ProjectorIdempotencyGuard($connection);

        $result = $guard->markIfNotProcessed(self::PROJECTOR_A, self::AGGREGATE_1, self::PLAYHEAD_5);

        self::assertFalse($result);
    }

    /**
     * Scenario 3: Different event_playhead for the same (projector, aggregate).
     *
     * The composite PK includes event_playhead, so playhead=6 is a distinct row
     * even though projector and aggregate are identical to scenario 1.
     * executeStatement returns 1 → markIfNotProcessed returns true.
     */
    #[Test]
    public function markIfNotProcessedReturnsTrueForDifferentPlayhead(): void
    {
        $differentPlayhead = 6;

        $connection = Mockery::mock(Connection::class);
        $connection
            ->shouldReceive('executeStatement')
            ->once()
            ->with(
                Mockery::type('string'),
                [
                    'projector' => self::PROJECTOR_A,
                    'aggregate_id' => self::AGGREGATE_1,
                    'playhead' => $differentPlayhead,
                ]
            )
            ->andReturn(1);

        $guard = new ProjectorIdempotencyGuard($connection);

        $result = $guard->markIfNotProcessed(self::PROJECTOR_A, self::AGGREGATE_1, $differentPlayhead);

        self::assertTrue($result);
    }

    /**
     * Scenario 4: Different projector_name for the same (aggregate, playhead).
     *
     * Each projector tracks its own processed events independently, so a second
     * projector processing the same event is a new row.
     * executeStatement returns 1 → markIfNotProcessed returns true.
     */
    #[Test]
    public function markIfNotProcessedReturnsTrueForDifferentProjectorName(): void
    {
        $projectorB = 'App\\Projector\\NewsListProjector';

        $connection = Mockery::mock(Connection::class);
        $connection
            ->shouldReceive('executeStatement')
            ->once()
            ->with(
                Mockery::type('string'),
                [
                    'projector' => $projectorB,
                    'aggregate_id' => self::AGGREGATE_1,
                    'playhead' => self::PLAYHEAD_5,
                ]
            )
            ->andReturn(1);

        $guard = new ProjectorIdempotencyGuard($connection);

        $result = $guard->markIfNotProcessed($projectorB, self::AGGREGATE_1, self::PLAYHEAD_5);

        self::assertTrue($result);
    }

    /**
     * Scenario 5: reset() deletes all processed-event records for a given projector.
     *
     * The DELETE statement must be called once with the correct SQL and parameters
     * so that only the target projector's records are removed.
     * MockeryPHPUnitIntegration verifies the call count at tearDown time.
     */
    #[Test]
    public function resetDeletesAllRecordsForProjector(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection
            ->shouldReceive('executeStatement')
            ->once()
            ->with(
                'DELETE FROM projector_processed_events WHERE projector_name = :projector',
                ['projector' => self::PROJECTOR_A]
            )
            ->andReturn(42);

        $guard = new ProjectorIdempotencyGuard($connection);
        $guard->reset(self::PROJECTOR_A);

        // Verify the mock received the call (explicit assertion to avoid PHPUnit risky-test warning).
        $connection->shouldHaveReceived('executeStatement')->once();
    }
}
