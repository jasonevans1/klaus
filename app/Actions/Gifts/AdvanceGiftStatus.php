<?php

declare(strict_types=1);

namespace App\Actions\Gifts;

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\User;
use App\Repositories\GiftRepository;

class AdvanceGiftStatus
{
    public function __construct(private readonly GiftRepository $gifts) {}

    public function handle(User $user, int $giftId, GiftStatus $from): Gift
    {
        $gift = $this->gifts->findForUser($user, $giftId);
        $next = $gift->status->next();

        // A stale page or double-click must not skip a step.
        if ($gift->status !== $from || $next === null) {
            return $gift;
        }

        if ($gift->status === GiftStatus::Idea) {
            $gift->purchased_by_user_id = $user->id;
        }

        $gift->status = $next;
        $gift->save();

        return $gift;
    }
}
