<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class ValidBirthday implements DataAwareRule, ValidationRule
{
    /** Run even when birthday_month is empty, so a day or year alone is caught. */
    public bool $implicit = true;

    /** @var array<string, mixed> */
    protected array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    private function part(string $prefix, string $field): ?int
    {
        $value = data_get($this->data, $prefix.$field);

        return $value === null || $value === '' ? null : (int) $value;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $prefix = Str::beforeLast($attribute, 'birthday_month');
        $month = $this->part($prefix, 'birthday_month');
        $day = $this->part($prefix, 'birthday_day');

        $year = $this->part($prefix, 'birthday_year');

        if ($month === null && $day === null) {
            if ($year !== null) {
                $fail('Add the month and day too.');
            }

            return;
        }

        if ($month === null || $day === null) {
            $fail('Enter both the month and day.');

            return;
        }

        // Year-less dates are checked against a leap year so Feb 29 is allowed.
        if (! checkdate($month, $day, $year ?? 2000)) {
            $fail("That date doesn't exist.");

            return;
        }

        if ($year !== null && ($year < 1900 || $year > now()->year)) {
            $fail('Enter a year between 1900 and this year.');
        }
    }
}
