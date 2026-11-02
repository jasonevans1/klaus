<?php

declare(strict_types=1);

namespace App\Actions\Occasions;

use App\Enums\OccasionType;
use App\Models\Occasion;
use App\Models\Recipient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateUpcomingBirthdays
{
    public function handle(CarbonImmutable $today): int
    {
        $created = 0;

        foreach (Recipient::query()->whereNull('archived_at')->whereNotNull('birthday_month')->whereNotNull('birthday_day')->get() as $recipient) {
            $date = $this->birthdayIn($today->year, $recipient);

            if ($date->lessThan($today->startOfDay())) {
                $date = $this->birthdayIn($today->year + 1, $recipient);
            }

            if ($date->greaterThan($today->addDays(30)) || $recipient->last_birthday_year === $date->year) {
                continue;
            }

            $budgetCents = Occasion::query()
                ->where('birthday_recipient_id', $recipient->id)
                ->orderByDesc('date')
                ->first()
                ?->recipients()->whereKey($recipient->id)->first()?->pivot->getAttribute('budget_cents');

            DB::transaction(function () use ($recipient, $date, $budgetCents): void {
                $occasion = new Occasion([
                    'type' => OccasionType::Birthday,
                    'name' => $this->occasionName($recipient->name, $date->year),
                    'date' => $date,
                ]);
                $occasion->household_id = $recipient->household_id;
                $occasion->birthday_recipient_id = $recipient->id;
                $occasion->save();
                $occasion->recipients()->attach($recipient, ['budget_cents' => $budgetCents]);

                $recipient->last_birthday_year = $date->year;
                $recipient->save();
            });
            $created++;
        }

        return $created;
    }

    private function birthdayIn(int $year, Recipient $recipient): CarbonImmutable
    {
        $day = checkdate($recipient->birthday_month, $recipient->birthday_day, $year) ? $recipient->birthday_day : 28;

        return CarbonImmutable::create($year, $recipient->birthday_month, $day);
    }

    private function occasionName(string $name, int $year): string
    {
        $ending = (str_ends_with(mb_strtolower($name), 's') ? "'" : "'s")." birthday {$year}";

        return mb_substr($name, 0, 100 - mb_strlen($ending)).$ending;
    }
}
