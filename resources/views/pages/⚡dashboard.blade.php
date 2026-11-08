<?php

use App\Models\Household;
use App\Services\Dashboard;
use App\Services\Reminders;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use App\Support\Price;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    #[Computed]
    public function household(): Household
    {
        return Auth::user()->loadMissing('household.users')->household;
    }

    /**
     * @return array{birthdays: list<array<string, mixed>>, noGift: list<array<string, mixed>>}
     */
    #[Computed]
    public function reminders(): array
    {
        return app(Reminders::class)->for(Auth::user(), CarbonImmutable::today());
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function christmas(): ?array
    {
        return app(Dashboard::class)->christmas(Auth::user(), CarbonImmutable::today());
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function upcoming(): array
    {
        return app(Dashboard::class)->upcoming(Auth::user(), CarbonImmutable::today());
    }

    public function money(int $spent, int $budget): string
    {
        return $budget > 0
            ? __(':spent of :budget spent', ['spent' => Price::format($spent), 'budget' => Price::format($budget)])
            : __(':spent spent · No budget', ['spent' => Price::format($spent)]);
    }

    public function when(int $days, CarbonInterface $date): string
    {
        return match (true) {
            $days < 0 => $date->format('M j, Y'),
            $days === 0 => __('today'),
            $days === 1 => __('tomorrow'),
            default => __('in :n days (:date)', ['n' => $days, 'date' => $date->format('M j')]),
        };
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div>
        <flux:heading size="xl" level="1" data-household-name>{{ $this->household->name }}</flux:heading>
        <flux:text>
            <span data-household-members>{{ $this->household->users->pluck('name')->join(', ') }}</span>
            · <flux:link :href="route('household.edit')" wire:navigate>{{ __('Manage household') }}</flux:link>
        </flux:text>
    </div>
    <div class="flex flex-col gap-3">
        @foreach ($this->reminders['birthdays'] as $birthday)
            <flux:callout data-reminder-birthday="{{ $birthday['name'] }}" data-days="{{ $birthday['days'] }}" :data-needs-gift="$birthday['needsGift'] ? '' : null">
                <flux:callout.text>
                    @php($text = __(':name\'s birthday is :when', ['name' => $birthday['name'], 'when' => $this->when($birthday['days'], $birthday['date'])]))
                    @if ($birthday['occasionId'])
                        <flux:link :href="route('occasions.show', $birthday['occasionId'])" wire:navigate>{{ $text }}</flux:link>
                    @else
                        {{ $text }}
                    @endif
                    @if ($birthday['needsGift']) — {{ __('no gift yet') }} @endif
                </flux:callout.text>
            </flux:callout>
        @endforeach
        @foreach ($this->reminders['noGift'] as $row)
            <flux:callout data-reminder-no-gift="{{ $row['name'] }}" data-people="{{ implode(', ', $row['people']) }}">
                <flux:callout.text>
                    <flux:link :href="route('occasions.show', $row['occasionId'])" wire:navigate>{{ $row['name'] }}</flux:link>
                    ({{ $this->when($row['days'], $row['date']) }}):
                    {{ count($row['people']) }} {{ trans_choice('person|people', count($row['people'])) }} {{ __('with no gift yet') }} — {{ implode(', ', $row['people']) }}
                </flux:callout.text>
            </flux:callout>
        @endforeach
        @if ($this->reminders['birthdays'] === [] && $this->reminders['noGift'] === [])
            <flux:text data-all-caught-up>{{ __('All caught up.') }}</flux:text>
        @endif
    </div>

    @if ($card = $this->christmas)
        <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-christmas="{{ $card['occasion']->name }}">
            <div>
                <flux:heading level="2"><flux:link :href="route('occasions.show', $card['occasion'])" wire:navigate>{{ $card['occasion']->name }}</flux:link></flux:heading>
                <flux:text>{{ $this->when($card['days'], $card['occasion']->date) }}</flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:text data-christmas-spent="{{ Price::format($card['spent']) }}" data-christmas-budget="{{ Price::format($card['budget']) }}">{{ $this->money($card['spent'], $card['budget']) }}</flux:text>
                @if ($card['budget'] > 0 && $card['remaining'] < 0)
                    <flux:badge size="sm" color="red" data-over-budget>{{ __(':amount over', ['amount' => Price::format(-$card['remaining'])]) }}</flux:badge>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach (['idea' => 'Ideas', 'bought' => 'Bought', 'wrapped' => 'Wrapped', 'given' => 'Given'] as $status => $label)
                    <flux:badge size="sm" data-status-count="{{ $status }}">{{ __($label) }} {{ $card['counts'][$status] }}</flux:badge>
                @endforeach
            </div>
            @if ($card['privateSpent'] !== null)
                <flux:text data-my-private-spent="{{ Price::format($card['privateSpent']) }}">{{ __('My private gifts: :amount spent', ['amount' => Price::format($card['privateSpent'])]) }}</flux:text>
            @endif
            <div class="flex items-center gap-2">
                <flux:button :href="route('occasions.shopping', $card['occasion'])" wire:navigate>{{ __('Shopping list') }}</flux:button>
                <flux:button :href="route('occasions.wrapping', $card['occasion'])" wire:navigate>{{ __('Wrapping checklist') }}</flux:button>
            </div>
        </div>
    @else
        <flux:text>{{ __('No Christmas yet.') }} <flux:link :href="route('occasions.index')" wire:navigate>{{ __('Start Christmas') }}</flux:link></flux:text>
    @endif

    <div class="flex flex-col gap-3">
        <flux:heading level="2">{{ __('Coming up') }}</flux:heading>
        @forelse ($this->upcoming as $row)
            <div class="flex flex-wrap items-center gap-2" data-upcoming="{{ $row['name'] }}" data-days="{{ $row['days'] }}" data-spent="{{ Price::format($row['spent']) }}" data-budget="{{ Price::format($row['budget']) }}">
                <flux:link :href="route('occasions.show', $row['occasionId'])" wire:navigate>{{ $row['name'] }}</flux:link>
                <flux:text>{{ $this->when($row['days'], $row['date']) }}</flux:text>
                <flux:text>{{ $this->money($row['spent'], $row['budget']) }}</flux:text>
                @if ($row['budget'] > 0 && $row['remaining'] < 0)
                    <flux:badge size="sm" color="red">{{ __(':amount over', ['amount' => Price::format(-$row['remaining'])]) }}</flux:badge>
                @endif
            </div>
        @empty
            <flux:text>{{ __('Nothing else coming up in the next 60 days.') }}</flux:text>
        @endforelse
    </div>
</div>
