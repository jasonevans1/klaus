<?php

declare(strict_types=1);

namespace App\Actions\Households;

use App\Models\HouseholdInvitation;
use App\Models\User;

class ResendHouseholdInvitation
{
    public function __construct(private SendHouseholdInvitation $sendInvitation) {}

    public function handle(User $user, int $invitationId): HouseholdInvitation
    {
        $invitation = $user->household->pendingInvitations()->findOrFail($invitationId);

        return $this->sendInvitation->handle($user, $invitation->email);
    }
}
