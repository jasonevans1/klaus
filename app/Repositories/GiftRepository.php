<?php

declare(strict_types=1);

namespace App\Repositories;

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
            ->orderBy('id')
            ->get()
            ->groupBy('recipient_id')
            ->all();
    }
}
