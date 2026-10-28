<?php

declare(strict_types=1);

namespace App\Actions\Households;

use App\Models\HouseholdInvitation;
use Illuminate\Validation\ValidationException;

class AcceptHouseholdInvitation
{
    /**
     * Atomically use up the invitation; a concurrent or repeat accept affects 0 rows and fails.
     */
    public function handle(HouseholdInvitation $invitation): void
    {
        $accepted = HouseholdInvitation::whereKey($invitation->id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->update(['accepted_at' => now()]);

        if ($accepted === 0) {
            throw ValidationException::withMessages(['email' => HouseholdInvitation::INVALID_MESSAGE]);
        }
    }
}
