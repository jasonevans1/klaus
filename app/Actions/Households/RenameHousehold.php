<?php

declare(strict_types=1);

namespace App\Actions\Households;

use App\Models\User;
use Illuminate\Support\Facades\Validator;

class RenameHousehold
{
    public function handle(User $user, string $name): void
    {
        $validated = Validator::make(['name' => $name], ['name' => 'required|string|max:100'])->validate();

        $user->household->update(['name' => $validated['name']]);
    }
}
