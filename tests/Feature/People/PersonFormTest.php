<?php

declare(strict_types=1);

use App\Enums\RecipientGroup;
use App\Livewire\Forms\PersonForm;
use App\Models\Recipient;
use Livewire\Component;
use Livewire\Livewire;

class PersonFormHost extends Component
{
    public PersonForm $form;

    public function save(): void
    {
        $this->form->validate();
    }

    public function load(int $id): void
    {
        $this->form->fillFrom(Recipient::findOrFail($id));
    }

    public function next(): void
    {
        $this->form->resetForNext();
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

function personForm(array $set = [])
{
    $component = Livewire::test(PersonFormHost::class);

    foreach (array_merge(['form.name' => 'Ann'], $set) as $key => $value) {
        $component->set($key, $value);
    }

    return $component;
}

it('requires a name', function () {
    personForm(['form.name' => ''])->call('save')->assertHasErrors(['form.name' => 'required']);
});

it('rejects a name longer than 100 characters', function () {
    personForm(['form.name' => str_repeat('a', 101)])->call('save')->assertHasErrors(['form.name' => 'max']);
});

it('requires one of the four groups', function () {
    personForm(['form.group' => 'strangers'])->call('save')->assertHasErrors('form.group');
    personForm(['form.group' => 'teachers'])->call('save')->assertHasNoErrors();
});

it('shows the birthday error on the month field', function () {
    personForm(['form.birthday_month' => '2', 'form.birthday_day' => '30'])
        ->call('save')
        ->assertHasErrors('form.birthday_month')
        ->assertHasNoErrors(['form.birthday_day']);
});

it('rejects a day without a month', function () {
    personForm(['form.birthday_day' => '14'])->call('save')->assertHasErrors('form.birthday_month');
});

it('treats a cleared birthday field as no value', function () {
    $component = personForm(['form.birthday_month' => '3', 'form.birthday_day' => '14', 'form.birthday_year' => '']);
    $component->call('save')->assertHasNoErrors();

    $form = new PersonForm($component->instance(), 'form');
    $form->fill($component->get('form'));

    expect($form->data())->toMatchArray([
        'birthday_month' => 3,
        'birthday_day' => 14,
        'birthday_year' => null,
        'interests' => null,
    ]);
});

it('fills from an existing person', function () {
    $person = Recipient::factory()->create([
        'name' => 'Bea',
        'group' => RecipientGroup::Friends,
        'is_shared' => true,
        'birthday_month' => 7,
        'birthday_day' => 4,
        'birthday_year' => null,
        'interests' => 'Chess',
    ]);

    Livewire::test(PersonFormHost::class)
        ->call('load', $person->id)
        ->assertSet('form.recipientId', $person->id)
        ->assertSet('form.name', 'Bea')
        ->assertSet('form.group', 'friends')
        ->assertSet('form.is_shared', true)
        ->assertSet('form.birthday_month', '7')
        ->assertSet('form.birthday_day', '4')
        ->assertSet('form.birthday_year', '')
        ->assertSet('form.interests', 'Chess');
});

it('keeps the group but clears the other fields when adding another', function () {
    personForm(['form.group' => 'coworkers', 'form.is_shared' => true, 'form.birthday_month' => '3', 'form.interests' => 'x'])
        ->call('next')
        ->assertSet('form.group', 'coworkers')
        ->assertSet('form.name', '')
        ->assertSet('form.is_shared', false)
        ->assertSet('form.birthday_month', '')
        ->assertSet('form.interests', '');
});

it('starts with an empty form defaulting to the family group', function () {
    Livewire::test(PersonFormHost::class)
        ->assertSet('form.name', '')
        ->assertSet('form.group', 'family')
        ->assertSet('form.is_shared', false)
        ->assertSet('form.birthday_month', '')
        ->assertSet('form.birthday_day', '')
        ->assertSet('form.birthday_year', '')
        ->assertSet('form.interests', '');
});

it('requires a group', function () {
    personForm(['form.group' => ''])->call('save')->assertHasErrors(['form.group' => 'required']);
});

it('requires numeric birthday parts', function () {
    personForm(['form.birthday_day' => 'abc'])->call('save')->assertHasErrors(['form.birthday_day' => 'integer']);
    personForm(['form.birthday_year' => 'abc'])->call('save')->assertHasErrors(['form.birthday_year' => 'integer']);
    personForm(['form.birthday_month' => 'abc'])->call('save')->assertHasErrors(['form.birthday_month' => 'integer']);
});

it('accepts blank birthday parts and blank interests', function () {
    personForm()->call('save')->assertHasNoErrors();
});

it('limits interests to 1000 characters', function () {
    personForm(['form.interests' => str_repeat('a', 1001)])->call('save')->assertHasErrors(['form.interests' => 'max']);
    personForm(['form.interests' => str_repeat('a', 1000)])->call('save')->assertHasNoErrors();
});

it('fills blank interests when the person has none', function () {
    $person = Recipient::factory()->create(['interests' => null]);

    Livewire::test(PersonFormHost::class)
        ->call('load', $person->id)
        ->assertSet('form.interests', '');
});

it('keeps non-empty interests in the normalised data', function () {
    $component = personForm(['form.interests' => 'Chess', 'form.is_shared' => true]);
    $form = new PersonForm($component->instance(), 'form');
    $form->fill($component->get('form'));

    expect($form->data())->toMatchArray(['name' => 'Ann', 'interests' => 'Chess', 'is_shared' => true]);
});

it('normalises blank interests to a strict null', function () {
    $component = personForm();
    $form = new PersonForm($component->instance(), 'form');
    $form->fill($component->get('form'));

    expect($form->data()['interests'])->toBeNull();
});
