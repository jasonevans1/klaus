<?php

declare(strict_types=1);

use App\Enums\RecipientGroup;
use App\Models\Recipient;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function peoplePage(User $user)
{
    return Livewire::actingAs($user)->test('pages::people.index');
}

it('lists my people under their group headings in order', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->create(['name' => 'Pat Friend', 'group' => RecipientGroup::Friends]);
    Recipient::factory()->forHousehold($user->household)->create(['name' => 'Zed Family', 'group' => RecipientGroup::Family]);
    Recipient::factory()->forHousehold($user->household)->create(['name' => 'Abe Family', 'group' => RecipientGroup::Family]);

    peoplePage($user)->assertSeeInOrder(['Family', 'Abe Family', 'Zed Family', 'Friends', 'Pat Friend']);
});

it("does not show another household's people", function () {
    $user = User::factory()->create();
    Recipient::factory()->create(['name' => 'Stranger Danger']);

    peoplePage($user)->assertDontSee('Stranger Danger')->assertSee('No people yet.');
});

it('marks shared entries with a badge', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->shared()->create(['name' => 'The Smiths']);

    peoplePage($user)->assertSeeInOrder(['The Smiths', 'Shared']);
});

it("shows each person's birthday and age when the year is known", function () {
    $this->travelTo(Carbon\Carbon::parse('2026-06-01'));
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->withBirthday(3, 14, 1980)->create(['name' => 'Mom']);
    Recipient::factory()->forHousehold($user->household)->withBirthday(5, 2)->create(['name' => 'Dad']);

    peoplePage($user)->assertSee('Mar 14, 1980 · 46')->assertSee('May 2')->assertDontSee('May 2,');
});

it('hides archived people until show archived is on', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->archived()->create(['name' => 'Old Flame']);

    peoplePage($user)->assertDontSee('Old Flame')
        ->set('showArchived', true)
        ->assertSee('Old Flame')->assertSee('Archived');
});

it('still offers show archived when everyone is archived', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->archived()->create();

    peoplePage($user)->assertSee('No people yet.')->assertSee('Show archived');
});

it('archives and restores a person from the list', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Bob']);

    peoplePage($user)->call('archive', $person->id)->assertDontSee('Bob')
        ->set('showArchived', true)->assertSee('Bob')
        ->call('restore', $person->id);

    expect($person->fresh()->archived_at)->toBeNull();
});

it('shows the people link in the sidebar', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee(route('people.index'), false)
        ->assertDontSee('Documentation');
});

function peopleXpath(Testable $page): DOMXPath
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$page->html());

    return new DOMXPath($dom);
}

function personRowPath(string $name, string $inner): string
{
    return '//*[@data-person="'.$name.'"]'.$inner;
}

it('shows each group of people inside one bordered list', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->create(['name' => 'Abe', 'group' => RecipientGroup::Family]);

    $xpath = peopleXpath(peoplePage($user));

    expect($xpath->query('//*[@data-group]/*[contains(@class,"border") and contains(@class,"rounded-xl") and contains(@class,"divide-y")]/*[@data-person="Abe"]')->length)->toBe(1);
});

it('keeps the edit button and the more-actions menu together on the right of each person row', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->create(['name' => 'Abe']);

    $xpath = peopleXpath(peoplePage($user));
    $actions = $xpath->query(personRowPath('Abe', '/*[last()][contains(@class,"shrink-0")]'));

    expect($actions->length)->toBe(1)
        ->and($xpath->query('.//*[@data-test="edit-person-button"]', $actions->item(0))->length)->toBe(1)
        ->and($xpath->query('.//*[@data-flux-dropdown]', $actions->item(0))->length)->toBe(1)
        ->and($xpath->query('.//*[@data-flux-dropdown]//button[@aria-label="More actions"]', $actions->item(0))->length)->toBe(1);
});

it('puts archive inside the more-actions menu for an active person', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->create(['name' => 'Abe']);

    $xpath = peopleXpath(peoplePage($user));

    expect($xpath->query(personRowPath('Abe', '//*[@data-flux-dropdown]//*[@data-test="archive-person-button"]'))->length)->toBe(1);
});

it('puts restore inside the more-actions menu for an archived person', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->archived()->create(['name' => 'Old Flame']);

    $xpath = peopleXpath(peoplePage($user)->set('showArchived', true));

    expect($xpath->query(personRowPath('Old Flame', '//*[@data-flux-dropdown]//*[@data-test="restore-person-button"]'))->length)->toBe(1);
});

it('keeps the edit button visible outside the more-actions menu', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->create(['name' => 'Abe']);

    $xpath = peopleXpath(peoplePage($user));

    expect($xpath->query(personRowPath('Abe', '//*[@data-test="edit-person-button"]'))->length)->toBe(1)
        ->and($xpath->query(personRowPath('Abe', '//*[@data-flux-dropdown]//*[@data-test="edit-person-button"]'))->length)->toBe(0);
});

it('archives a person from the more-actions menu', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Abe']);

    $page = peoplePage($user);
    $item = peopleXpath($page)->query(personRowPath('Abe', '//*[@data-flux-dropdown]//*[@data-test="archive-person-button"]'))->item(0);
    preg_match('/^(\w+)\((.*)\)$/', $item->getAttribute('wire:click'), $call);
    $page->call($call[1], (int) $call[2]);

    expect($person->fresh()->archived_at)->not->toBeNull();
});
