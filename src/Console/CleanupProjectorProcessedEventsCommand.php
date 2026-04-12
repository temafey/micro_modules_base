<?php

declare(strict_types=1);

namespace MicroModule\Base\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'micro-module:projector-idempotency:cleanup',
    description: 'Removes old entries from projector_processed_events table'
)]
final class CleanupProjectorProcessedEventsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'older-than-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Delete entries processed more than N days ago',
                '30'
            )
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows per DELETE batch', '1000')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without executing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $olderThanDays = (int) $input->getOption('older-than-days');
        $batchSize = (int) $input->getOption('batch-size');
        $dryRun = (bool) $input->getOption('dry-run');

        $threshold = new \DateTimeImmutable("-{$olderThanDays} days");

        if ($dryRun) {
            $count = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM projector_processed_events WHERE processed_at < :threshold',
                ['threshold' => $threshold->format('Y-m-d H:i:s')]
            );
            $output->writeln("[dry-run] Would delete {$count} rows older than {$threshold->format('Y-m-d')}");

            return Command::SUCCESS;
        }

        $totalDeleted = 0;
        do {
            $deleted = $this->connection->executeStatement(
                'DELETE FROM projector_processed_events
                 WHERE ctid IN (
                     SELECT ctid FROM projector_processed_events
                     WHERE processed_at < :threshold
                     LIMIT :batch_size
                 )',
                [
                    'threshold' => $threshold->format('Y-m-d H:i:s'),
                    'batch_size' => $batchSize,
                ],
                ['batch_size' => ParameterType::INTEGER]
            );
            $totalDeleted += $deleted;
            $output->writeln("Deleted batch of {$deleted} rows (total: {$totalDeleted})");
        } while ($deleted > 0);

        $output->writeln("Cleanup complete: {$totalDeleted} rows deleted");

        return Command::SUCCESS;
    }
}
