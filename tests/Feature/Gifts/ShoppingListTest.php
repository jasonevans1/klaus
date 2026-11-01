<?php

declare(strict_types=1);

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Livewire\Livewire;

function shoppingGift(User $user, Occasion $occasion, string $person, array $attrs = []): Gift
{
    $recipient = Recipient::where('household_id', $user->household_id)->where('name', $person)->first()
        ?? Recipient::factory()->forHousehold($user->household)->create(['name' => $person]);
    $occasion->recipients()->syncWithoutDetaching([$recipient->id => ['budget_cents' => null]]);

    $gift = new Gift(array_merge(['title' => 'Scarf', 'price_cents' => null, 'store' => null, 'url' => null], $attrs));
    $gift->household_id = $user->household_id;
    $gift->occasion_id = $occasion->id;
    $gift->recipient_id = $recipient->id;
    $gift->created_by_user_id = $user->id;
    $gift->forceFill(['status' => $attrs['status'] ?? GiftStatus::Idea]);
    $gift->save();

    return $gift;
}

function shoppingSetup(): array
{
    $user = User::factory()->create();

    return [$user, Occasion::factory()->forHousehold($user->household)->christmas(2026)->create()];
}

function shoppingPage(User $user, Occasion $occasion)
{
    return Livewire::actingAs($user)->test('pages::occasions.shopping', ['occasion' => $occasion->id]);
}

it('shows every idea on the occasion with who it is for, its price, store and link', function () {
    [$user, $occasion] = shoppingSetup();
    shoppingGift($user, $occasion, 'Mum', ['title' => 'Scarf', 'price_cents' => 4050, 'store' => 'Target', 'url' => 'https://t.example/s']);

    shoppingPage($user, $occasion)
        ->assertSeeHtml('data-idea="Scarf"')
        ->assertSeeHtml('data-for="Mum"')
        ->assertSeeHtml('data-price="$40.50"')
        ->assertSeeHtml('data-store="Target"')
        ->assertSeeHtml('data-url="https://t.example/s"')
        ->assertSeeHtml('target="_blank" rel="noopener noreferrer"');
});

it('does not show gifts that are already bought, wrapped or given', function () {
    [$user, $occasion] = shoppingSetup();
    shoppingGift($user, $occasion, 'Mum', ['title' => 'Idea one']);
    foreach ([GiftStatus::Bought, GiftStatus::Wrapped, GiftStatus::Given] as $status) {
        shoppingGift($user, $occasion, 'Mum', ['title' => "Done {$status->value}", 'status' => $status]);
    }

    shoppingPage($user, $occasion)
        ->assertSeeHtml('data-idea="Idea one"')
        ->assertDontSeeHtml('data-idea="Done');
});

it('shows how many ideas are left and what they cost in total', function () {
    [$user, $occasion] = shoppingSetup();
    shoppingGift($user, $occasion, 'Mum', ['title' => 'A', 'price_cents' => 8000]);
    shoppingGift($user, $occasion, 'Dad', ['title' => 'B', 'price_cents' => 1500]);
    shoppingGift($user, $occasion, 'Dad', ['title' => 'C']);

    shoppingPage($user, $occasion)
        ->assertSeeHtml('data-ideas-count="3"')
        ->assertSeeHtml('data-ideas-total="$95"')
        ->assertSee('3 ideas · $95');
});

it('says there is nothing left to buy when there are no ideas', function () {
    [$user, $occasion] = shoppingSetup();

    shoppingPage($user, $occasion)
        ->assertSeeHtml('data-ideas-count="0"')
        ->assertSee('0 ideas · $0')
        ->assertSee('Nothing left to buy.');
});

it("returns not found for another household's occasion", function () {
    [, $occasion] = shoppingSetup();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('occasions.shopping', $occasion))->assertNotFound();
});

it('links to the shopping list from the occasion page', function () {
    [$user, $occasion] = shoppingSetup();

    Livewire::actingAs($user)->test('pages::occasions.show', ['occasion' => $occasion->id])
        ->assertSeeHtml('data-test="shopping-list-link"')
        ->assertSeeHtml(route('occasions.shopping', $occasion));
});

it('marks an idea bought by whoever clicked', function () {
    [$user, $occasion] = shoppingSetup();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $gift = shoppingGift($user, $occasion, 'Mum');

    shoppingPage($partner, $occasion)->call('markBought', $gift->id);

    expect($gift->refresh()->status)->toBe(GiftStatus::Bought)
        ->and($gift->purchased_by_user_id)->toBe($partner->id);
});

it('takes a bought gift off the shopping list straight away', function () {
    [$user, $occasion] = shoppingSetup();
    $gift = shoppingGift($user, $occasion, 'Mum', ['title' => 'Scarf']);

    shoppingPage($user, $occasion)
        ->assertSeeHtml('data-idea="Scarf"')
        ->call('markBought', $gift->id)
        ->assertDontSeeHtml('data-idea="Scarf"');
});

it('updates the count and total after marking a gift bought', function () {
    [$user, $occasion] = shoppingSetup();
    $gift = shoppingGift($user, $occasion, 'Mum', ['title' => 'A', 'price_cents' => 8000]);
    shoppingGift($user, $occasion, 'Dad', ['title' => 'B', 'price_cents' => 1500]);

    shoppingPage($user, $occasion)
        ->call('markBought', $gift->id)
        ->assertSeeHtml('data-ideas-count="1"')
        ->assertSeeHtml('data-ideas-total="$15"');
});

it('does not move a gift past bought when it was already bought in another window', function () {
    [$user, $occasion] = shoppingSetup();
    $gift = shoppingGift($user, $occasion, 'Mum');
    $partner = User::factory()->create(['household_id' => $user->household_id]);

    $page = shoppingPage($user, $occasion);
    shoppingPage($partner, $occasion)->call('markBought', $gift->id);
    $page->call('markBought', $gift->id);

    expect($gift->refresh()->status)->toBe(GiftStatus::Bought)
        ->and($gift->purchased_by_user_id)->toBe($partner->id);
});

it("refuses to mark another household's gift bought", function () {
    [$user, $occasion] = shoppingSetup();
    [$stranger, $theirs] = shoppingSetup();
    $gift = shoppingGift($stranger, $theirs, 'Mum');

    shoppingPage($user, $occasion)->call('markBought', $gift->id)->assertNotFound();

    expect($gift->refresh()->status)->toBe(GiftStatus::Idea);
});

it('badges my private ideas on the shopping list', function () {
    [$user, $occasion] = shoppingSetup();
    shoppingGift($user, $occasion, 'Mum', ['title' => 'Perfume'])->forceFill(['is_private' => true])->save();
    shoppingGift($user, $occasion, 'Mum', ['title' => 'Scarf']);

    shoppingPage($user, $occasion)
        ->assertSeeHtml('data-idea="Perfume"')
        ->assertSeeHtmlInOrder(['data-idea="Perfume"', 'data-private="yes"', 'Private', 'data-idea="Scarf"', 'data-private="no"'])
        ->assertSee('Private', 1);
});

it('shows my partner nothing about my private ideas on the shopping list', function () {
    [$user, $occasion] = shoppingSetup();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    shoppingGift($user, $occasion, 'Mum', ['title' => 'Perfume', 'price_cents' => 6400])->forceFill(['is_private' => true])->save();

    shoppingPage($partner, $occasion)
        ->assertDontSee('Perfume')
        ->assertDontSee('Private')
        ->assertSeeHtml('data-ideas-count="0"');
});
