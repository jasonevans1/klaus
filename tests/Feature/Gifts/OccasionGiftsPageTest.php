<?php

declare(strict_types=1);

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Support\Arr;
use Livewire\Livewire;

function christmasFor(User $user): Occasion
{
    return Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();
}

function personFor(Occasion $occasion, string $name): Recipient
{
    $person = Recipient::factory()->forHousehold($occasion->household)->create(['name' => $name]);
    $occasion->recipients()->attach($person, ['budget_cents' => null]);

    return $person;
}

function giftsPage(User $user, Occasion $occasion)
{
    return Livewire::actingAs($user)->test('pages::occasions.show', ['occasion' => $occasion->id]);
}

function giftFor(User $user, $occasion, $person, array $attrs = []): Gift
{
    $gift = new Gift(array_merge(['title' => 'Scarf', 'price_cents' => null, 'store' => null, 'url' => null], $attrs));
    $gift->household_id = $user->household_id;
    $gift->occasion_id = $occasion->id;
    $gift->recipient_id = $person->id;
    $gift->created_by_user_id = $user->id;
    $gift->forceFill(Arr::only($attrs, ['status', 'purchased_by_user_id']));
    $gift->save();

    return $gift;
}

it('shows each gift under its person with title, price, store and link', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $mum = personFor($occasion, 'Mum');
    giftFor($user, $occasion, $mum, ['title' => 'Scarf', 'price_cents' => 4050, 'store' => 'Target', 'url' => 'https://t.example/s']);

    giftsPage($user, $occasion)
        ->assertSeeHtml('data-gift="Scarf"')
        ->assertSeeHtml('data-price="$40.50"')
        ->assertSeeHtml('data-store="Target"')
        ->assertSeeHtml('data-url="https://t.example/s"');
});

it('shows a person with no gifts with just an Add gift button', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    personFor($occasion, 'Mum');

    giftsPage($user, $occasion)
        ->assertDontSeeHtml('data-gift=')
        ->assertSeeHtml('data-test="add-gift-button"');
});

it('adds a gift for a person from the occasion page', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $mum = personFor($occasion, 'Mum');

    giftsPage($user, $occasion)
        ->call('addGift', $mum->id)
        ->assertSet('giftForm.recipientId', $mum->id)
        ->set('giftForm.title', 'Scarf')
        ->set('giftForm.price', '40.50')
        ->call('saveGift')
        ->assertHasNoErrors()
        ->assertSeeHtml('data-gift="Scarf"');

    expect(Gift::where('recipient_id', $mum->id)->first())
        ->title->toBe('Scarf')
        ->price_cents->toBe(4050)
        ->occasion_id->toBe($occasion->id);
});

it('keeps the gift window open for the same person after Save and add another', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $mum = personFor($occasion, 'Mum');

    giftsPage($user, $occasion)
        ->call('addGift', $mum->id)
        ->set('giftForm.title', 'Scarf')
        ->call('saveGiftAndAddAnother')
        ->assertHasNoErrors()
        ->assertSet('giftForm.title', '')
        ->assertSet('giftForm.recipientId', $mum->id)
        ->assertNotDispatched('modal-close');

    expect(Gift::count())->toBe(1);
});

it('shows validation errors and saves nothing when the title is missing', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $mum = personFor($occasion, 'Mum');

    giftsPage($user, $occasion)
        ->call('addGift', $mum->id)
        ->call('saveGift')
        ->assertHasErrors(['giftForm.title' => 'required']);

    expect(Gift::count())->toBe(0);
});

it('shows an error for a price with more than two decimals', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $mum = personFor($occasion, 'Mum');

    giftsPage($user, $occasion)
        ->call('addGift', $mum->id)
        ->set('giftForm.title', 'Scarf')
        ->set('giftForm.price', '1.234')
        ->call('saveGift')
        ->assertHasErrors(['giftForm.price']);

    expect(Gift::count())->toBe(0);
});

it('opens the gift window without errors from a previous attempt', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $mum = personFor($occasion, 'Mum');

    giftsPage($user, $occasion)
        ->call('addGift', $mum->id)
        ->call('saveGift')
        ->assertHasErrors()
        ->call('addGift', $mum->id)
        ->assertHasNoErrors();
});

it('opens product links in a new tab without passing the page along', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    giftFor($user, $occasion, personFor($occasion, 'Mum'), ['url' => 'https://t.example/s']);

    giftsPage($user, $occasion)
        ->assertSeeHtml('href="https://t.example/s"')
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml('rel="noopener noreferrer"');
});

it('shows gifts my partner added', function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $occasion = christmasFor($user);
    giftFor($partner, $occasion, personFor($occasion, 'Mum'), ['title' => 'Mug']);

    giftsPage($user, $occasion)->assertSeeHtml('data-gift="Mug"');
});

it('edits a gift from the occasion page', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $mum = personFor($occasion, 'Mum');
    $gift = giftFor($user, $occasion, $mum, ['title' => 'Scarf', 'price_cents' => 4050]);

    giftsPage($user, $occasion)
        ->call('editGift', $gift->id)
        ->assertSet('giftForm.title', 'Scarf')
        ->assertSet('giftForm.price', '40.50')
        ->set('giftForm.title', 'Gloves')
        ->call('saveGift')
        ->assertHasNoErrors()
        ->assertSeeHtml('data-gift="Gloves"')
        ->assertDontSeeHtml('data-gift="Scarf"');

    expect(Gift::count())->toBe(1)->and($gift->fresh()->title)->toBe('Gloves');
});

it('hides Save and add another when editing a gift', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $gift = giftFor($user, $occasion, personFor($occasion, 'Mum'));

    giftsPage($user, $occasion)
        ->assertSeeHtml('data-test="save-gift-and-add-another-button"')
        ->call('editGift', $gift->id)
        ->assertSee('Edit gift')
        ->assertDontSeeHtml('data-test="save-gift-and-add-another-button"');
});

it('deletes a gift from the occasion page after confirming', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $gift = giftFor($user, $occasion, personFor($occasion, 'Mum'));

    giftsPage($user, $occasion)
        ->assertSeeHtml('wire:confirm="Delete Scarf?"')
        ->call('deleteGift', $gift->id)
        ->assertDontSeeHtml('data-gift="Scarf"');

    expect(Gift::count())->toBe(0);
});

it('refuses to edit or delete another household\'s gift', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $other = User::factory()->create();
    $otherOccasion = christmasFor($other);
    $gift = giftFor($other, $otherOccasion, personFor($otherOccasion, 'Dad'));

    giftsPage($user, $occasion)->call('editGift', $gift->id)->assertNotFound();
    giftsPage($user, $occasion)->call('deleteGift', $gift->id)->assertNotFound();

    expect(Gift::count())->toBe(1);
});

it('says how many gifts will be deleted when removing a person', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $mum = personFor($occasion, 'Mum');
    personFor($occasion, 'Dad');
    giftFor($user, $occasion, $mum);

    giftsPage($user, $occasion)
        ->assertSeeHtml('wire:confirm="Remove Dad from this occasion?"')
        ->assertSeeHtml('wire:confirm="Remove Mum and their 1 gift from this occasion?"');

    giftFor($user, $occasion, $mum, ['title' => 'Mug']);

    giftsPage($user, $occasion)->assertSeeHtml('wire:confirm="Remove Mum and their 2 gifts from this occasion?"');
});

it('removes the person\'s gifts from the page when they are removed', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $mum = personFor($occasion, 'Mum');
    giftFor($user, $occasion, $mum);

    giftsPage($user, $occasion)
        ->call('remove', $mum->id)
        ->assertDontSeeHtml('data-gift="Scarf"');
});

it('shows each gift\'s status on the occasion page', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    giftFor($user, $occasion, personFor($occasion, 'Mum'));

    giftsPage($user, $occasion)
        ->assertSeeHtml('data-status="Idea"')
        ->assertSeeHtml('Mark bought');
});

it('advances a gift one step with one click', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $gift = giftFor($user, $occasion, personFor($occasion, 'Mum'));

    giftsPage($user, $occasion)->call('advanceGift', $gift->id, 'idea')
        ->assertSeeHtml('data-status="Bought"')
        ->assertSeeHtml('Mark wrapped');

    expect($gift->refresh()->status)->toBe(GiftStatus::Bought);
});

it('shows who bought a gift once it is marked bought', function () {
    $user = User::factory()->create(['name' => 'Sam']);
    $occasion = christmasFor($user);
    $gift = giftFor($user, $occasion, personFor($occasion, 'Mum'));

    giftsPage($user, $occasion)->call('advanceGift', $gift->id, 'idea')
        ->assertSeeHtml('data-buyer="Sam"')
        ->assertSee('Bought by Sam');
});

it('shows no advance button on a given gift', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $gift = giftFor($user, $occasion, personFor($occasion, 'Mum'));
    $gift->forceFill(['status' => GiftStatus::Given, 'purchased_by_user_id' => $user->id])->save();

    giftsPage($user, $occasion)->assertDontSeeHtml('data-test="advance-gift-button"');
});

it('refuses to advance another household\'s gift from the page', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $other = User::factory()->create();
    $otherOccasion = christmasFor($other);
    $gift = giftFor($other, $otherOccasion, personFor($otherOccasion, 'Dad'));

    giftsPage($user, $occasion)->call('advanceGift', $gift->id, 'idea')->assertNotFound();

    expect($gift->refresh()->status)->toBe(GiftStatus::Idea);
});

it('does not skip a step when the gift was already advanced in another window', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $gift = giftFor($user, $occasion, personFor($occasion, 'Mum'));

    $page = giftsPage($user, $occasion);
    $page->call('advanceGift', $gift->id, 'idea')->call('advanceGift', $gift->id, 'idea');

    expect($gift->refresh()->status)->toBe(GiftStatus::Bought);
});

it('adds a gift that is already bought, with its buyer', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $mum = personFor($occasion, 'Mum');

    giftsPage($user, $occasion)
        ->call('addGift', $mum->id)
        ->set('giftForm.title', 'Scarf')
        ->set('giftForm.status', 'bought')
        ->set('giftForm.buyerId', (string) $user->id)
        ->call('saveGift')
        ->assertHasNoErrors();

    $gift = Gift::firstWhere('title', 'Scarf');
    expect($gift->status)->toBe(GiftStatus::Bought)->and($gift->purchased_by_user_id)->toBe($user->id);
});

it('sets a gift back to Idea from the edit window and clears its buyer', function () {
    $user = User::factory()->create();
    $occasion = christmasFor($user);
    $gift = giftFor($user, $occasion, personFor($occasion, 'Mum'), ['status' => GiftStatus::Bought, 'purchased_by_user_id' => $user->id]);

    giftsPage($user, $occasion)
        ->call('editGift', $gift->id)
        ->set('giftForm.status', 'idea')
        ->set('giftForm.buyerId', '')
        ->call('saveGift')
        ->assertHasNoErrors();

    expect($gift->fresh())->status->toBe(GiftStatus::Idea)->purchased_by_user_id->toBeNull();
});

it('changes who bought a gift from the edit window', function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $occasion = christmasFor($user);
    $gift = giftFor($user, $occasion, personFor($occasion, 'Mum'), ['status' => GiftStatus::Bought, 'purchased_by_user_id' => $user->id]);

    giftsPage($user, $occasion)
        ->call('editGift', $gift->id)
        ->set('giftForm.buyerId', (string) $partner->id)
        ->call('saveGift')
        ->assertHasNoErrors();

    expect($gift->fresh()->purchased_by_user_id)->toBe($partner->id);
});

it('offers both household members as buyers in the gift window', function () {
    $user = User::factory()->create(['name' => 'Jason']);
    $partner = User::factory()->create(['name' => 'Sam', 'household_id' => $user->household_id]);
    $occasion = christmasFor($user);
    personFor($occasion, 'Mum');

    giftsPage($user, $occasion)
        ->assertSeeHtml('value="'.$user->id.'"')
        ->assertSeeHtml('value="'.$partner->id.'"')
        ->assertSeeHtml('value="bought"')
        ->assertSee(['Jason', 'Sam']);
});
