<?php

declare(strict_types=1);

namespace App\Actions\Occasions;

use App\Models\Gift;
use App\Models\User;
use App\Repositories\OccasionRepository;
use Illuminate\Support\Facades\DB;

class RemovePersonFromOccasion
{
    public function __construct(private readonly OccasionRepository $occasions) {}

    public function handle(User $user, int $occasionId, int $recipientId): void
    {
        $occasion = $this->occasions->findForUser($user, $occasionId);

        DB::transaction(function () use ($occasion, $recipientId): void {
            Gift::query()->where('occasion_id', $occasion->id)->where('recipient_id', $recipientId)->delete();
            $occasion->recipients()->detach($recipientId);
        });
    }
}
