<?php

declare(strict_types=1);

use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * @return array{Testable, User, Occasion, Recipient}
 */
function giftRowXpath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);

    return new DOMXPath($dom);
}

function layoutPage(): array
{
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();
    $mum = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Mum']);
    $occasion->recipients()->attach($mum, ['budget_cents' => 10000]);
    Gift::factory()->forPersonOn($mum, $occasion)->bought($user)->create(['price_cents' => 2500]);

    return [Livewire::actingAs($user)->test('pages::occasions.show', ['occasion' => $occasion->id]), $user, $occasion, $mum];
}

it('shows the budget, spent and remaining totals as tiles under the occasion name', function () {
    [$page] = layoutPage();

    $page->assertSeeHtml('data-total-budget="$100"')
        ->assertSeeHtml('grid-cols-2 gap-3 sm:grid-cols-4')
        ->assertSee('Total budget $100')
        ->assertSee('Spent $25')
        ->assertSee('Remaining $75');
});

it('shows the my private gifts tile only to the owner of a private gift', function () {
    [$page, $user, $occasion, $mum] = layoutPage();
    Gift::factory()->forPersonOn($mum, $occasion)->bought($user)->private($user)->create(['price_cents' => 1000]);

    $page->call('$refresh')->assertSee('My private gifts: $10 spent');

    $partner = User::factory()->create(['household_id' => $user->household_id]);
    Livewire::actingAs($partner)->test('pages::occasions.show', ['occasion' => $occasion->id])
        ->assertDontSee('My private gifts');
});

it('shows a no gift yet badge on the row of a person with no gifts', function () {
    [$page, $user, $occasion] = layoutPage();
    $occasion->recipients()->attach(Recipient::factory()->forHousehold($user->household)->create(['name' => 'Grandpa']));

    $page->call('$refresh')->assertSee('No gift yet');

    expect($page->html())->toMatch('/data-person="Grandpa".*?data-no-gift="Grandpa"/s');
});

it('does not show a no gift yet badge on a person who has a gift', function () {
    [$page] = layoutPage();

    $page->assertDontSeeHtml('data-no-gift')->assertDontSee('No gift yet');
});

it('no longer shows a separate no gift yet list above the groups', function () {
    [$page, $user, $occasion] = layoutPage();
    $occasion->recipients()->attach(Recipient::factory()->forHousehold($user->household)->create(['name' => 'Grandpa']));

    expect(Str::before($page->call('$refresh')->html(), 'data-group='))->not->toContain('No gift yet');
});

it('keeps the shopping list, wrapping checklist and add people buttons in the header', function () {
    [$page] = layoutPage();

    $page->assertSeeHtml('data-test="shopping-list-link"')
        ->assertSeeHtml('data-test="wrapping-checklist-link"')
        ->assertSeeHtml('data-test="add-people-button"');
});

it("shows each group's people inside one bordered box", function () {
    [$page] = layoutPage();

    $page->assertSeeHtml('divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700');
});

it('lists people in the order of their group under the group heading', function () {
    [$page, $user, $occasion, $mum] = layoutPage();
    $occasion->recipients()->attach(Recipient::factory()->forHousehold($user->household)->create(['name' => 'Zed', 'group' => $mum->group]));

    expect($page->call('$refresh')->html())->toMatch('/\('.'2\).*?data-person="Mum".*?data-person="Zed"/s');
});

it("puts the remove choice in the person's more menu with the same warning", function () {
    [$page] = layoutPage();

    expect($page->html())->toMatch('/data-flux-menu.*?wire:click="remove\(\d+\)".*?data-test="remove-person-button"/s')
        ->toContain('wire:confirm="Remove Mum and their 1 gift from this occasion?"');
});

it('keeps the budget box on the person row and saves a new budget when it loses focus', function () {
    [$page, , $occasion, $mum] = layoutPage();

    $page->assertSeeHtml('wire:model.live.blur="budgets.'.$mum->id.'"')
        ->set('budgets.'.$mum->id, '150');

    expect($occasion->recipients()->first()->pivot->budget_cents)->toBe(15000);
});

it('shows spent and amount left together at the right of the person row', function () {
    [$page] = layoutPage();

    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$page->html());
    $actions = (new DOMXPath($dom))->query('//*[@data-person="Mum"]/*[contains(concat(" ", normalize-space(@class), " "), " shrink-0 ")][last()]')->item(0);

    expect($actions)->not->toBeNull()
        ->and(preg_replace('/\s+/', ' ', (string) $actions?->textContent))->toMatch('/Spent \$25 .*\$75 left/');
});

it('shows an over budget badge instead of amount left when spending passes the budget', function () {
    [$page, $user, $occasion, $mum] = layoutPage();
    Gift::factory()->forPersonOn($mum, $occasion)->bought($user)->create(['price_cents' => 9000]);

    $page->call('$refresh')->assertSeeHtml('data-over-budget')->assertSee('$15 over')->assertDontSee('left');
});

it('shows each gift as a row under its person', function () {
    [$page] = layoutPage();

    expect($page->html())->toMatch('/data-person="Mum".*?data-gift=/s')
        ->toMatch('/data-gift=[^>]*class="flex items-center justify-between gap-4"/s');
});

it('keeps the mark next status button visible on a gift row', function () {
    [$page] = layoutPage();

    expect($page->html())->toMatch('/data-gift=.*?data-test="advance-gift-button"/s')
        ->and($page->html())->not->toMatch('/data-flux-menu[^<]*<[^>]*advance-gift-button/s');
    $page->assertSee('Mark wrapped');
});

it("puts edit and delete in the gift's more menu", function () {
    [$page] = layoutPage();

    expect($page->html())->toMatch('/data-flux-menu.*?data-test="edit-gift-button".*?data-test="delete-gift-button"/s');
});

it('asks for confirmation before deleting a gift from the menu', function () {
    [$page] = layoutPage();

    $html = $page->html();
    $title = Gift::query()->firstOrFail()->title;

    expect(giftRowXpath($html)->query('//*[@data-gift]//*[@data-flux-menu]//*[@data-test="delete-gift-button"]')->item(0)?->getAttribute('wire:confirm'))
        ->toBe("Delete {$title}?");
});

it('shows the gift status as a coloured badge', function () {
    [$page] = layoutPage();

    $badge = giftRowXpath($page->html())->query('//*[@data-gift]//*[@data-flux-badge][normalize-space()="Bought"]')->item(0);

    expect($badge)->not->toBeNull()
        ->and((string) $badge?->getAttribute('class'))->toContain('blue');
});

it('shows a toggle with the count of year-round ideas for a person', function () {
    [$page, , $occasion, $mum] = layoutPage();
    Gift::factory()->count(2)->forPersonOn($mum, $occasion)->yearRound()->create();

    $page->call('$refresh')->assertSee('Year-round ideas (2)');
});

it('still renders the year-round ideas so they can be added to the occasion', function () {
    [$page, , $occasion, $mum] = layoutPage();
    Gift::factory()->forPersonOn($mum, $occasion)->yearRound()->create(['title' => 'Slippers']);

    $page->call('$refresh')->assertSeeHtml('data-year-round-idea="Slippers"')->assertSeeHtml('x-cloak');
});
