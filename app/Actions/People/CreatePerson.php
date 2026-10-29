<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Models\Recipient;
use App\Models\User;

class CreatePerson
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): Recipient
    {
        $person = new Recipient(array_map(fn ($value) => $value === '' ? null : $value, $data));
        $person->household_id = $user->household_id;
        $person->save();

        return $person;
    }
}
