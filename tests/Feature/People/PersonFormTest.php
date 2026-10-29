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
