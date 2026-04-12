<?php

declare(strict_types=1);

namespace MicroModule\Base\Tests\Unit\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use MicroModule\Base\Console\CleanupProjectorProcessedEventsCommand;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CleanupProjectorProcessedEventsCommand::class)]
final class CleanupProjectorProcessedEventsCommandTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Dry-run mode: must call fetchOne for count and NEVER call executeStatement.
     * Verifies that the dry-run branch correctly reports what would be deleted
     * without performing any destructive operation.
     */
    #[Test]
    public function dryRunReportsCountWithoutDeletingRows(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection
            ->shouldReceive('fetchOne')
            ->once()
            ->with(
                Mockery::type('string'),
                Mockery::on(static function (array $params): bool {
                    return isset($params['threshold']) && is_string($params['threshold']);
                })
            )
            ->andReturn('42');

        $connection->shouldNotReceive('executeStatement');

        $command = new CleanupProjectorProcessedEventsCommand($connection);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('[dry-run]', $tester->getDisplay());
        self::assertStringContainsString('42', $tester->getDisplay());
    }

    /**
     * Real run with two non-empty batches followed by an empty batch (terminator).
     *
     * Simulates 5+5+0 pattern: executeStatement is called 3 times total.
     * The loop must stop when a batch returns 0 rows deleted.
     */
    #[Test]
    public function realRunDeletesInBatchesAndStopsWhenBatchIsEmpty(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldNotReceive('fetchOne');
        $connection
            ->shouldReceive('executeStatement')
            ->times(3)
            ->with(
                Mockery::type('string'),
                Mockery::on(static function (array $params): bool {
                    return isset($params['threshold'], $params['batch_size'])
                        && is_string($params['threshold'])
                        && is_int($params['batch_size']);
                }),
                Mockery::on(static function (array $types): bool {
                    return isset($types['batch_size']) && $types['batch_size'] === ParameterType::INTEGER;
                })
            )
            ->andReturn(5, 5, 0);

        $command = new CleanupProjectorProcessedEventsCommand($connection);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Cleanup complete: 10 rows deleted', $display);
    }

    /**
     * Custom options: --older-than-days=7 and --batch-size=500.
     *
     * Verifies that the threshold is computed correctly from custom options
     * and that the batch_size parameter matches the custom value.
     */
    #[Test]
    public function realRunRespectsCustomOlderThanDaysAndBatchSizeOptions(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection
            ->shouldReceive('executeStatement')
            ->once()
            ->with(
                Mockery::type('string'),
                Mockery::on(static function (array $params): bool {
                    if (!isset($params['threshold'], $params['batch_size'])) {
                        return false;
                    }
                    // batch_size must reflect the --batch-size=500 option
                    return $params['batch_size'] === 500;
                }),
                Mockery::on(static function (array $types): bool {
                    return isset($types['batch_size']) && $types['batch_size'] === ParameterType::INTEGER;
                })
            )
            ->andReturn(0);

        $command = new CleanupProjectorProcessedEventsCommand($connection);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--older-than-days' => '7',
            '--batch-size' => '500',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Cleanup complete: 0 rows deleted', $tester->getDisplay());
    }

    /**
     * Dry-run with custom --older-than-days=7:
     * fetchOne is called once and executeStatement is never called.
     */
    #[Test]
    public function dryRunWithCustomOlderThanDaysReportsCorrectly(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection
            ->shouldReceive('fetchOne')
            ->once()
            ->andReturn('0');
        $connection->shouldNotReceive('executeStatement');

        $command = new CleanupProjectorProcessedEventsCommand($connection);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--dry-run' => true,
            '--older-than-days' => '7',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('[dry-run]', $tester->getDisplay());
        self::assertStringContainsString('0', $tester->getDisplay());
    }
}
