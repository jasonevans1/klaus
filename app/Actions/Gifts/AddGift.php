<?php

declare(strict_types=1);

namespace App\Actions\Gifts;

use App\Models\Gift;
use App\Models\User;
use App\Repositories\OccasionRepository;

class AddGift
{
    public function __construct(private readonly OccasionRepository $occasions) {}

    /**
     * @param  array{title: string, price_cents: int|null, store: string|null, url: string|null}  $data
     */
    public function handle(User $user, int $occasionId, int $recipientId, array $data): Gift
    {
        $occasion = $this->occasions->findForUser($user, $occasionId);
        $occasion->recipients()->whereKey($recipientId)->firstOrFail();

        $gift = new Gift($data);
        $gift->household_id = $occasion->household_id;
        $gift->recipient_id = $recipientId;
        $gift->occasion_id = $occasion->id;
        $gift->created_by_user_id = $user->id;
        $gift->save();

        return $gift;
    }
}
