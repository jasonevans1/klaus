<?php

use App\Actions\Occasions\CreateChristmas;
use App\Actions\Occasions\DeleteOccasion;
use App\Repositories\OccasionRepository;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Occasions')] class extends Component {
    public int $year;

    public function mount(): void
    {
        $this->year = now()->year;
    }

    public function createChristmas(CreateChristmas $createChristmas): void
    {
        $this->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100']]);

        $occasion = $createChristmas->handle(Auth::user(), $this->year);

        $this->redirectRoute('occasions.show', $occasion->id, navigate: true);
    }

    public function delete(DeleteOccasion $deleteOccasion, int $occasionId): void
    {
        $occasion = app(OccasionRepository::class)->findForUser(Auth::user(), $occasionId);
        $deleteOccasion->handle(Auth::user(), $occasionId);

        unset($this->occasions);
        Flux::toast(variant: 'success', text: __('Deleted :name.', ['name' => $occasion->name]));
    }

    /**
     * @return Collection<int, \App\Models\Occasion>
     */
    #[Computed]
    public function occasions(): Collection
    {
        return app(OccasionRepository::class)->listForUser(Auth::user());
    }
}; ?>

<section class="w-full space-y-6 p-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Occasions') }}</flux:heading>
        <flux:modal.trigger name="christmas">
            <flux:button variant="primary" data-test="new-christmas-button">{{ __('New Christmas') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @forelse ($this->occasions as $occasion)
        <div wire:key="occasion-{{ $occasion->id }}" data-occasion="{{ $occasion->name }}" class="flex items-center justify-between gap-4">
            <div>
                <flux:link :href="route('occasions.show', $occasion->id)" wire:navigate>{{ $occasion->name }}</flux:link>
                <flux:text size="sm">{{ $occasion->date->format('M j, Y') }}</flux:text>
            </div>
            <flux:button size="sm" variant="danger" wire:click="delete({{ $occasion->id }})"
                wire:confirm="{{ __('Delete :name?', ['name' => $occasion->name]) }}" data-test="delete-occasion-button">{{ __('Delete') }}</flux:button>
        </div>
    @empty
        <flux:text>{{ __('No occasions yet.') }}</flux:text>
    @endforelse

    <flux:modal name="christmas" class="w-full max-w-sm">
        <form wire:submit="createChristmas" class="space-y-4">
            <flux:heading size="lg">{{ __('New Christmas') }}</flux:heading>
            <flux:input type="number" wire:model="year" :label="__('Year')" />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" data-test="create-christmas-button">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
