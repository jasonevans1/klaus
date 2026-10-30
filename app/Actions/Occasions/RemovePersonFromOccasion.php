<?php

declare(strict_types=1);

namespace App\Actions\Occasions;

use App\Models\User;
use App\Repositories\OccasionRepository;

class RemovePersonFromOccasion
{
    public function __construct(private readonly OccasionRepository $occasions) {}

    public function handle(User $user, int $occasionId, int $recipientId): void
    {
        $this->occasions->findForUser($user, $occasionId)->recipients()->detach($recipientId);
    }
}
