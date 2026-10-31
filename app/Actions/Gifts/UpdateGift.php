<?php

declare(strict_types=1);

namespace App\Actions\Gifts;

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\User;
use App\Repositories\GiftRepository;
use Illuminate\Support\Arr;

class UpdateGift
{
    public function __construct(private readonly GiftRepository $gifts, private readonly ResolveBuyer $resolveBuyer) {}

    /**
     * @param  array{title: string, price_cents: int|null, store: string|null, url: string|null, status: GiftStatus, purchased_by_user_id: int|null}  $data
     */
    public function handle(User $user, int $giftId, array $data): Gift
    {
        $gift = $this->gifts->findForUser($user, $giftId);
        $gift->fill(Arr::only($data, ['title', 'price_cents', 'store', 'url']));
        $gift->status = $data['status'];
        $gift->purchased_by_user_id = $this->resolveBuyer->handle($user, $data['status'], $data['purchased_by_user_id']);
        $gift->save();

        return $gift;
    }
}
