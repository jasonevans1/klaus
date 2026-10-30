<?php

use App\Actions\Occasions\AddPeopleToOccasion;
use App\Actions\Occasions\RemovePersonFromOccasion;
use App\Actions\Occasions\SetBudget;
use App\Enums\RecipientGroup;
use App\Models\Occasion;
use App\Repositories\OccasionRepository;
use App\Support\Budget;
use Flux\Flux;
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

    /** @var array<int, string> */
    public array $budgets = [];

    public function mount(int $occasion): void
    {
        $this->occasionId = app(OccasionRepository::class)->findForUser(Auth::user(), $occasion)->id;
        $this->fillBudgets();
    }

    public function updatedBudgets(mixed $value, string $key): void
    {
        $this->validate(["budgets.{$key}" => Budget::rules()]);

        app(SetBudget::class)->handle(Auth::user(), $this->occasionId, (int) $key, Budget::toCents($value));

        unset($this->groups, $this->totalBudgetCents);
    }

    public function remove(int $recipientId): void
    {
        app(RemovePersonFromOccasion::class)->handle(Auth::user(), $this->occasionId, $recipientId);

        unset($this->groups, $this->totalBudgetCents);
        $this->fillBudgets();
    }

    /** @var array<int, string> */
    public array $selected = [];

    public function openAddPeople(): void
    {
        $this->selected = [];
        $this->resetErrorBag();
        Flux::modal('add-people')->show();
    }

    public function addSelected(): void
    {
        if ($this->selected === []) {
            $this->addError('selected', __('Pick at least one person.'));

            return;
        }

        $this->addPeople(array_map('intval', $this->selected));
    }

    public function addAll(): void
    {
        $this->addPeople($this->availablePeople->pluck('id')->all());
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function addPeople(array $ids): void
    {
        $count = app(AddPeopleToOccasion::class)->handle(Auth::user(), $this->occasionId, $ids);

        unset($this->groups, $this->totalBudgetCents, $this->availablePeople);
        $this->fillBudgets();
        $this->selected = [];
        Flux::modal('add-people')->close();
        Flux::toast(variant: 'success', text: __('Added :count :people.', ['count' => $count, 'people' => Str::plural('person', $count)]));
    }

    #[Computed]
    public function availablePeople(): Collection
    {
        return app(OccasionRepository::class)->availablePeople(Auth::user(), $this->occasion);
    }

    #[Computed]
    public function occasion(): Occasion
    {
        return app(OccasionRepository::class)->findForUser(Auth::user(), $this->occasionId);
    }

    #[Computed]
    public function groups(): array
    {
        return app(OccasionRepository::class)->groupedPeople($this->occasion);
    }

    #[Computed]
    public function totalBudgetCents(): int
    {
        return app(OccasionRepository::class)->totalBudgetCents($this->occasion);
    }

    private function fillBudgets(): void
    {
        $this->budgets = [];
        foreach ($this->groups as $people) {
            foreach ($people as $person) {
                $cents = $person->pivot->budget_cents;
                $this->budgets[$person->id] = $cents === null ? '' : (string) intdiv($cents, 100);
            }
        }
    }
}; ?>

<section class="w-full space-y-6 p-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $this->occasion->name }}</flux:heading>
            <flux:text size="sm">{{ $this->occasion->date->format('M j, Y') }}</flux:text>
            <flux:text data-total-budget="{{ Budget::format($this->totalBudgetCents) }}">{{ __('Total budget :amount', ['amount' => Budget::format($this->totalBudgetCents)]) }}</flux:text>
        </div>
        <flux:button variant="primary" wire:click="openAddPeople" data-test="add-people-button">{{ __('Add people') }}</flux:button>
    </div>

    @forelse ($this->groups as $group => $people)
        @php($label = RecipientGroup::from($group)->label())
        <div wire:key="group-{{ $label }}" data-group="{{ $label }}" class="space-y-2">
            <flux:heading>{{ $label }} ({{ $people->count() }})</flux:heading>

            @foreach ($people as $person)
                <div wire:key="person-{{ $person->id }}" data-person="{{ $person->name }}"
                    @if ($person->is_shared) data-shared @endif
                    @if ($person->archived_at) data-archived @endif
                    data-budget="{{ Budget::format($person->pivot->budget_cents) }}"
                    class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <flux:text variant="strong">{{ $person->name }}</flux:text>
                        @if ($person->is_shared)
                            <flux:badge size="sm">{{ __('Shared') }}</flux:badge>
                        @endif
                        @if ($person->archived_at)
                            <flux:badge size="sm" color="zinc">{{ __('Archived') }}</flux:badge>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <div>
                            <flux:input.group>
                                <flux:input.group.prefix>$</flux:input.group.prefix>
                                {{-- Livewire 4: .blur alone never reaches the server; .live.blur saves when the box loses focus --}}
                                <flux:input wire:model.live.blur="budgets.{{ $person->id }}" inputmode="numeric" class="w-28" :aria-label="__('Budget for :name', ['name' => $person->name])" />
                            </flux:input.group>
                            <flux:error name="budgets.{{ $person->id }}" />
                        </div>
                        <flux:button size="sm" wire:click="remove({{ $person->id }})" wire:confirm="{{ __('Remove :name from this occasion?', ['name' => $person->name]) }}" data-test="remove-person-button">{{ __('Remove') }}</flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="space-y-2">
            <flux:text>{{ __('Nobody on this occasion yet.') }}</flux:text>
            <flux:button wire:click="openAddPeople">{{ __('Add people') }}</flux:button>
        </div>
    @endforelse

    <flux:modal name="add-people" class="w-full max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Add people') }}</flux:heading>

            @if ($this->availablePeople->isEmpty())
                <flux:text>{{ __('Everyone is already on this occasion.') }}</flux:text>
            @else
                <flux:checkbox.group wire:model="selected">
                    @foreach ($this->availablePeople as $person)
                        <flux:checkbox data-available="{{ $person->name }}" wire:key="available-{{ $person->id }}" value="{{ $person->id }}" label="{{ $person->name }}" description="{{ $person->group->label() }}" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="selected" />

                <div class="flex justify-end gap-2">
                    <flux:button wire:click="addAll" data-test="add-all-button">{{ __('Add all') }}</flux:button>
                    <flux:button variant="primary" wire:click="addSelected" data-test="add-selected-button">{{ __('Add selected') }}</flux:button>
                </div>
            @endif
        </div>
    </flux:modal>
</section>
