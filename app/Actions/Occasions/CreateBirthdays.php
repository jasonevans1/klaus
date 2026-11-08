<?php

declare(strict_types=1);

namespace App\Actions\Occasions;

use App\Enums\OccasionType;
use App\Models\Occasion;
use App\Models\User;
use App\Repositories\OccasionRepository;
use App\Repositories\RecipientRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBirthdays
{
    public function __construct(
        private readonly OccasionRepository $occasions,
        private readonly RecipientRepository $recipients,
    ) {}

    public function handle(User $user, int $year): Occasion
    {
        if ($this->occasions->birthdaysFor($user, $year) !== null) {
            throw ValidationException::withMessages(['year' => "You already have Birthdays {$year}."]);
        }

        return DB::transaction(function () use ($user, $year): Occasion {
            $occasion = new Occasion([
                'type' => OccasionType::Birthdays,
                'name' => "Birthdays {$year}",
                'date' => "{$year}-01-01",
            ]);
            $occasion->household_id = $user->household_id;
            $occasion->save();

            $budgets = $this->occasions->previousBirthdays($user, $year)?->recipients()
                ->pluck('occasion_recipient.budget_cents', 'recipients.id') ?? collect();

            $occasion->recipients()->attach(
                $this->recipients->withBirthdays($user)
                    ->mapWithKeys(fn ($person) => [$person->id => ['budget_cents' => $budgets[$person->id] ?? null]])
                    ->all(),
            );

            return $occasion;
        });
    }
}
