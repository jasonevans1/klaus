<?php

declare(strict_types=1);

use App\Enums\GiftStatus;
use App\Livewire\Forms\GiftForm;
use App\Models\Gift;
use App\Models\User;
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

    expect($form->data())->toMatchArray(['title' => 'Book', 'price_cents' => null, 'store' => null, 'url' => null]);
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

it('starts a new gift form at Idea with no buyer', function () {
    Livewire::test(GiftFormHost::class)
        ->assertSet('form.status', 'idea')
        ->assertSet('form.buyerId', '');
});

it('rejects a status that is not a gift status', function () {
    giftForm(['form.status' => 'lost'])->call('save')->assertHasErrors('form.status');
    giftForm(['form.status' => 'bought'])->call('save')->assertHasNoErrors();
});

it('rejects a buyer who is not in the household', function () {
    $user = User::factory()->create();
    $mine = User::factory()->forHousehold($user->household)->create();
    $stranger = User::factory()->create();

    $this->actingAs($user);

    giftForm(['form.buyerId' => (string) $stranger->id])->call('save')->assertHasErrors('form.buyerId');
    giftForm(['form.buyerId' => (string) $mine->id])->call('save')->assertHasNoErrors();
});

it('passes the status and buyer on with the gift details', function () {
    $user = User::factory()->create();
    $component = giftForm(['form.status' => 'wrapped', 'form.buyerId' => (string) $user->id]);

    $form = new GiftForm($component->instance(), 'form');
    $form->fill($component->get('form'));

    expect($form->data())->toMatchArray(['status' => GiftStatus::Wrapped, 'purchased_by_user_id' => $user->id]);

    $form->fill(['status' => 'idea', 'buyerId' => '']);
    expect($form->data())->toMatchArray(['status' => GiftStatus::Idea, 'purchased_by_user_id' => null]);
});

it('fills the status and buyer from an existing gift', function () {
    $buyer = User::factory()->create();
    $gift = Gift::factory()->bought($buyer)->create();

    Livewire::test(GiftFormHost::class)
        ->call('load', $gift->id)
        ->assertSet('form.status', 'bought')
        ->assertSet('form.buyerId', (string) $buyer->id);
});

it('goes back to Idea with no buyer when getting ready for the next gift', function () {
    giftForm(['form.status' => 'given', 'form.buyerId' => '3'])
        ->call('next')
        ->assertSet('form.status', 'idea')
        ->assertSet('form.buyerId', '');
});

it('starts a new gift form as not private', function () {
    Livewire::test(GiftFormHost::class)
        ->assertSet('form.isPrivate', false)
        ->assertSet('form.canSetPrivate', true);
});

it('passes the private flag on with the gift details', function () {
    $component = giftForm(['form.isPrivate' => true]);

    $form = new GiftForm($component->instance(), 'form');
    $form->fill($component->get('form'));

    expect($form->data())->toMatchArray(['is_private' => true]);

    $form->fill(['isPrivate' => false]);
    expect($form->data())->toMatchArray(['is_private' => false]);
});

it('fills the private flag from an existing gift', function () {
    $user = User::factory()->create();
    $gift = Gift::factory()->private($user)->create();

    Livewire::test(GiftFormHost::class)
        ->call('load', $gift->id)
        ->assertSet('form.isPrivate', true);
});

it('only offers the private choice on gifts I added', function () {
    $user = User::factory()->create();
    $partner = User::factory()->forHousehold($user->household)->create();
    $mine = Gift::factory()->create(['household_id' => $user->household_id, 'created_by_user_id' => $user->id]);
    $theirs = Gift::factory()->create(['household_id' => $user->household_id, 'created_by_user_id' => $partner->id]);

    $this->actingAs($user);

    Livewire::test(GiftFormHost::class)
        ->call('load', $theirs->id)
        ->assertSet('form.canSetPrivate', false)
        ->call('load', $mine->id)
        ->assertSet('form.canSetPrivate', true)
        ->call('load', $theirs->id)
        ->call('next')
        ->assertSet('form.canSetPrivate', true);
});
