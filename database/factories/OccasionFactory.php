<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OccasionType;
use App\Models\Household;
use App\Models\Occasion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Occasion>
 */
class OccasionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'type' => OccasionType::Christmas,
            'name' => 'Christmas 2026',
            'date' => '2026-12-25',
        ];
    }

    public function forHousehold(Household $household): static
    {
        return $this->state(['household_id' => $household->id]);
    }

    public function christmas(int $year): static
    {
        return $this->state([
            'type' => OccasionType::Christmas,
            'name' => "Christmas $year",
            'date' => "$year-12-25",
        ]);
    }
}
