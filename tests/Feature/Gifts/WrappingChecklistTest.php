<?php

declare(strict_types=1);

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Livewire\Livewire;

function wrappingGift(User $user, Occasion $occasion, string $person, string $title, GiftStatus $status): Gift
{
    $recipient = Recipient::where('household_id', $user->household_id)->where('name', $person)->first()
        ?? Recipient::factory()->forHousehold($user->household)->create(['name' => $person]);
    $occasion->recipients()->syncWithoutDetaching([$recipient->id => ['budget_cents' => null]]);

    $gift = new Gift(['title' => $title]);
    $gift->household_id = $user->household_id;
    $gift->occasion_id = $occasion->id;
    $gift->recipient_id = $recipient->id;
    $gift->created_by_user_id = $user->id;
    $gift->forceFill(['status' => $status]);
    $gift->save();

    return $gift;
}

function wrappingSetup(): array
{
    $user = User::factory()->create();

    return [$user, Occasion::factory()->forHousehold($user->household)->christmas(2026)->create()];
}

function wrappingPage(User $user, Occasion $occasion)
{
    return Livewire::actingAs($user)->test('pages::occasions.wrapping', ['occasion' => $occasion->id]);
}

it('shows every bought and wrapped gift with who it is for', function () {
    [$user, $occasion] = wrappingSetup();
    wrappingGift($user, $occasion, 'Mum', 'Scarf', GiftStatus::Bought);
    wrappingGift($user, $occasion, 'Dad', 'Socks', GiftStatus::Wrapped);
    wrappingGift($user, $occasion, 'Dad', 'Idea one', GiftStatus::Idea);
    wrappingGift($user, $occasion, 'Dad', 'Given one', GiftStatus::Given);

    wrappingPage($user, $occasion)
        ->assertSeeHtml('data-wrap="Scarf"')
        ->assertSeeHtml('data-for="Mum"')
        ->assertSeeHtml('data-wrap="Socks"')
        ->assertSeeHtml('data-for="Dad"')
        ->assertDontSeeHtml('data-wrap="Idea one"')
        ->assertDontSeeHtml('data-wrap="Given one"')
        ->assertSee('Wrapping checklist · Christmas 2026')
        ->assertSee('Back to Christmas 2026')
        ->assertSeeHtml('aria-label="Wrapped: Scarf for Mum"');
});

it('shows which gifts are already wrapped', function () {
    [$user, $occasion] = wrappingSetup();
    wrappingGift($user, $occasion, 'Mum', 'Scarf', GiftStatus::Bought);
    wrappingGift($user, $occasion, 'Mum', 'Hat', GiftStatus::Wrapped);

    wrappingPage($user, $occasion)
        ->assertSeeHtml('data-wrap="Scarf"')
        ->assertSeeHtmlInOrder(['data-wrap="Scarf"', 'data-wrapped="no"', 'data-wrap="Hat"', 'data-wrapped="yes"'])
        ->assertSeeHtml('wire:click="setWrapped(')
        ->assertSeeHtml(', true)')
        ->assertSeeHtml(', false)');
});

it('shows how many of the gifts are wrapped', function () {
    [$user, $occasion] = wrappingSetup();
    wrappingGift($user, $occasion, 'Mum', 'Scarf', GiftStatus::Bought);
    wrappingGift($user, $occasion, 'Mum', 'Hat', GiftStatus::Wrapped);

    wrappingPage($user, $occasion)
        ->assertSeeHtml('data-wrapped-count="1"')
        ->assertSeeHtml('data-to-wrap-count="2"')
        ->assertSee('1 of 2 wrapped');
});

it('says there is nothing to wrap yet when no gift is bought', function () {
    [$user, $occasion] = wrappingSetup();
    wrappingGift($user, $occasion, 'Mum', 'Scarf', GiftStatus::Idea);

    wrappingPage($user, $occasion)
        ->assertSee('Nothing to wrap yet.')
        ->assertSee('0 of 0 wrapped');
});

it("returns not found for another household's wrapping checklist", function () {
    [, $occasion] = wrappingSetup();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('occasions.wrapping', $occasion))->assertNotFound();
});

it('links to the wrapping checklist from the occasion page', function () {
    [$user, $occasion] = wrappingSetup();

    Livewire::actingAs($user)->test('pages::occasions.show', ['occasion' => $occasion->id])
        ->assertSeeHtml('data-test="wrapping-checklist-link"')
        ->assertSeeHtml(route('occasions.wrapping', $occasion));
});

it('sends the state to switch to from each checkbox', function () {
    [$user, $occasion] = wrappingSetup();
    $bought = wrappingGift($user, $occasion, 'Mum', 'Scarf', GiftStatus::Bought);
    $wrapped = wrappingGift($user, $occasion, 'Mum', 'Hat', GiftStatus::Wrapped);

    wrappingPage($user, $occasion)
        ->assertSeeHtml('wire:click="setWrapped('.$bought->id.', true)"')
        ->assertSeeHtml('wire:click="setWrapped('.$wrapped->id.', false)"');
});

it('marks a gift wrapped when it is ticked', function () {
    [$user, $occasion] = wrappingSetup();
    $gift = wrappingGift($user, $occasion, 'Mum', 'Scarf', GiftStatus::Bought);

    wrappingPage($user, $occasion)->call('setWrapped', $gift->id, true);

    expect($gift->fresh()->status)->toBe(GiftStatus::Wrapped);
});

it('puts a gift back to bought when it is unticked', function () {
    [$user, $occasion] = wrappingSetup();
    $gift = wrappingGift($user, $occasion, 'Mum', 'Scarf', GiftStatus::Wrapped);

    wrappingPage($user, $occasion)->call('setWrapped', $gift->id, false);

    expect($gift->fresh()->status)->toBe(GiftStatus::Bought);
});

it('updates how many are wrapped straight away', function () {
    [$user, $occasion] = wrappingSetup();
    $gift = wrappingGift($user, $occasion, 'Mum', 'Scarf', GiftStatus::Bought);

    wrappingPage($user, $occasion)
        ->assertSee('0 of 1 wrapped')
        ->call('setWrapped', $gift->id, true)
        ->assertSee('1 of 1 wrapped');
});

it('does not unwrap a gift when a stale tick is clicked again', function () {
    [$user, $occasion] = wrappingSetup();
    $gift = wrappingGift($user, $occasion, 'Mum', 'Scarf', GiftStatus::Bought);

    wrappingPage($user, $occasion)
        ->call('setWrapped', $gift->id, true)
        ->call('setWrapped', $gift->id, true);

    expect($gift->fresh()->status)->toBe(GiftStatus::Wrapped);
});

it("refuses to tick another household's gift", function () {
    [, $occasion] = wrappingSetup();
    $other = User::factory()->create();
    $theirs = wrappingGift($other, Occasion::factory()->forHousehold($other->household)->christmas(2026)->create(), 'Nan', 'Shawl', GiftStatus::Bought);
    [$user, $mine] = wrappingSetup();

    wrappingPage($user, $mine)->call('setWrapped', $theirs->id, true)->assertNotFound();
    expect($theirs->fresh()->status)->toBe(GiftStatus::Bought);
});

it('badges my private gifts on the wrapping checklist', function () {
    [$user, $occasion] = wrappingSetup();
    wrappingGift($user, $occasion, 'Mum', 'Perfume', GiftStatus::Bought)->forceFill(['is_private' => true])->save();
    wrappingGift($user, $occasion, 'Mum', 'Scarf', GiftStatus::Bought);

    wrappingPage($user, $occasion)
        ->assertSeeHtmlInOrder(['data-wrap="Perfume"', 'data-private="yes"', 'Private', 'data-wrap="Scarf"', 'data-private="no"'])
        ->assertSee('Private', 1);
});

it('shows my partner nothing about my private gifts on the wrapping checklist', function () {
    [$user, $occasion] = wrappingSetup();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    wrappingGift($user, $occasion, 'Mum', 'Perfume', GiftStatus::Bought)->forceFill(['is_private' => true])->save();

    wrappingPage($partner, $occasion)
        ->assertDontSee('Perfume')
        ->assertDontSee('Private')
        ->assertSeeHtml('data-to-wrap-count="0"');
});
