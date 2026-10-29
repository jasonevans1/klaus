<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Models\Recipient;
use App\Models\User;
use App\Repositories\RecipientRepository;

class UpdatePerson
{
    public function __construct(private readonly RecipientRepository $people) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, int $recipientId, array $data): Recipient
    {
        $person = $this->people->findForUser($user, $recipientId);
        $person->update(array_map(fn ($value) => $value === '' ? null : $value, $data));

        return $person;
    }
}
