<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;

/**
 * Aggregates task counters used on the dashboard and in the app:stats command.
 */
class TaskStatisticsService
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
    ) {
    }

    /**
     * @return array{byStatus: array<string, int>, total: int, overdue: int}
     */
    public function getStatsForUser(User $owner): array
    {
        return $this->collect(owner: $owner);
    }

    /**
     * @return array{byStatus: array<string, int>, total: int, overdue: int}
     */
    public function getOverallStats(): array
    {
        return $this->collect();
    }

    /**
     * @return array{byStatus: array<string, int>, total: int, overdue: int}
     */
    private function collect(?User $owner = null): array
    {
        $byStatus = [];
        foreach (TaskStatus::cases() as $status) {
            $byStatus[$status->value] = 0;
        }

        foreach ($this->taskRepository->countByStatusForUser($owner) as $value => $count) {
            $byStatus[$value] = $count;
        }

        return [
            'byStatus' => $byStatus,
            'total' => array_sum($byStatus),
            'overdue' => $this->taskRepository->countOverdueForUser($owner),
        ];
    }
}
