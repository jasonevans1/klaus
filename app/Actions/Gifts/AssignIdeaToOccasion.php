<?php

declare(strict_types=1);

namespace App\Actions\Gifts;

use App\Models\Gift;
use App\Models\User;
use App\Repositories\GiftRepository;
use App\Repositories\OccasionRepository;

class AssignIdeaToOccasion
{
    public function __construct(private readonly GiftRepository $gifts, private readonly OccasionRepository $occasions) {}

    public function handle(User $user, int $giftId, int $occasionId): Gift
    {
        $gift = $this->gifts->findForUser($user, $giftId);

        // A stale page or double-click must not move a gift twice.
        if ($gift->occasion_id !== null) {
            return $gift;
        }

        $occasion = $this->occasions->findForUser($user, $occasionId);
        $occasion->recipients()->whereKey($gift->recipient_id)->firstOrFail();

        $gift->occasion_id = $occasion->id;
        $gift->save();

        return $gift;
    }
}
