<?php

use App\Actions\Gifts\AddGift;
use App\Actions\Gifts\AdvanceGiftStatus;
use App\Actions\Gifts\DeleteGift;
use App\Actions\Gifts\UpdateGift;
use App\Actions\Occasions\AddPeopleToOccasion;
use App\Actions\Occasions\RemovePersonFromOccasion;
use App\Actions\Occasions\SetBudget;
use App\Enums\GiftStatus;
use App\Enums\RecipientGroup;
use App\Livewire\Forms\GiftForm;
use App\Models\Occasion;
use App\Repositories\GiftRepository;
use App\Repositories\OccasionRepository;
use App\Services\BudgetSummary;
use App\Support\Budget;
use App\Support\Price;
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

        unset($this->groups, $this->totalBudgetCents, $this->giftsByPerson);
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

    public GiftForm $giftForm;

    public function addGift(int $recipientId): void
    {
        $this->giftForm->reset();
        $this->resetValidation();
        $this->giftForm->recipientId = $recipientId;
        Flux::modal('gift')->show();
    }

    public function editGift(int $giftId): void
    {
        $this->giftForm->reset();
        $this->resetValidation();
        $this->giftForm->fillFrom(app(GiftRepository::class)->findForUser(Auth::user(), $giftId));
        Flux::modal('gift')->show();
    }

    public function deleteGift(int $giftId): void
    {
        app(DeleteGift::class)->handle(Auth::user(), $giftId);

        unset($this->giftsByPerson);
        Flux::toast(variant: 'success', text: __('Gift deleted.'));
    }

    public function advanceGift(int $giftId, string $from): void
    {
        $status = GiftStatus::tryFrom($from);

        if ($status === null) {
            return;
        }

        app(AdvanceGiftStatus::class)->handle(Auth::user(), $giftId, $status);

        unset($this->giftsByPerson);
    }

    public function saveGift(): void
    {
        $this->persistGift();
        Flux::modal('gift')->close();
    }

    public function saveGiftAndAddAnother(): void
    {
        $this->persistGift();
        $this->giftForm->resetForNext();
    }

    private function persistGift(): void
    {
        $this->giftForm->validate();

        $gift = $this->giftForm->giftId
            ? app(UpdateGift::class)->handle(Auth::user(), $this->giftForm->giftId, $this->giftForm->data())
            : app(AddGift::class)->handle(Auth::user(), $this->occasionId, (int) $this->giftForm->recipientId, $this->giftForm->data());

        unset($this->giftsByPerson);
        Flux::toast(variant: 'success', text: __('Saved :title.', ['title' => $gift->title]));
    }

    #[Computed]
    public function giftsByPerson(): array
    {
        return app(GiftRepository::class)->byPerson(Auth::user(), $this->occasion);
    }

    #[Computed]
    public function buyers(): Collection
    {
        return app(GiftRepository::class)->buyers(Auth::user());
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

    public function summary(): array
    {
        return app(BudgetSummary::class)->summarize(collect($this->groups)->flatten(1), $this->giftsByPerson);
    }

    public function removeWarning(string $name, int $giftCount): string
    {
        return $giftCount === 0
            ? __('Remove :name from this occasion?', ['name' => $name])
            : __('Remove :name and their :count :gifts from this occasion?', ['name' => $name, 'count' => $giftCount, 'gifts' => Str::plural('gift', $giftCount)]);
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
    @php($summary = $this->summary())
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $this->occasion->name }}</flux:heading>
            <flux:text size="sm">{{ $this->occasion->date->format('M j, Y') }}</flux:text>
            <flux:text data-total-budget="{{ Budget::format($this->totalBudgetCents) }}">{{ __('Total budget :amount', ['amount' => Budget::format($this->totalBudgetCents)]) }}</flux:text>
            <flux:text data-total-spent="{{ Price::format($summary['spent']) }}">{{ __('Spent :amount', ['amount' => Price::format($summary['spent'])]) }}</flux:text>
            <flux:text data-total-remaining="{{ Price::format($summary['remaining']) }}">{{ __('Remaining :amount', ['amount' => Price::format($summary['remaining'])]) }}</flux:text>
            @if ($summary['privateSpent'] !== null)
                <flux:text data-my-private-spent="{{ Price::format($summary['privateSpent']) }}">{{ __('My private gifts: :amount spent', ['amount' => Price::format($summary['privateSpent'])]) }}</flux:text>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <flux:button :href="route('occasions.shopping', $this->occasion)" wire:navigate data-test="shopping-list-link">{{ __('Shopping list') }}</flux:button>
            <flux:button :href="route('occasions.wrapping', $this->occasion)" wire:navigate data-test="wrapping-checklist-link">{{ __('Wrapping checklist') }}</flux:button>
            <flux:button variant="primary" wire:click="openAddPeople" data-test="add-people-button">{{ __('Add people') }}</flux:button>
        </div>
    </div>

    @if ($summary['noGift'] !== [])
        <div class="space-y-2">
            <flux:heading>{{ __('No gift yet') }}</flux:heading>
            @foreach ($summary['noGift'] as $name)
                <flux:text data-no-gift="{{ $name }}">{{ $name }}</flux:text>
            @endforeach
        </div>
    @endif

    @forelse ($this->groups as $group => $people)
        @php($label = RecipientGroup::from($group)->label())
        <div wire:key="group-{{ $label }}" data-group="{{ $label }}" class="space-y-2">
            <flux:heading>{{ $label }} ({{ $people->count() }})</flux:heading>

            @foreach ($people as $person)
                @php($row = $summary['people'][$person->id])
                <div wire:key="person-{{ $person->id }}">
                <div data-person="{{ $person->name }}"
                    @if ($person->is_shared) data-shared @endif
                    @if ($person->archived_at) data-archived @endif
                    data-budget="{{ Budget::format($person->pivot->budget_cents) }}"
                    data-spent="{{ Price::format($row['spent']) }}"
                    data-remaining="{{ $row['remaining'] === null ? '' : Price::format($row['remaining']) }}"
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
                        <flux:text size="sm">{{ __('Spent :amount', ['amount' => Price::format($row['spent'])]) }}</flux:text>
                        @if ($row['remaining'] === null)
                        @elseif ($row['remaining'] < 0)
                            <flux:badge size="sm" color="red" data-over-budget>{{ __(':amount over', ['amount' => Price::format(-$row['remaining'])]) }}</flux:badge>
                        @else
                            <flux:text size="sm">{{ __(':amount left', ['amount' => Price::format($row['remaining'])]) }}</flux:text>
                        @endif
                        <div>
                            <flux:input.group>
                                <flux:input.group.prefix>$</flux:input.group.prefix>
                                {{-- Livewire 4: .blur alone never reaches the server; .live.blur saves when the box loses focus --}}
                                <flux:input wire:model.live.blur="budgets.{{ $person->id }}" inputmode="numeric" class="w-28" :aria-label="__('Budget for :name', ['name' => $person->name])" />
                            </flux:input.group>
                            <flux:error name="budgets.{{ $person->id }}" />
                        </div>
                        <flux:button size="sm" wire:click="remove({{ $person->id }})" wire:confirm="{{ $this->removeWarning($person->name, count($this->giftsByPerson[$person->id] ?? [])) }}" data-test="remove-person-button">{{ __('Remove') }}</flux:button>
                    </div>
                </div>

                <div class="space-y-1 pl-4 pt-2">
                    @foreach ($this->giftsByPerson[$person->id] ?? [] as $gift)
                        <div wire:key="gift-{{ $gift->id }}" data-gift="{{ $gift->title }}" data-price="{{ Price::format($gift->price_cents) }}" data-store="{{ $gift->store }}" data-url="{{ $gift->url }}" data-status="{{ $gift->status->label() }}" data-buyer="{{ $gift->purchasedBy?->name }}" data-private="{{ $gift->is_private ? 'yes' : 'no' }}" class="flex items-center gap-4">
                            @if ($gift->url)
                                <a href="{{ $gift->url }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $gift->title }}</a>
                            @else
                                <flux:text variant="strong">{{ $gift->title }}</flux:text>
                            @endif
                            <flux:text size="sm">{{ Price::format($gift->price_cents) }}</flux:text>
                            @if ($gift->store)
                                <flux:text size="sm">{{ $gift->store }}</flux:text>
                            @endif
                            <flux:badge size="sm">{{ $gift->status->label() }}</flux:badge>
                            @if ($gift->is_private)
                                <flux:badge size="sm" color="purple">{{ __('Private') }}</flux:badge>
                            @endif
                            @if ($gift->purchasedBy)
                                <flux:text size="sm">{{ __('Bought by :name', ['name' => $gift->purchasedBy->name]) }}</flux:text>
                            @endif
                            @if ($next = $gift->status->next())
                                <flux:button size="xs" wire:click="advanceGift({{ $gift->id }}, '{{ $gift->status->value }}')" data-test="advance-gift-button">{{ __('Mark :status', ['status' => strtolower($next->label())]) }}</flux:button>
                            @endif
                            <flux:button size="xs" wire:click="editGift({{ $gift->id }})" data-test="edit-gift-button">{{ __('Edit') }}</flux:button>
                            <flux:button size="xs" wire:click="deleteGift({{ $gift->id }})" wire:confirm="{{ __('Delete :title?', ['title' => $gift->title]) }}" data-test="delete-gift-button">{{ __('Delete') }}</flux:button>
                        </div>
                    @endforeach
                    <flux:button size="sm" wire:click="addGift({{ $person->id }})" data-test="add-gift-button">{{ __('Add gift') }}</flux:button>
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

    <flux:modal name="gift" class="w-full max-w-lg">
        <form wire:submit="saveGift" class="space-y-4">
            <flux:heading size="lg">{{ $giftForm->giftId ? __('Edit gift') : __('Add gift for :name', ['name' => collect($this->groups)->flatten()->firstWhere('id', $giftForm->recipientId)?->name]) }}</flux:heading>

            <flux:input wire:model="giftForm.title" :label="__('Title')" />

            <flux:field>
                <flux:label>{{ __('Price') }}</flux:label>
                <flux:input.group>
                    <flux:input.group.prefix>$</flux:input.group.prefix>
                    <flux:input wire:model="giftForm.price" inputmode="decimal" />
                </flux:input.group>
                <flux:error name="giftForm.price" />
            </flux:field>

            <flux:input wire:model="giftForm.store" :label="__('Store')" />
            <flux:input wire:model="giftForm.url" :label="__('Link')" />

            <flux:select wire:model="giftForm.status" :label="__('Status')">
                @foreach (GiftStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="giftForm.buyerId" :label="__('Bought by')">
                <flux:select.option value="">—</flux:select.option>
                @foreach ($this->buyers as $buyer)
                    <flux:select.option value="{{ $buyer->id }}">{{ $buyer->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($giftForm->canSetPrivate)
                <flux:checkbox wire:model="giftForm.isPrivate" :label="__('Private (only you can see it)')" />
            @endif

            <div class="flex justify-end gap-2">
                @unless ($giftForm->giftId)
                    <flux:button wire:click="saveGiftAndAddAnother" data-test="save-gift-and-add-another-button">{{ __('Save and add another') }}</flux:button>
                @endunless
                <flux:button type="submit" variant="primary" data-test="save-gift-button">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
