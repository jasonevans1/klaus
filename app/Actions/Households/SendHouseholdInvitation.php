<?php

declare(strict_types=1);

namespace App\Actions\Households;

use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Notifications\HouseholdInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SendHouseholdInvitation
{
    public function handle(User $inviter, string $email): HouseholdInvitation
    {
        $email = Str::lower($email);

        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'That email already has a Klaus account.']);
        }

        if (! RateLimiter::attempt('household-invite:'.$inviter->id, 10, fn () => true, 3600)) {
            throw ValidationException::withMessages(['email' => 'Too many invites sent. Try again later.']);
        }

        return DB::transaction(function () use ($inviter, $email) {
            $household = $inviter->household;

            $household->pendingInvitations()->where('email', $email)->update(['revoked_at' => now()]);

            $token = Str::random(64);
            $invitation = $household->invitations()->create([
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'invited_by_user_id' => $inviter->id,
                'expires_at' => now()->addDays(7),
            ]);

            Notification::route('mail', $email)->notify(new HouseholdInvitationNotification($invitation, $token));

            return $invitation;
        });
    }
}
