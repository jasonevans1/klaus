<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\RecipientGroup;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class RecipientRepository
{
    /**
     * @return array<string, Collection<int, Recipient>>
     */
    public function groupedForUser(User $user, bool $includeArchived = false): array
    {
        $people = Recipient::query()
            ->where('household_id', $user->household_id)
            ->unless($includeArchived, fn ($query) => $query->whereNull('archived_at'))
            ->get();

        $grouped = [];
        foreach (RecipientGroup::cases() as $group) {
            $members = $people->where('group', $group)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
            if ($members->isNotEmpty()) {
                $grouped[$group->value] = $members->values();
            }
        }

        return $grouped;
    }

    public function findForUser(User $user, int $id): Recipient
    {
        return Recipient::query()->where('household_id', $user->household_id)->findOrFail($id);
    }
}
