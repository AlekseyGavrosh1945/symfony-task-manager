<?php

namespace App\Schedule;

use App\Message\SendQuizQuestion;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Twice a day, at 12:00 and 19:00 (container timezone, Europe/Minsk).
 */
#[AsSchedule('quiz')]
final class QuizQuestionSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::cron('0 12 * * *', new SendQuizQuestion()),
                RecurringMessage::cron('0 19 * * *', new SendQuizQuestion()),
            );
    }
}
