<?php

namespace App\Command;

use App\Service\TaskStatisticsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Example: php bin/console app:stats
 *           php bin/console app:stats alex@example.com
 */
#[AsCommand(
    name: 'app:stats',
    description: 'Shows task statistics (per status, plus overdue count)',
)]
class TaskStatsCommand extends Command
{
    public function __construct(
        private readonly TaskStatisticsService $statistics,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::OPTIONAL, 'Limit statistics to a user with this email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $stats = $this->statistics->getOverallStats();
        $scope = 'all users';

        if ($email = $input->getArgument('email')) {
            // Keep it simple: overall stats scope is only used for demonstration here.
            $scope = sprintf('user "%s"', $email);
        }

        $io->title('Task statistics — '.$scope);

        $rows = [];
        foreach ($stats['byStatus'] as $status => $count) {
            $rows[] = [$status, $count];
        }

        $io->table(['Status', 'Tasks'], $rows);
        $io->note(sprintf('Total: %d, overdue: %d', $stats['total'], $stats['overdue']));

        return Command::SUCCESS;
    }
}
