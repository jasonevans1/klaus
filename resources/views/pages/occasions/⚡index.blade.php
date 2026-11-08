<?php

use App\Actions\Occasions\CreateBirthdays;
use App\Actions\Occasions\CreateChristmas;
use App\Actions\Occasions\DeleteOccasion;
use App\Models\Occasion;
use App\Repositories\OccasionRepository;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Occasions')] class extends Component
{
    public int $year;

    public int $birthdaysYear;

    public function mount(): void
    {
        $latest = app(OccasionRepository::class)->previousChristmas(Auth::user())?->date->year;
        $this->year = max(now()->year, ($latest ?? 0) + 1);

        $latestBirthdays = app(OccasionRepository::class)->previousBirthdays(Auth::user())?->date->year;
        $this->birthdaysYear = max(now()->year, ($latestBirthdays ?? 0) + 1);
    }

    public function createChristmas(CreateChristmas $createChristmas): void
    {
        $this->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100']]);

        $occasion = $createChristmas->handle(Auth::user(), $this->year);

        $this->redirectRoute('occasions.show', $occasion->id, navigate: true);
    }

    public function createBirthdays(CreateBirthdays $createBirthdays): void
    {
        $this->validate(['birthdaysYear' => ['required', 'integer', 'min:2000', 'max:2100']]);

        try {
            $occasion = $createBirthdays->handle(Auth::user(), $this->birthdaysYear);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['birthdaysYear' => $e->errors()['year']]);
        }

        $this->redirectRoute('occasions.show', $occasion->id, navigate: true);
    }

    public function delete(DeleteOccasion $deleteOccasion, int $occasionId): void
    {
        $occasion = app(OccasionRepository::class)->findForUser(Auth::user(), $occasionId);
        $deleteOccasion->handle(Auth::user(), $occasionId);

        unset($this->occasions);
        Flux::toast(variant: 'success', text: __('Deleted :name.', ['name' => $occasion->name]));
    }

    #[Computed]
    public function copiesFrom(): ?Occasion
    {
        return isset($this->year) ? app(OccasionRepository::class)->previousChristmas(Auth::user(), $this->year) : null;
    }

    #[Computed]
    public function birthdaysCopiesFrom(): ?Occasion
    {
        return isset($this->birthdaysYear) ? app(OccasionRepository::class)->previousBirthdays(Auth::user(), $this->birthdaysYear) : null;
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
        <div class="flex items-center gap-2">
            <flux:modal.trigger name="birthdays">
                <flux:button data-test="new-birthdays-button">{{ __('New birthdays') }}</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="christmas">
                <flux:button variant="primary" data-test="new-christmas-button">{{ __('New Christmas') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if ($this->occasions->isEmpty())
        <flux:text>{{ __('No occasions yet.') }}</flux:text>
    @else
        <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->occasions as $occasion)
                <div wire:key="occasion-{{ $occasion->id }}" data-occasion="{{ $occasion->name }}" class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                    <div class="min-w-0">
                        <flux:link :href="route('occasions.show', $occasion->id)" wire:navigate>{{ $occasion->name }}</flux:link>
                        <flux:text size="sm">{{ $occasion->date->format('M j, Y') }}</flux:text>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('More actions') }}" data-test="occasion-menu-button" />
                            <flux:menu>
                                <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $occasion->id }})"
                                    wire:confirm="{{ $occasion->gifts_count
                                        ? __('Delete :name and its :gifts?', ['name' => $occasion->name, 'gifts' => $occasion->gifts_count.' '.Str::plural('gift', $occasion->gifts_count)])
                                        : __('Delete :name?', ['name' => $occasion->name]) }}" data-test="delete-occasion-button">{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal name="christmas" class="w-full max-w-sm">
        <form wire:submit="createChristmas" class="space-y-4">
            <flux:heading size="lg">{{ __('New Christmas') }}</flux:heading>
            <flux:input type="number" wire:model.live.blur="year" :label="__('Year')" />
            @if ($this->copiesFrom)
                <flux:text data-copies-from="{{ $this->copiesFrom->name }}">{{ __('Copies people and budgets from :name.', ['name' => $this->copiesFrom->name]) }}</flux:text>
            @endif
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" data-test="create-christmas-button">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="birthdays" class="w-full max-w-sm">
        <form wire:submit="createBirthdays" class="space-y-4">
            <flux:heading size="lg">{{ __('New birthdays') }}</flux:heading>
            <flux:input type="number" wire:model.live.blur="birthdaysYear" :label="__('Year')" />
            @if ($this->birthdaysCopiesFrom)
                <flux:text data-birthdays-copies-from="{{ $this->birthdaysCopiesFrom->name }}">{{ __('Copies budgets from :name.', ['name' => $this->birthdaysCopiesFrom->name]) }}</flux:text>
            @endif
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" data-test="create-birthdays-button">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
