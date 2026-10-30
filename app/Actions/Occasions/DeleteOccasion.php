<?php

declare(strict_types=1);

namespace App\Actions\Occasions;

use App\Models\User;
use App\Repositories\OccasionRepository;

class DeleteOccasion
{
    public function __construct(private readonly OccasionRepository $occasions) {}

    public function handle(User $user, int $occasionId): void
    {
        $this->occasions->findForUser($user, $occasionId)->delete();
    }
}
