<?php

declare(strict_types=1);

namespace App\Support;

final class Price
{
    /** @return list<string> */
    public static function rules(): array
    {
        return ['nullable', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'];
    }

    public static function toCents(int|string|null $dollars): ?int
    {
        return $dollars === null || $dollars === '' ? null : (int) round((float) $dollars * 100);
    }

    public static function format(?int $cents): string
    {
        return $cents === null ? 'No price' : '$'.self::number($cents, ',');
    }

    public static function toDollars(?int $cents): string
    {
        return $cents === null ? '' : self::number($cents, '');
    }

    private static function number(int $cents, string $thousands): string
    {
        return $cents % 100 === 0
            ? number_format(intdiv($cents, 100), 0, '.', $thousands)
            : number_format($cents / 100, 2, '.', $thousands);
    }
}
