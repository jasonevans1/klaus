<?php

declare(strict_types=1);

namespace App\Support;

final class Budget
{
    /** @return list<string> */
    public static function rules(): array
    {
        return ['nullable', 'integer', 'min:0', 'max:100000'];
    }

    public static function toCents(int|string|null $dollars): ?int
    {
        return $dollars === null || $dollars === '' ? null : (int) $dollars * 100;
    }

    public static function format(?int $cents): string
    {
        return $cents === null ? 'No budget' : '$'.number_format(intdiv($cents, 100));
    }
}
