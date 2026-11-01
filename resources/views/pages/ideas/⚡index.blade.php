<?php

use App\Actions\Gifts\AddIdea;
use App\Actions\Gifts\DeleteGift;
use App\Actions\Gifts\UpdateGift;
use App\Enums\RecipientGroup;
use App\Livewire\Forms\GiftForm;
use App\Repositories\GiftRepository;
use App\Repositories\RecipientRepository;
use App\Support\Price;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ideas')] class extends Component {
    public GiftForm $form;

    public function addIdea(int $recipientId): void
    {
        $this->form->reset();
        $this->resetValidation();
        $this->form->recipientId = app(RecipientRepository::class)->findForUser(Auth::user(), $recipientId)->id;
        Flux::modal('idea')->show();
    }

    public function editIdea(int $giftId): void
    {
        $this->form->reset();
        $this->resetValidation();
        $this->form->fillFrom(app(GiftRepository::class)->findForUser(Auth::user(), $giftId));
        Flux::modal('idea')->show();
    }

    public function deleteIdea(int $giftId): void
    {
        app(DeleteGift::class)->handle(Auth::user(), $giftId);

        unset($this->ideas);
        Flux::toast(variant: 'success', text: __('Idea deleted.'));
    }

    public function saveIdea(): void
    {
        $this->persist();
        Flux::modal('idea')->close();
    }

    public function saveIdeaAndAddAnother(): void
    {
        $this->persist();
        $this->form->resetForNext();
    }

    private function persist(): void
    {
        $this->form->validate();

        $gift = $this->form->giftId
            ? app(UpdateGift::class)->handle(Auth::user(), $this->form->giftId, $this->form->data())
            : app(AddIdea::class)->handle(Auth::user(), (int) $this->form->recipientId, $this->form->data());

        unset($this->ideas);
        Flux::toast(variant: 'success', text: __('Saved :title.', ['title' => $gift->title]));
    }

    #[Computed]
    public function groups(): array
    {
        return app(RecipientRepository::class)->groupedForUser(Auth::user());
    }

    #[Computed]
    public function ideas(): array
    {
        return app(GiftRepository::class)->yearRoundIdeas(Auth::user());
    }
}; ?>

<section class="w-full space-y-6 p-6">
    <flux:heading size="xl" level="1">{{ __('Ideas') }}</flux:heading>

    @forelse ($this->groups as $group => $people)
        @php($label = RecipientGroup::from($group)->label())
        <div wire:key="group-{{ $label }}" class="space-y-2">
            <flux:heading>{{ $label }} ({{ $people->count() }})</flux:heading>

            @foreach ($people as $person)
                <div wire:key="person-{{ $person->id }}">
                    <div data-person="{{ $person->name }}" class="flex items-center justify-between gap-4">
                        <flux:text variant="strong">{{ $person->name }}</flux:text>
                        <flux:button size="sm" wire:click="addIdea({{ $person->id }})" data-test="add-idea-button">{{ __('Add idea') }}</flux:button>
                    </div>

                    <div class="space-y-1 pl-4 pt-2">
                        @foreach ($this->ideas[$person->id] ?? [] as $idea)
                            <div wire:key="idea-{{ $idea->id }}" data-idea="{{ $idea->title }}" data-price="{{ Price::format($idea->price_cents) }}" data-store="{{ $idea->store }}" data-url="{{ $idea->url }}" data-private="{{ $idea->is_private ? 'yes' : 'no' }}" class="flex items-center gap-4">
                                @if ($idea->url)
                                    <a href="{{ $idea->url }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $idea->title }}</a>
                                @else
                                    <flux:text variant="strong">{{ $idea->title }}</flux:text>
                                @endif
                                <flux:text size="sm">{{ Price::format($idea->price_cents) }}</flux:text>
                                @if ($idea->store)
                                    <flux:text size="sm">{{ $idea->store }}</flux:text>
                                @endif
                                @if ($idea->is_private)
                                    <flux:badge size="sm" color="purple">{{ __('Private') }}</flux:badge>
                                @endif
                                <flux:button size="xs" wire:click="editIdea({{ $idea->id }})" data-test="edit-idea-button">{{ __('Edit') }}</flux:button>
                                <flux:button size="xs" wire:click="deleteIdea({{ $idea->id }})" wire:confirm="{{ __('Delete :title?', ['title' => $idea->title]) }}" data-test="delete-idea-button">{{ __('Delete') }}</flux:button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <flux:text>{{ __('No people yet.') }}</flux:text>
    @endforelse

    <flux:modal name="idea" class="w-full max-w-lg">
        <form wire:submit="saveIdea" class="space-y-4">
            <flux:heading size="lg">{{ $form->giftId ? __('Edit idea') : __('Add idea') }}</flux:heading>

            <flux:input wire:model="form.title" :label="__('Title')" />

            <flux:field>
                <flux:label>{{ __('Price') }}</flux:label>
                <flux:input.group>
                    <flux:input.group.prefix>$</flux:input.group.prefix>
                    <flux:input wire:model="form.price" inputmode="decimal" />
                </flux:input.group>
                <flux:error name="form.price" />
            </flux:field>

            <flux:input wire:model="form.store" :label="__('Store')" />
            <flux:input wire:model="form.url" :label="__('Link')" />

            @if ($form->canSetPrivate)
                <flux:checkbox wire:model="form.isPrivate" :label="__('Private (only you can see it)')" />
            @endif

            <div class="flex justify-end gap-2">
                @unless ($form->giftId)
                    <flux:button wire:click="saveIdeaAndAddAnother" data-test="save-idea-and-add-another-button">{{ __('Save and add another') }}</flux:button>
                @endunless
                <flux:button type="submit" variant="primary" data-test="save-idea-button">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
