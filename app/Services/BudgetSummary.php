<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\Recipient;
use Illuminate\Support\Collection;

class BudgetSummary
{
    /**
     * @param  Collection<int, Recipient>  $people
     * @param  array<int, Collection<int, Gift>>  $giftsByPerson
     * @return array{people: array<int, array{budget: int|null, spent: int, remaining: int|null}>, budget: int, spent: int, remaining: int, noGift: list<string>}
     */
    public function summarize(Collection $people, array $giftsByPerson): array
    {
        // ponytail: private gifts (feature 10) are excluded before they reach here.
        $rows = [];

        foreach ($people as $person) {
            $spent = (int) ($giftsByPerson[$person->id] ?? collect())->reject(fn (Gift $gift): bool => $gift->status === GiftStatus::Idea)->sum('price_cents');
            /** @var int|null $budget */
            $budget = $person->getRelation('pivot')->getAttribute('budget_cents');
            $rows[$person->id] = ['budget' => $budget, 'spent' => $spent, 'remaining' => $budget === null ? null : (int) ($budget - $spent)];
        }

        $rowsCollection = collect($rows);
        $budget = (int) $rowsCollection->sum('budget');
        $spent = (int) $rowsCollection->sum('spent');
        $noGift = array_values($people
            ->filter(fn (Recipient $person): bool => ($giftsByPerson[$person->id] ?? collect())->isEmpty())
            ->map(fn (Recipient $person): string => $person->name)
            ->all());

        return ['people' => $rows, 'budget' => $budget, 'spent' => $spent, 'remaining' => $budget - $spent, 'noGift' => $noGift];
    }
}
