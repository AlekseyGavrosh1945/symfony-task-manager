<?php

namespace App\EventSubscriber;

use App\Event\TaskCreatedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Writes an entry to the dedicated "task_activity" log channel
 * every time a TaskCreatedEvent is dispatched.
 */
class TaskActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $taskActivityLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TaskCreatedEvent::class => 'onTaskCreated',
        ];
    }

    public function onTaskCreated(TaskCreatedEvent $event): void
    {
        $task = $event->task;

        $this->taskActivityLogger->info('Task created', [
            'task_id' => $task->getId(),
            'title' => $task->getTitle(),
            'owner' => $task->getOwner()?->getEmail(),
            'priority' => $task->getPriority()?->value,
        ]);
    }
}
