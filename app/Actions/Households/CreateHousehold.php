<?php

declare(strict_types=1);

namespace App\Actions\Households;

use App\Models\Household;

class CreateHousehold
{
    public function handle(string $name): Household
    {
        return Household::create(['name' => "{$name}'s household"]);
    }
}
