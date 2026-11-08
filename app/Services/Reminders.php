<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OccasionType;
use App\Models\Occasion;
use App\Models\User;
use App\Repositories\GiftRepository;
use App\Repositories\OccasionRepository;
use App\Repositories\RecipientRepository;
use Carbon\CarbonImmutable;

class Reminders
{
    public function __construct(private readonly OccasionRepository $occasionRepository, private readonly RecipientRepository $recipients, private readonly GiftRepository $gifts, private readonly BudgetSummary $budget) {}

    /**
     * @return array{birthdays: list<array{name: string, date: CarbonImmutable, days: int, occasionId: int|null, needsGift: bool}>, noGift: list<array{occasionId: int, name: string, date: CarbonImmutable, days: int, people: list<string>}>}
     */
    public function for(User $user, CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $occasions = $this->occasionRepository->upcoming($user, $today, 30);
        $limit = $today->addDays(30);
        $yearly = [];

        $birthdays = [];
        $noGiftByOccasion = [];
        foreach ($this->recipients->withBirthdays($user) as $person) {
            $date = $person->nextBirthday($today);
            if ($date === null || $date > $limit) {
                continue;
            }
            $occasion = $yearly[$date->year] ??= $this->occasionRepository->birthdaysFor($user, $date->year);
            $onOccasion = $occasion?->recipients()->whereKey($person->id)->exists() ?? false;
            $occasion = $onOccasion ? $occasion : null;
            $needsGift = $occasion !== null && in_array($person->name, $noGiftByOccasion[$occasion->id] ??= $this->noGiftNames($user, $occasion), true);
            $birthdays[] = ['name' => $person->name, 'date' => $date, 'days' => (int) $today->diffInDays($date), 'occasionId' => $occasion?->id, 'needsGift' => $needsGift];
        }
        usort($birthdays, fn (array $a, array $b): int => [$a['date'], $a['name']] <=> [$b['date'], $b['name']]);

        $noGift = [];
        foreach ($occasions->whereNull('birthday_recipient_id')->where('type', '!=', OccasionType::Birthdays) as $occasion) {
            $people = $this->noGiftNames($user, $occasion);
            if ($people !== []) {
                $noGift[] = ['occasionId' => $occasion->id, 'name' => $occasion->name, 'date' => $occasion->date->toImmutable(), 'days' => (int) $today->diffInDays($occasion->date), 'people' => $people];
            }
        }

        return ['birthdays' => $birthdays, 'noGift' => $noGift];
    }

    /**
     * @return list<string>
     */
    private function noGiftNames(User $user, Occasion $occasion): array
    {
        return $this->budget->summarize($occasion->recipients()->get(), $this->gifts->byPerson($user, $occasion))['noGift'];
    }
}
