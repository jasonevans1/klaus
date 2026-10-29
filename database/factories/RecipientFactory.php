<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecipientGroup;
use App\Models\Household;
use App\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipient>
 */
class RecipientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'group' => fake()->randomElement(RecipientGroup::cases()),
            'name' => fake()->name(),
            'is_shared' => false,
        ];
    }

    public function shared(): static
    {
        return $this->state(['is_shared' => true]);
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }

    public function withBirthday(int $month, int $day, ?int $year = null): static
    {
        return $this->state([
            'birthday_month' => $month,
            'birthday_day' => $day,
            'birthday_year' => $year,
        ]);
    }

    public function forHousehold(Household $household): static
    {
        return $this->state(['household_id' => $household->id]);
    }
}
