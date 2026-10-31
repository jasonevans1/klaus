<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GiftRepository
{
    public function findForUser(User $user, int $id): Gift
    {
        // ponytail: private-gift filtering (feature 10) goes in here and in byPerson.
        return Gift::query()->where('household_id', $user->household_id)->findOrFail($id);
    }

    /**
     * @return array<int, Collection<int, Gift>>
     */
    public function byPerson(User $user, Occasion $occasion): array
    {
        return Gift::query()
            ->where('household_id', $user->household_id)
            ->where('occasion_id', $occasion->id)
            ->with('purchasedBy')
            ->orderBy('id')
            ->get()
            ->groupBy('recipient_id')
            ->all();
    }

    /**
     * @return Collection<int, Gift>
     */
    public function ideas(User $user, Occasion $occasion): Collection
    {
        // ponytail: private-gift filtering (feature 10) goes in here too.
        return Gift::query()
            ->where('household_id', $user->household_id)
            ->where('occasion_id', $occasion->id)
            ->where('status', GiftStatus::Idea)
            ->with('recipient')
            ->orderBy('id')
            ->get()
            ->sortBy('recipient.name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function buyers(User $user): Collection
    {
        return User::query()->where('household_id', $user->household_id)->orderBy('name')->get();
    }
}
