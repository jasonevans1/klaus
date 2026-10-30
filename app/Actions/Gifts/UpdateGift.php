<?php

declare(strict_types=1);

namespace App\Actions\Gifts;

use App\Models\Gift;
use App\Models\User;
use App\Repositories\GiftRepository;

class UpdateGift
{
    public function __construct(private readonly GiftRepository $gifts) {}

    /**
     * @param  array{title: string, price_cents: int|null, store: string|null, url: string|null}  $data
     */
    public function handle(User $user, int $giftId, array $data): Gift
    {
        $gift = $this->gifts->findForUser($user, $giftId);
        $gift->update($data);

        return $gift;
    }
}
