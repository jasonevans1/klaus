<?php

declare(strict_types=1);

namespace App\Enums;

enum RecipientGroup: string
{
    case Family = 'family';
    case Friends = 'friends';
    case Teachers = 'teachers';
    case Coworkers = 'coworkers';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
