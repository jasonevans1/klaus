<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GiftRepository
{
    public function findForUser(User $user, int $id): Gift
    {
        return Gift::query()->visibleTo($user)->findOrFail($id);
    }

    /**
     * @return array<int, Collection<int, Gift>>
     */
    public function byPerson(User $user, Occasion $occasion): array
    {
        return Gift::query()
            ->visibleTo($user)
            ->where('occasion_id', $occasion->id)
            ->with('purchasedBy')
            ->orderBy('id')
            ->get()
            ->groupBy('recipient_id')
            ->all();
    }

    /**
     * @return array<int, Collection<int, Gift>>
     */
    public function yearRoundIdeas(User $user): array
    {
        return Gift::query()
            ->visibleTo($user)
            ->whereNull('occasion_id')
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
        return $this->withStatuses($user, $occasion, [GiftStatus::Idea]);
    }

    /**
     * @return Collection<int, Gift>
     */
    public function toWrap(User $user, Occasion $occasion): Collection
    {
        return $this->withStatuses($user, $occasion, [GiftStatus::Bought, GiftStatus::Wrapped]);
    }

    /**
     * @param  array<int, GiftStatus>  $statuses
     * @return Collection<int, Gift>
     */
    private function withStatuses(User $user, Occasion $occasion, array $statuses): Collection
    {
        return Gift::query()
            ->visibleTo($user)
            ->where('occasion_id', $occasion->id)
            ->whereIn('status', $statuses)
            ->with('recipient')
            ->orderBy('id')
            ->get()
            ->sortBy('recipient.name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return Collection<int, Gift>
     */
    public function historyFor(User $user, Recipient $person): Collection
    {
        // No join on occasions: visibleTo uses an unqualified household_id.
        return Gift::query()
            ->visibleTo($user)
            ->where('recipient_id', $person->id)
            ->whereNotNull('occasion_id')
            ->whereIn('status', [GiftStatus::Bought, GiftStatus::Wrapped, GiftStatus::Given])
            ->with(['occasion', 'purchasedBy'])
            ->orderByDesc(Occasion::query()->select('date')->whereColumn('occasions.id', 'gifts.occasion_id'))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function buyers(User $user): Collection
    {
        return User::query()->where('household_id', $user->household_id)->orderBy('name')->get();
    }
}
