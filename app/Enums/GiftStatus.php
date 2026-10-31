<?php

declare(strict_types=1);

namespace App\Enums;

enum GiftStatus: string
{
    case Idea = 'idea';
    case Bought = 'bought';
    case Wrapped = 'wrapped';
    case Given = 'given';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Idea => self::Bought,
            self::Bought => self::Wrapped,
            self::Wrapped => self::Given,
            self::Given => null,
        };
    }
}
