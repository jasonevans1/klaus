<?php

declare(strict_types=1);

namespace App\Actions\Gifts;

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\User;
use App\Repositories\OccasionRepository;
use Illuminate\Support\Arr;

class AddGift
{
    public function __construct(private readonly OccasionRepository $occasions, private readonly ResolveBuyer $resolveBuyer) {}

    /**
     * @param  array{title: string, price_cents: int|null, store: string|null, url: string|null, status: GiftStatus, purchased_by_user_id: int|null}  $data
     */
    public function handle(User $user, int $occasionId, int $recipientId, array $data): Gift
    {
        $occasion = $this->occasions->findForUser($user, $occasionId);
        $occasion->recipients()->whereKey($recipientId)->firstOrFail();

        $gift = new Gift(Arr::only($data, ['title', 'price_cents', 'store', 'url']));
        $gift->status = $data['status'];
        $gift->purchased_by_user_id = $this->resolveBuyer->handle($user, $data['status'], $data['purchased_by_user_id']);
        $gift->household_id = $occasion->household_id;
        $gift->recipient_id = $recipientId;
        $gift->occasion_id = $occasion->id;
        $gift->created_by_user_id = $user->id;
        $gift->save();

        return $gift;
    }
}
