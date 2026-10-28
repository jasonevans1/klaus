<?php

declare(strict_types=1);

namespace App\Actions\Households;

use App\Models\User;

class RevokeHouseholdInvitation
{
    public function handle(User $user, int $invitationId): void
    {
        $user->household->pendingInvitations()->findOrFail($invitationId)->update(['revoked_at' => now()]);
    }
}
