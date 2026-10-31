<?php

declare(strict_types=1);

use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Livewire\Livewire;

/**
 * An occasion with one person (budget in cents or null) holding an Idea of $9.99 and a bought gift of $spent cents.
 *
 * @return array{Testable, User, Occasion}
 */
function overview(?int $budget, int $spent): array
{
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();
    $person = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Mum']);
    $occasion->recipients()->attach($person, ['budget_cents' => $budget]);
    Gift::factory()->forPersonOn($person, $occasion)->bought($user)->create(['price_cents' => $spent]);
    Gift::factory()->forPersonOn($person, $occasion)->create(['price_cents' => 999]);

    return [Livewire::actingAs($user)->test('pages::occasions.show', ['occasion' => $occasion->id]), $user, $occasion];
}

it('shows what has been spent on each person', function () {
    [$page] = overview(10000, 2500);

    $page->assertSeeHtml('data-spent="$25"')->assertSee('Spent $25');
});

it("shows how much of each person's budget is left", function () {
    [$page] = overview(10000, 2500);

    $page->assertSeeHtml('data-remaining="$75"')->assertSee('$75 left');
});

it('marks a person who is over budget', function () {
    [$page] = overview(2000, 3250);

    $page->assertSeeHtml('data-remaining="-$12.50"')->assertSee('$12.50 over')->assertDontSee('left')->assertSeeHtml('data-over-budget');
});

it('shows spent but no remaining for a person with no budget', function () {
    [$page] = overview(null, 2500);

    $page->assertSeeHtml('data-spent="$25"')->assertSeeHtml('data-remaining=""')->assertDontSee('left')->assertDontSeeHtml('data-over-budget');
});

it("shows the season's budget, spent and remaining", function () {
    [$page] = overview(10000, 2500);

    $page->assertSeeHtml('data-total-budget="$100"')
        ->assertSeeHtml('data-total-spent="$25"')
        ->assertSeeHtml('data-total-remaining="$75"')
        ->assertSee('Remaining $75');
});

it('lists the people who have no gift yet', function () {
    [$page, $user, $occasion] = overview(null, 2500);
    $occasion->recipients()->attach(Recipient::factory()->forHousehold($user->household)->create(['name' => 'Grandpa']));

    $page->call('$refresh')->assertSee('No gift yet')->assertSeeHtml('data-no-gift="Grandpa"')->assertDontSeeHtml('data-no-gift="Mum"');
});

it('hides the no gift section when everyone has a gift', function () {
    [$page] = overview(null, 2500);

    $page->assertDontSee('No gift yet')->assertDontSeeHtml('data-no-gift');
});

it('takes a person off the no gift list once a gift is added for them', function () {
    [$page, $user, $occasion] = overview(null, 2500);
    $grandpa = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Grandpa']);
    $occasion->recipients()->attach($grandpa);

    $page->call('$refresh')->assertSeeHtml('data-no-gift="Grandpa"')
        ->call('addGift', $grandpa->id)
        ->set('giftForm.title', 'Book')
        ->call('saveGift')
        ->assertDontSeeHtml('data-no-gift');
});

it('updates spent and remaining as soon as a gift is marked bought', function () {
    [$page, $user, $occasion] = overview(10000, 2500);
    $idea = $occasion->gifts()->where('price_cents', 999)->firstOrFail();

    $page->call('advanceGift', $idea->id, $idea->status->value)
        ->call('advanceGift', $idea->id, $idea->status->next()->value)
        ->assertSeeHtml('data-total-spent="$34.99"')
        ->assertSeeHtml('data-total-remaining="$65.01"');
});

it('updates spent and remaining when a bought gift is deleted', function () {
    [$page, , $occasion] = overview(10000, 2500);
    $bought = $occasion->gifts()->where('price_cents', 2500)->firstOrFail();

    $page->call('deleteGift', $bought->id)
        ->assertSeeHtml('data-total-spent="$0"')
        ->assertSeeHtml('data-total-remaining="$100"');
});

it('updates remaining as soon as a budget is changed', function () {
    [$page, , $occasion] = overview(10000, 2500);

    $page->set('budgets.'.$occasion->recipients()->firstOrFail()->id, '50')
        ->assertSeeHtml('data-total-remaining="$25"');
});

it("updates spent when a bought gift's price is changed", function () {
    [$page, , $occasion] = overview(10000, 2500);
    $bought = $occasion->gifts()->where('price_cents', 2500)->firstOrFail();

    $page->call('editGift', $bought->id)
        ->set('giftForm.price', '40')
        ->call('saveGift')
        ->assertSeeHtml('data-total-spent="$40"');
});

it('updates the season totals when a person is removed', function () {
    [$page, , $occasion] = overview(10000, 2500);

    $page->call('remove', $occasion->recipients()->firstOrFail()->id)
        ->assertSeeHtml('data-total-spent="$0"')
        ->assertSeeHtml('data-total-budget="$0"');
});
