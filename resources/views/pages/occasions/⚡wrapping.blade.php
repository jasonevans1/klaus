<?php

use App\Actions\Gifts\SetGiftWrapped;
use App\Enums\GiftStatus;
use App\Models\Occasion;
use App\Repositories\GiftRepository;
use App\Repositories\OccasionRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
    public function gifts(): Collection
    {
        return app(GiftRepository::class)->toWrap(Auth::user(), $this->occasion);
    }

    public function setWrapped(int $giftId, bool $wrapped): void
    {
        app(SetGiftWrapped::class)->handle(Auth::user(), $giftId, $wrapped);

        unset($this->gifts);
    }
}; ?>

<section class="w-full space-y-6 p-6">
    @php($wrappedCount = $this->gifts->where('status', GiftStatus::Wrapped)->count())
    <div>
        <flux:heading size="xl" level="1">{{ __('Wrapping checklist') }} · {{ $this->occasion->name }}</flux:heading>
        <flux:link :href="route('occasions.show', $this->occasion)" wire:navigate>{{ __('Back to :occasion', ['occasion' => $this->occasion->name]) }}</flux:link>
        <flux:text data-wrapped-count="{{ $wrappedCount }}" data-to-wrap-count="{{ $this->gifts->count() }}">{{ __(':wrapped of :total wrapped', ['wrapped' => $wrappedCount, 'total' => $this->gifts->count()]) }}</flux:text>
    </div>

    @forelse ($this->gifts as $gift)
        <div wire:key="wrap-{{ $gift->id }}" data-wrap="{{ $gift->title }}" data-for="{{ $gift->recipient->name }}" data-wrapped="{{ $gift->status === GiftStatus::Wrapped ? 'yes' : 'no' }}" data-private="{{ $gift->is_private ? 'yes' : 'no' }}" class="flex items-center gap-4">
            <flux:checkbox wire:key="wrap-check-{{ $gift->id }}-{{ $gift->status->value }}" :checked="$gift->status === GiftStatus::Wrapped" wire:click="setWrapped({{ $gift->id }}, {{ $gift->status === GiftStatus::Wrapped ? 'false' : 'true' }})" aria-label="{{ __('Wrapped: :title for :name', ['title' => $gift->title, 'name' => $gift->recipient->name]) }}" data-test="wrap-checkbox" />
            <flux:text variant="strong">{{ $gift->title }}</flux:text>
            <flux:text size="sm">{{ $gift->recipient->name }}</flux:text>
            @if ($gift->is_private)
                <flux:badge size="sm" color="purple">{{ __('Private') }}</flux:badge>
            @endif
        </div>
    @empty
        <flux:text>{{ __('Nothing to wrap yet.') }}</flux:text>
    @endforelse
</section>
