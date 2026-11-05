<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\User;
use App\Repositories\GiftRepository;
use App\Repositories\OccasionRepository;
use Carbon\CarbonImmutable;

class Dashboard
{
    public function __construct(
        private readonly OccasionRepository $occasions,
        private readonly GiftRepository $gifts,
        private readonly BudgetSummary $summary,
    ) {}

    /**
     * @return array{occasion: Occasion, days: int, budget: int, spent: int, remaining: int, counts: array<string, int>, privateSpent: int|null}|null
     */
    public function christmas(User $user, CarbonImmutable $today): ?array
    {
        $occasion = $this->occasions->currentChristmas($user, $today);

        if ($occasion === null) {
            return null;
        }

        $giftsByPerson = $this->gifts->byPerson($user, $occasion);
        $people = collect($this->occasions->groupedPeople($occasion))->flatten(1);
        $summary = $this->summary->summarize($people, $giftsByPerson);
        $all = collect($giftsByPerson)->flatten(1);

        return [
            'occasion' => $occasion,
            'days' => (int) $today->startOfDay()->diffInDays($occasion->date, false),
            'budget' => $summary['budget'],
            'spent' => $summary['spent'],
            'remaining' => $summary['remaining'],
            'counts' => collect(GiftStatus::cases())
                ->mapWithKeys(fn (GiftStatus $status): array => [$status->value => $all->filter(fn (Gift $gift): bool => $gift->status === $status)->count()])
                ->all(),
            'privateSpent' => $summary['privateSpent'],
        ];
    }

    /**
     * @return list<array{occasion: Occasion, days: int, budget: int, spent: int, remaining: int}>
     */
    public function upcoming(User $user, CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $christmasId = $this->occasions->currentChristmas($user, $today)?->id;

        return array_values($this->occasions->upcoming($user, $today, 60)
            ->reject(fn (Occasion $occasion): bool => $occasion->id === $christmasId)
            ->map(function (Occasion $occasion) use ($user, $today): array {
                // ponytail: one query per occasion; batch if a household ever has dozens
                $summary = $this->summary->summarize(
                    collect($this->occasions->groupedPeople($occasion))->flatten(1),
                    $this->gifts->byPerson($user, $occasion),
                );

                return [
                    'occasion' => $occasion,
                    'days' => (int) $today->diffInDays($occasion->date),
                    'budget' => $summary['budget'],
                    'spent' => $summary['spent'],
                    'remaining' => $summary['remaining'],
                ];
            })
            ->all());
    }
}
