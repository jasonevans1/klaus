<?php

declare(strict_types=1);

use App\Livewire\Forms\GiftForm;
use App\Models\Gift;
use Livewire\Component;
use Livewire\Livewire;

class GiftFormHost extends Component
{
    public GiftForm $form;

    public function save(): void
    {
        $this->form->validate();
    }

    public function load(int $id): void
    {
        $this->form->fillFrom(Gift::findOrFail($id));
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

function giftForm(array $set = [])
{
    $component = Livewire::test(GiftFormHost::class);

    foreach (array_merge(['form.title' => 'Book'], $set) as $key => $value) {
        $component->set($key, $value);
    }

    return $component;
}

it('requires a gift title', function () {
    giftForm(['form.title' => ''])->call('save')->assertHasErrors(['form.title' => 'required']);
});

it('rejects a title longer than 150 characters', function () {
    giftForm(['form.title' => str_repeat('a', 151)])->call('save')->assertHasErrors(['form.title' => 'max']);
});

it('rejects a price with more than two decimals', function () {
    giftForm(['form.price' => '12.345'])->call('save')->assertHasErrors('form.price');
});

it('rejects a link that is not a web address', function () {
    giftForm(['form.url' => 'ftp://example.com'])->call('save')->assertHasErrors('form.url');
    giftForm(['form.url' => 'https://example.com'])->call('save')->assertHasNoErrors();
});

it('turns a blank price, store and link into nothing', function () {
    $component = giftForm(['form.title' => '  Book  ', 'form.price' => '', 'form.store' => '  ', 'form.url' => ' ']);

    $form = new GiftForm($component->instance(), 'form');
    $form->fill($component->get('form'));

    expect($form->data())->toBe(['title' => 'Book', 'price_cents' => null, 'store' => null, 'url' => null]);
});

it('fills the form from an existing gift', function () {
    $gift = Gift::factory()->create([
        'title' => 'Lego', 'price_cents' => 2550, 'store' => 'Target', 'url' => 'https://example.com',
    ]);

    Livewire::test(GiftFormHost::class)
        ->call('load', $gift->id)
        ->assertSet('form.giftId', $gift->id)
        ->assertSet('form.recipientId', $gift->recipient_id)
        ->assertSet('form.title', 'Lego')
        ->assertSet('form.price', '25.50')
        ->assertSet('form.store', 'Target')
        ->assertSet('form.url', 'https://example.com');
});

it('keeps the person when getting ready for the next gift', function () {
    giftForm(['form.giftId' => 4, 'form.recipientId' => 9, 'form.price' => '5', 'form.store' => 'x'])
        ->call('next')
        ->assertSet('form.recipientId', 9)
        ->assertSet('form.giftId', null)
        ->assertSet('form.title', '')
        ->assertSet('form.price', '')
        ->assertSet('form.store', '');
});
