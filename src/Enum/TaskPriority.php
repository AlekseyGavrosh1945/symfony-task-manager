<?php

namespace App\Enum;

enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Low => 'bg-info text-dark',
            self::Medium => 'bg-primary',
            self::High => 'bg-danger',
        };
    }
}
