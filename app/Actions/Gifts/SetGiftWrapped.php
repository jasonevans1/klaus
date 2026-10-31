<?php

declare(strict_types=1);

namespace App\Actions\Gifts;

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\User;
use App\Repositories\GiftRepository;

class SetGiftWrapped
{
    public function __construct(private readonly GiftRepository $gifts) {}

    public function handle(User $user, int $giftId, bool $wrapped): Gift
    {
        $gift = $this->gifts->findForUser($user, $giftId);

        $from = $wrapped ? GiftStatus::Bought : GiftStatus::Wrapped;

        // Only Bought <-> Wrapped; a stale page or double click must not move anything else.
        if ($gift->status !== $from) {
            return $gift;
        }

        $gift->status = $wrapped ? GiftStatus::Wrapped : GiftStatus::Bought;
        $gift->save();

        return $gift;
    }
}
