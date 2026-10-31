<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\Household;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gift>
 */
class GiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'recipient_id' => fn (array $attributes) => Recipient::factory()->create(['household_id' => $attributes['household_id']])->id,
            'occasion_id' => fn (array $attributes) => Occasion::factory()->create(['household_id' => $attributes['household_id']])->id,
            'title' => fake()->words(3, true),
            'price_cents' => fake()->optional()->numberBetween(500, 20000),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Gift $gift): void {
            $gift->occasion?->recipients()->syncWithoutDetaching([$gift->recipient_id]);
        });
    }

    public function forPersonOn(Recipient $recipient, Occasion $occasion): static
    {
        return $this->state([
            'household_id' => $recipient->household_id,
            'recipient_id' => $recipient->id,
            'occasion_id' => $occasion->id,
        ]);
    }

    public function bought(User $buyer): static
    {
        return $this->purchased(GiftStatus::Bought, $buyer);
    }

    public function wrapped(User $buyer): static
    {
        return $this->purchased(GiftStatus::Wrapped, $buyer);
    }

    public function given(User $buyer): static
    {
        return $this->purchased(GiftStatus::Given, $buyer);
    }

    private function purchased(GiftStatus $status, User $buyer): static
    {
        return $this->state(['status' => $status, 'purchased_by_user_id' => $buyer->id]);
    }
}
