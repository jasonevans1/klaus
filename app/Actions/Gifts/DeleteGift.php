<?php

declare(strict_types=1);

namespace App\Actions\Gifts;

use App\Models\User;
use App\Repositories\GiftRepository;

class DeleteGift
{
    public function __construct(private readonly GiftRepository $gifts) {}

    public function handle(User $user, int $giftId): void
    {
        $this->gifts->findForUser($user, $giftId)->delete();
    }
}
