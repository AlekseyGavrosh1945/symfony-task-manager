<?php

namespace App\Event;

use App\Entity\Task;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched whenever a new task is persisted.
 */
final class TaskCreatedEvent extends Event
{
    public function __construct(
        public readonly Task $task,
    ) {
    }
}
