<?php

declare(strict_types=1);

namespace App\Actions\Occasions;

use App\Enums\OccasionType;
use App\Models\Occasion;
use App\Models\User;
use App\Repositories\OccasionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateChristmas
{
    public function __construct(private readonly OccasionRepository $occasions) {}

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

        return DB::transaction(function () use ($user, $year): Occasion {
            $occasion = new Occasion([
                'type' => OccasionType::Christmas,
                'name' => "Christmas {$year}",
                'date' => "{$year}-12-25",
            ]);
            $occasion->household_id = $user->household_id;
            $occasion->save();

            $previous = $this->occasions->previousChristmas($user, $year);

            $budgets = $previous?->recipients()->whereNull('archived_at')
                ->pluck('occasion_recipient.budget_cents', 'recipients.id') ?? collect();

            $occasion->recipients()->attach($budgets->map(fn ($cents) => ['budget_cents' => $cents])->all());

            return $occasion;
        });
    }
}
