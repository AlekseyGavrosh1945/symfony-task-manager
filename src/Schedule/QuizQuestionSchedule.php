<?php

namespace App\Schedule;

use App\Message\SendQuizQuestion;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Twice a day, at 12:00 and 19:00 (container timezone, Europe/Minsk).
 *
 * The schedule is stateful: if the worker was down at the scheduled time,
 * only the last missed run is executed after restart.
 */
#[AsSchedule('quiz')]
final class QuizQuestionSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::cron('0 12 * * *', new SendQuizQuestion()),
                RecurringMessage::cron('0 19 * * *', new SendQuizQuestion()),
            )
            ->stateful($this->cache)      // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true); // but only the last missed run
    }
}
