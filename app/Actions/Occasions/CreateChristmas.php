<?php

declare(strict_types=1);

namespace App\Actions\Occasions;

use App\Enums\OccasionType;
use App\Models\Occasion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateChristmas
{
    public function handle(User $user, int $year): Occasion
    {
        $exists = Occasion::query()
            ->where('household_id', $user->household_id)
            ->where('type', OccasionType::Christmas)
            ->whereYear('date', $year)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['year' => "You already have Christmas {$year}."]);
        }

        $occasion = new Occasion([
            'type' => OccasionType::Christmas,
            'name' => "Christmas {$year}",
            'date' => "{$year}-12-25",
        ]);
        $occasion->household_id = $user->household_id;
        $occasion->save();

        return $occasion;
    }
}
