<?php

declare(strict_types=1);

namespace App\Actions\Occasions;

use App\Models\Recipient;
use App\Models\User;
use App\Repositories\OccasionRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AddPeopleToOccasion
{
    public function __construct(private readonly OccasionRepository $occasions) {}

    /**
     * @param  array<int, int>  $recipientIds
     */
    public function handle(User $user, int $occasionId, array $recipientIds): int
    {
        $occasion = $this->occasions->findForUser($user, $occasionId);
        $ids = array_unique($recipientIds);
        $people = Recipient::query()->where('household_id', $user->household_id)->whereIn('id', $ids)->get();
        if ($people->count() !== count($ids)) {
            throw (new ModelNotFoundException)->setModel(Recipient::class, $ids);
        }

        $toAdd = $people->whereNull('archived_at')
            ->whereNotIn('id', $occasion->recipients()->pluck('recipients.id'))
            ->pluck('id');
        $occasion->recipients()->attach($toAdd->all());

        return $toAdd->count();
    }
}
