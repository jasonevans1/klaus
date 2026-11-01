<?php

use App\Actions\Gifts\AdvanceGiftStatus;
use App\Enums\GiftStatus;
use App\Models\Occasion;
use App\Repositories\GiftRepository;
use App\Repositories\OccasionRepository;
use App\Support\Price;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $occasionId;

    public function mount(int $occasion): void
    {
        $this->occasionId = app(OccasionRepository::class)->findForUser(Auth::user(), $occasion)->id;
    }

    #[Computed]
    public function occasion(): Occasion
    {
        return app(OccasionRepository::class)->findForUser(Auth::user(), $this->occasionId);
    }

    #[Computed]
    public function ideas(): Collection
    {
        return app(GiftRepository::class)->ideas(Auth::user(), $this->occasion);
    }

    public function markBought(int $giftId): void
    {
        app(AdvanceGiftStatus::class)->handle(Auth::user(), $giftId, GiftStatus::Idea);

        unset($this->ideas);
    }
}; ?>

<section class="w-full space-y-6 p-6">
    @php($total = Price::format((int) $this->ideas->sum('price_cents')))
    <div>
        <flux:heading size="xl" level="1">{{ __('Shopping list') }} · {{ $this->occasion->name }}</flux:heading>
        <flux:link :href="route('occasions.show', $this->occasion)" wire:navigate>{{ __('Back to :occasion', ['occasion' => $this->occasion->name]) }}</flux:link>
        <flux:text data-ideas-count="{{ $this->ideas->count() }}" data-ideas-total="{{ $total }}">{{ $this->ideas->count() }} {{ Str::plural('idea', $this->ideas->count()) }} · {{ $total }}</flux:text>
    </div>

    @forelse ($this->ideas as $gift)
        <div wire:key="idea-{{ $gift->id }}" data-idea="{{ $gift->title }}" data-for="{{ $gift->recipient->name }}" data-price="{{ Price::format($gift->price_cents) }}" data-store="{{ $gift->store }}" data-url="{{ $gift->url }}" data-private="{{ $gift->is_private ? 'yes' : 'no' }}" class="flex items-center gap-4">
            @if ($gift->url)
                <a href="{{ $gift->url }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $gift->title }}</a>
            @else
                <flux:text variant="strong">{{ $gift->title }}</flux:text>
            @endif
            <flux:text size="sm">{{ $gift->recipient->name }}</flux:text>
            <flux:text size="sm">{{ Price::format($gift->price_cents) }}</flux:text>
            @if ($gift->store)
                <flux:text size="sm">{{ $gift->store }}</flux:text>
            @endif
            @if ($gift->is_private)
                <flux:badge size="sm" color="purple">{{ __('Private') }}</flux:badge>
            @endif
            <flux:button size="xs" wire:click="markBought({{ $gift->id }})" data-test="mark-bought-button">{{ __('Mark bought') }}</flux:button>
        </div>
    @empty
        <flux:text>{{ __('Nothing left to buy.') }}</flux:text>
    @endforelse
</section>
