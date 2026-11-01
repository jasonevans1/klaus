<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class OccasionRepository
{
    public function __construct(private readonly RecipientRepository $recipients) {}

    /**
     * @return Collection<int, Occasion>
     */
    public function listForUser(User $user): Collection
    {
        return Occasion::query()->withCount(['gifts' => fn ($query) => $query->visibleTo($user)])->where('household_id', $user->household_id)->orderByDesc('date')->get();
    }

    public function findForUser(User $user, int $id): Occasion
    {
        return Occasion::query()->where('household_id', $user->household_id)->findOrFail($id);
    }

    /**
     * @return array<string, Collection<int, Recipient>>
     */
    public function groupedPeople(Occasion $occasion): array
    {
        return $this->recipients->groupPeople($occasion->recipients()->get());
    }

    public function totalBudgetCents(Occasion $occasion): int
    {
        // Query (not the pivot) so MariaDB's decimal-string SUM is cast to int.
        return (int) $occasion->recipients()->sum('occasion_recipient.budget_cents');
    }

    /**
     * @return Collection<int, Recipient>
     */
    public function availablePeople(User $user, Occasion $occasion): Collection
    {
        return Recipient::query()
            ->where('household_id', $user->household_id)
            ->whereNull('archived_at')
            ->whereNotIn('id', $occasion->recipients()->select('recipients.id'))
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
