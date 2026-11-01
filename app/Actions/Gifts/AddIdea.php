<?php

declare(strict_types=1);

namespace App\Actions\Gifts;

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\User;
use App\Repositories\RecipientRepository;
use Illuminate\Support\Arr;

class AddIdea
{
    public function __construct(private readonly RecipientRepository $recipients) {}

    /**
     * @param  array{title: string, price_cents: int|null, store: string|null, url: string|null, is_private: bool}  $data
     */
    public function handle(User $user, int $recipientId, array $data): Gift
    {
        $recipient = $this->recipients->findForUser($user, $recipientId);

        $gift = new Gift(Arr::only($data, ['title', 'price_cents', 'store', 'url']));
        $gift->status = GiftStatus::Idea;
        $gift->is_private = $data['is_private'];
        $gift->household_id = $recipient->household_id;
        $gift->recipient_id = $recipient->id;
        $gift->created_by_user_id = $user->id;
        $gift->save();

        return $gift;
    }
}
