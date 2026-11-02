<x-layouts::app :title="__('Dashboard')">
    @php
        $household = auth()->user()->loadMissing('household.users')->household;
        $reminders = app(\App\Services\Reminders::class)->for(auth()->user(), \Carbon\CarbonImmutable::today());
        $when = fn (int $days, $date) => match ($days) {
            0 => __('today'),
            1 => __('tomorrow'),
            default => __('in :n days (:date)', ['n' => $days, 'date' => $date->format('M j')]),
        };
    @endphp
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div>
            <flux:heading size="xl" level="1" data-household-name>{{ $household->name }}</flux:heading>
            <flux:text>
                <span data-household-members>{{ $household->users->pluck('name')->join(', ') }}</span>
                · <flux:link :href="route('household.edit')" wire:navigate>{{ __('Manage household') }}</flux:link>
            </flux:text>
        </div>
        <div class="flex flex-col gap-3">
            @foreach ($reminders['birthdays'] as $birthday)
                <flux:callout data-reminder-birthday="{{ $birthday['name'] }}" data-days="{{ $birthday['days'] }}" :data-needs-gift="$birthday['needsGift'] ? '' : null">
                    <flux:callout.text>
                        @php($text = __(':name\'s birthday is :when', ['name' => $birthday['name'], 'when' => $when($birthday['days'], $birthday['date'])]))
                        @if ($birthday['occasionId'])
                            <flux:link :href="route('occasions.show', $birthday['occasionId'])" wire:navigate>{{ $text }}</flux:link>
                        @else
                            {{ $text }}
                        @endif
                        @if ($birthday['needsGift']) — {{ __('no gift yet') }} @endif
                    </flux:callout.text>
                </flux:callout>
            @endforeach
            @foreach ($reminders['noGift'] as $row)
                <flux:callout data-reminder-no-gift="{{ $row['name'] }}" data-people="{{ implode(', ', $row['people']) }}">
                    <flux:callout.text>
                        <flux:link :href="route('occasions.show', $row['occasionId'])" wire:navigate>{{ $row['name'] }}</flux:link>
                        ({{ $when($row['days'], $row['date']) }}):
                        {{ count($row['people']) }} {{ trans_choice('person|people', count($row['people'])) }} {{ __('with no gift yet') }} — {{ implode(', ', $row['people']) }}
                    </flux:callout.text>
                </flux:callout>
            @endforeach
            @if ($reminders['birthdays'] === [] && $reminders['noGift'] === [])
                <flux:text data-all-caught-up>{{ __('All caught up.') }}</flux:text>
            @endif
        </div>
    </div>
</x-layouts::app>
