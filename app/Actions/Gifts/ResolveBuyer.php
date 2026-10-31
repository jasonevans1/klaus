<?php

declare(strict_types=1);

namespace App\Actions\Gifts;

use App\Enums\GiftStatus;
use App\Models\User;

class ResolveBuyer
{
    public function handle(User $user, GiftStatus $status, ?int $buyerId): ?int
    {
        if ($status === GiftStatus::Idea) {
            return null;
        }

        if ($buyerId === null) {
            return $user->id;
        }

        return $user->household->users()->whereKey($buyerId)->firstOrFail()->id;
    }
}
