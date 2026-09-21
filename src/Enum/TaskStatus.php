<?php

namespace App\Enum;

enum TaskStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::InProgress => 'In progress',
            self::Done => 'Done',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::New => 'bg-secondary',
            self::InProgress => 'bg-warning text-dark',
            self::Done => 'bg-success',
        };
    }
}
