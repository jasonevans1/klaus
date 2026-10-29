<?php

use App\Actions\People\ArchivePerson;
use App\Actions\People\CreatePerson;
use App\Actions\People\RestorePerson;
use App\Actions\People\UpdatePerson;
use App\Livewire\Forms\PersonForm;
use App\Enums\RecipientGroup;
use App\Repositories\RecipientRepository;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('People')] class extends Component {
    public bool $showArchived = false;

    public PersonForm $form;

    public function create(): void
    {
        $this->form->reset();
        $this->resetValidation();
        Flux::modal('person')->show();
    }

    public function edit(int $id): void
    {
        $this->form->reset();
        $this->resetValidation();
        $this->form->fillFrom(app(RecipientRepository::class)->findForUser(Auth::user(), $id));
        Flux::modal('person')->show();
    }

    public function save(): void
    {
        $this->persist();
        Flux::modal('person')->close();
    }

    public function saveAndAddAnother(): void
    {
        $this->persist();
        $this->form->resetForNext();
    }

    private function persist(): void
    {
        $this->form->validate();

        $data = $this->form->data();
        $person = $this->form->recipientId === null
            ? app(CreatePerson::class)->handle(Auth::user(), $data)
            : app(UpdatePerson::class)->handle(Auth::user(), $this->form->recipientId, $data);

        unset($this->groups);
        Flux::toast(variant: 'success', text: __('Saved :name.', ['name' => $person->name]));
    }

    public function archive(ArchivePerson $archivePerson, int $recipientId): void
    {
        $archivePerson->handle(Auth::user(), $recipientId);

        unset($this->groups);
        Flux::toast(variant: 'success', text: __('Person archived.'));
    }

    public function restore(RestorePerson $restorePerson, int $recipientId): void
    {
        $restorePerson->handle(Auth::user(), $recipientId);

        unset($this->groups);
        Flux::toast(variant: 'success', text: __('Person restored.'));
    }

    #[Computed]
    public function groups(): array
    {
        return app(RecipientRepository::class)->groupedForUser(Auth::user(), $this->showArchived);
    }
}; ?>

<section class="w-full space-y-6 p-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('People') }}</flux:heading>
        <div class="flex items-center gap-4">
            <flux:switch wire:model.live="showArchived" :label="__('Show archived')" />
            <flux:button variant="primary" wire:click="create" data-test="add-person-button">{{ __('Add person') }}</flux:button>
        </div>
    </div>

    @forelse ($this->groups as $group => $people)
        @php($label = RecipientGroup::from($group)->label())
        <div wire:key="group-{{ $label }}" data-group="{{ $label }}" class="space-y-2">
            <flux:heading>{{ $label }} ({{ $people->count() }})</flux:heading>

            @foreach ($people as $person)
                <div wire:key="person-{{ $person->id }}" data-person="{{ $person->name }}"
                    @if ($person->is_shared) data-shared @endif
                    @if ($person->archived_at) data-archived @endif
                    @if ($person->birthdayLabel()) data-birthday="{{ $person->birthdayLabel() }}" @endif
                    @if ($person->age() !== null) data-age="{{ $person->age() }}" @endif
                    class="flex items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:text variant="strong">{{ $person->name }}</flux:text>
                            @if ($person->is_shared)
                                <flux:badge size="sm">{{ __('Shared') }}</flux:badge>
                            @endif
                            @if ($person->archived_at)
                                <flux:badge size="sm" color="zinc">{{ __('Archived') }}</flux:badge>
                            @endif
                        </div>
                        @if ($person->birthdayLabel())
                            <flux:text size="sm">{{ collect([$person->birthdayLabel(), $person->age()])->filter(fn ($part) => $part !== null)->implode(' · ') }}</flux:text>
                        @endif
                        @if ($person->interests)
                            <flux:text size="sm" class="truncate">{{ \Illuminate\Support\Str::limit($person->interests, 80) }}</flux:text>
                        @endif
                    </div>
                    <flux:button size="sm" wire:click="edit({{ $person->id }})" data-test="edit-person-button">{{ __('Edit') }}</flux:button>
                    @if ($person->archived_at)
                        <flux:button size="sm" wire:click="restore({{ $person->id }})" data-test="restore-person-button">{{ __('Restore') }}</flux:button>
                    @else
                        <flux:button size="sm" wire:click="archive({{ $person->id }})" data-test="archive-person-button">{{ __('Archive') }}</flux:button>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        <div class="space-y-2">
            <flux:text>{{ __('No people yet.') }}</flux:text>
        </div>
    @endforelse

    <flux:modal name="person" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $form->recipientId ? __('Edit person') : __('Add person') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" />

            <flux:select wire:model="form.group" :label="__('Group')">
                @foreach (RecipientGroup::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:checkbox wire:model="form.is_shared" :label="__('Shared entry (e.g. The Smiths)')" />

            <div class="grid grid-cols-3 gap-2">
                <flux:select wire:model="form.birthday_month" :label="__('Birthday month')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach (range(1, 12) as $month)
                        <flux:select.option value="{{ $month }}">{{ date('M', mktime(0, 0, 0, $month, 1)) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="number" wire:model="form.birthday_day" :label="__('Day')" />
                <flux:input type="number" wire:model="form.birthday_year" :label="__('Year (optional)')" />
            </div>

            <flux:textarea wire:model="form.interests" :label="__('Interests')" />

            <div class="flex justify-end gap-2">
                @unless ($form->recipientId)
                    <flux:button wire:click="saveAndAddAnother" data-test="save-and-add-another-button">{{ __('Save and add another') }}</flux:button>
                @endunless
                <flux:button type="submit" variant="primary" data-test="save-person-button">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
