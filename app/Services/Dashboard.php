<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GiftStatus;
use App\Enums\OccasionType;
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
     * @return list<array{name: string, occasionId: int, date: CarbonImmutable, days: int, budget: int, spent: int, remaining: int}>
     */
    public function upcoming(User $user, CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $christmasId = $this->occasions->currentChristmas($user, $today)?->id;

        $rows = $this->occasions->upcoming($user, $today, 60)
            ->reject(fn (Occasion $occasion): bool => $occasion->id === $christmasId || $occasion->type === OccasionType::Birthdays)
            ->map(function (Occasion $occasion) use ($user, $today): array {
                // ponytail: one query per occasion; batch if a household ever has dozens
                $summary = $this->summary->summarize(
                    collect($this->occasions->groupedPeople($occasion))->flatten(1),
                    $this->gifts->byPerson($user, $occasion),
                );

                return $this->row($occasion->name, $occasion->id, CarbonImmutable::instance($occasion->date), $today, $summary);
            })
            ->all();

        return array_values(collect([...$rows, ...$this->birthdayRows($user, $today)])->sortBy('date')->all());
    }

    /**
     * @return list<array{name: string, occasionId: int, date: CarbonImmutable, days: int, budget: int, spent: int, remaining: int}>
     */
    private function birthdayRows(User $user, CarbonImmutable $today): array
    {
        $rows = [];

        // The 60-day window can cross into next year.
        foreach ([$today->year, $today->year + 1] as $year) {
            $occasion = $this->occasions->birthdaysFor($user, $year);

            if ($occasion === null) {
                continue;
            }

            $giftsByPerson = $this->gifts->byPerson($user, $occasion);

            foreach ($occasion->recipients()->get() as $person) {
                $date = $person->archived_at === null ? $person->nextBirthday($today) : null;

                if ($date === null || $date->year !== $year || $today->diffInDays($date) > 60) {
                    continue;
                }

                $name = $person->name;
                $possessive = str_ends_with(mb_strtolower($name), 's') ? "'" : "'s";
                $rows[] = $this->row("{$name}{$possessive} birthday", $occasion->id, $date, $today, $this->summary->summarize(collect([$person]), $giftsByPerson));
            }
        }

        return $rows;
    }

    /**
     * @param  array{budget: int, spent: int, remaining: int}  $summary
     * @return array{name: string, occasionId: int, date: CarbonImmutable, days: int, budget: int, spent: int, remaining: int}
     */
    private function row(string $name, int $occasionId, CarbonImmutable $date, CarbonImmutable $today, array $summary): array
    {
        return [
            'name' => $name,
            'occasionId' => $occasionId,
            'date' => $date,
            'days' => (int) $today->diffInDays($date),
            'budget' => $summary['budget'],
            'spent' => $summary['spent'],
            'remaining' => $summary['remaining'],
        ];
    }
}
