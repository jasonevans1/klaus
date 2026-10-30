<?php

declare(strict_types=1);

namespace App\Actions\Occasions;

use App\Models\User;
use App\Repositories\OccasionRepository;

class SetBudget
{
    public function __construct(private readonly OccasionRepository $occasions) {}

    public function handle(User $user, int $occasionId, int $recipientId, ?int $budgetCents): void
    {
        $occasion = $this->occasions->findForUser($user, $occasionId);
        $occasion->recipients()->whereKey($recipientId)->firstOrFail();
        $occasion->recipients()->updateExistingPivot($recipientId, ['budget_cents' => $budgetCents]);
    }
}
