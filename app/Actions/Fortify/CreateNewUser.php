<?php

namespace App\Actions\Fortify;

use App\Actions\Households\AcceptHouseholdInvitation;
use App\Actions\Households\CreateHousehold;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        if (! empty($input['invitation'])) {
            return $this->createFromInvitation($input);
        }

        // No-invite path: the user gets a household of their own.
        return DB::transaction(function () use ($input): User {
            $household = (new CreateHousehold)->handle($input['name']);

            $user = new User;
            $user->forceFill([
                'household_id' => $household->id,
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ])->save();

            return $user;
        });
    }

    /**
     * @param  array<string, string>  $input
     */
    private function createFromInvitation(array $input): User
    {
        $invitation = HouseholdInvitation::findPendingByToken($input['invitation']);

        if ($invitation === null) {
            throw ValidationException::withMessages(['email' => HouseholdInvitation::INVALID_MESSAGE]);
        }

        if (strtolower($input['email']) !== strtolower($invitation->email)) {
            throw ValidationException::withMessages(['email' => 'Use the email address the invite was sent to.']);
        }

        return DB::transaction(function () use ($input, $invitation): User {
            $user = new User;
            $user->forceFill([
                'household_id' => $invitation->household_id,
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'email_verified_at' => now(),
            ])->save();

            (new AcceptHouseholdInvitation)->handle($invitation);

            return $user;
        });
    }
}
