<?php

use App\Models\Recipient;
use App\Repositories\GiftRepository;
use App\Repositories\RecipientRepository;
use App\Support\Price;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $personId;

    public function mount(int $person): void
    {
        $this->personId = app(RecipientRepository::class)->findForUser(Auth::user(), $person)->id;
    }

    #[Computed]
    public function person(): Recipient
    {
        return app(RecipientRepository::class)->findForUser(Auth::user(), $this->personId);
    }

    #[Computed]
    public function ideas()
    {
        return app(GiftRepository::class)->yearRoundIdeas(Auth::user())[$this->personId] ?? [];
    }

    #[Computed]
    public function history()
    {
        return app(GiftRepository::class)->historyFor(Auth::user(), $this->person)
            ->groupBy(fn ($gift) => $gift->occasion->date->year)
            ->map(fn ($gifts) => $gifts->groupBy('occasion_id'));
    }
}; ?>

<section class="w-full space-y-6 p-6">
    <div class="space-y-2">
        <flux:heading size="xl" level="1">{{ $this->person->name }}</flux:heading>
        <div class="flex items-center gap-2">
            <flux:text size="sm">{{ $this->person->group->label() }}</flux:text>
            @if ($this->person->is_shared)
                <flux:badge size="sm">{{ __('Shared') }}</flux:badge>
            @endif
            @if ($this->person->archived_at)
                <flux:badge size="sm" color="zinc">{{ __('Archived') }}</flux:badge>
            @endif
        </div>
        @if ($this->person->birthdayLabel())
            <flux:text data-profile-birthday="{{ $this->person->birthdayLabel() }}">{{ __('Birthday') }}: {{ $this->person->birthdayLabel() }}</flux:text>
        @endif
        @if ($this->person->age() !== null)
            <flux:text data-profile-age="{{ $this->person->age() }}">{{ __('Age') }}: {{ $this->person->age() }}</flux:text>
        @endif
        @if ($this->person->interests)
            <flux:text data-profile-interests="{{ $this->person->interests }}" class="whitespace-pre-line">{{ $this->person->interests }}</flux:text>
        @endif
    </div>

    <div class="space-y-2">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('Ideas') }}</flux:heading>
            <flux:link :href="route('ideas.index')" wire:navigate>{{ __('Manage ideas') }}</flux:link>
        </div>
        @forelse ($this->ideas as $idea)
            <div wire:key="idea-{{ $idea->id }}" data-idea="{{ $idea->title }}" data-price="{{ Price::format($idea->price_cents) }}" data-store="{{ $idea->store }}" data-private="{{ $idea->is_private ? 'yes' : 'no' }}" class="flex items-center gap-4">
                <flux:text variant="strong">{{ $idea->title }}</flux:text>
                <flux:text size="sm">{{ Price::format($idea->price_cents) }}</flux:text>
                @if ($idea->store)
                    <flux:text size="sm">{{ $idea->store }}</flux:text>
                @endif
                @if ($idea->is_private)
                    <flux:badge size="sm" color="purple">{{ __('Private') }}</flux:badge>
                @endif
            </div>
        @empty
            <flux:text>{{ __('No ideas yet.') }}</flux:text>
        @endforelse
    </div>

    <div class="space-y-4">
        <flux:heading size="lg">{{ __('History') }}</flux:heading>
        @forelse ($this->history as $year => $occasions)
            <div wire:key="year-{{ $year }}" data-year="{{ $year }}" class="space-y-2">
                <flux:heading>{{ $year }}</flux:heading>
                @foreach ($occasions as $occasionId => $gifts)
                    @php($occasion = $gifts->first()->occasion)
                    <div wire:key="history-occasion-{{ $occasionId }}" data-history-occasion="{{ $occasion->name }}" class="space-y-1">
                        <div class="flex items-center gap-2">
                            <flux:link :href="route('occasions.show', $occasion)" wire:navigate>{{ $occasion->name }}</flux:link>
                            <flux:text size="sm">{{ $occasion->date->format('M j, Y') }}</flux:text>
                        </div>
                        @foreach ($gifts as $gift)
                            <div wire:key="history-gift-{{ $gift->id }}" data-history-gift="{{ $gift->title }}" data-price="{{ Price::format($gift->price_cents) }}" data-status="{{ $gift->status->label() }}" data-buyer="{{ $gift->purchasedBy?->name }}" data-private="{{ $gift->is_private ? 'yes' : 'no' }}" class="flex items-center gap-4">
                                <flux:text variant="strong">{{ $gift->title }}</flux:text>
                                <flux:text size="sm">{{ Price::format($gift->price_cents) }}</flux:text>
                                <flux:text size="sm">{{ $gift->status->label() }}</flux:text>
                                @if ($gift->purchasedBy)
                                    <flux:text size="sm">{{ $gift->purchasedBy->name }}</flux:text>
                                @endif
                                @if ($gift->is_private)
                                    <flux:badge size="sm" color="purple">{{ __('Private') }}</flux:badge>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @empty
            <flux:text>{{ __('Nothing yet.') }}</flux:text>
        @endforelse
    </div>
</section>
