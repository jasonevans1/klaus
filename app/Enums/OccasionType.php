<?php

declare(strict_types=1);

namespace App\Enums;

enum OccasionType: string
{
    case Christmas = 'christmas';
    case Birthday = 'birthday';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
