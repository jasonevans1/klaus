<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Models\User;
use App\Repositories\RecipientRepository;

class ArchivePerson
{
    public function __construct(private readonly RecipientRepository $people) {}

    public function handle(User $user, int $recipientId): void
    {
        $this->people->findForUser($user, $recipientId)->forceFill(['archived_at' => now()])->save();
    }
}
