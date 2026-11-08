<?php

declare(strict_types=1);

use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Livewire\Livewire;

function occasionsPage(User $user)
{
    return Livewire::actingAs($user)->test('pages::occasions.index');
}

it('lists my occasions newest first', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2025)->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();

    occasionsPage($user)->assertSeeInOrder(['Christmas 2026', 'Dec 25, 2026', 'Christmas 2025', 'Dec 25, 2025']);
});

it("does not show another household's occasions", function () {
    $user = User::factory()->create();
    Occasion::factory()->christmas(2024)->create();

    occasionsPage($user)->assertDontSee('Christmas 2024')->assertSee('No occasions yet.');
});

it('creates a christmas from the modal and opens it', function () {
    $user = User::factory()->create();

    $page = occasionsPage($user)->assertSet('year', now()->year)->set('year', 2030)->call('createChristmas');

    $occasion = Occasion::where('household_id', $user->household_id)->firstOrFail();
    expect($occasion->name)->toBe('Christmas 2030');
    $page->assertRedirect(route('occasions.show', $occasion->id));
});

it('shows an error when christmas for that year already exists', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2030)->create();

    occasionsPage($user)->set('year', 2030)->call('createChristmas')
        ->assertHasErrors(['year'])->assertSee('You already have Christmas 2030.');
});

it('deletes an occasion', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->christmas(2030)->create();

    occasionsPage($user)->call('delete', $occasion->id)->assertDontSee('Christmas 2030');

    expect(Occasion::count())->toBe(0);
});

it("returns not found for another household's occasion page", function () {
    $user = User::factory()->create();
    $other = Occasion::factory()->create();

    $this->actingAs($user)->get(route('occasions.show', $other->id))->assertNotFound();
});

it('returns not found for a non-numeric occasion url', function () {
    $this->actingAs(User::factory()->create())->get('/occasions/abc')->assertNotFound();
});

it('shows the occasions link in the sidebar', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertSee(route('occasions.index'));
});

it('says how many gifts will be deleted when confirming an occasion delete', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    Gift::factory()->count(3)->forPersonOn($person, $occasion)->create();

    occasionsPage($user)->assertSeeHtml('Delete Christmas 2026 and its 3 gifts?');
});

it('keeps the plain delete confirmation for an occasion with no gifts', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();

    occasionsPage($user)->assertSeeHtml('Delete Christmas 2026?');
});

it('does not count my partner\'s private gifts in the occasion delete warning', function () {
    $me = User::factory()->create();
    $partner = User::factory()->create(['household_id' => $me->household_id]);
    $occasion = Occasion::factory()->forHousehold($me->household)->christmas(2026)->create();
    $person = Recipient::factory()->forHousehold($me->household)->create();
    Gift::factory()->count(2)->forPersonOn($person, $occasion)->create();
    Gift::factory()->forPersonOn($person, $occasion)->private($me)->create();

    occasionsPage($partner)->assertSeeHtml('Delete Christmas 2026 and its 2 gifts?');
    occasionsPage($me)->assertSeeHtml('Delete Christmas 2026 and its 3 gifts?');
});

it('pre-fills the year after the latest Christmas', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2025)->create();

    occasionsPage($user)->assertSet('year', 2027);
});

it('pre-fills this year when there is no Christmas yet', function () {
    occasionsPage(User::factory()->create())->assertSet('year', now()->year);
});

it('pre-fills this year when the latest Christmas is more than a year ago', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(now()->year - 3)->create();

    occasionsPage($user)->assertSet('year', now()->year);
});

it('says which Christmas the new one copies from', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();

    occasionsPage($user)
        ->assertSeeHtml('data-copies-from="Christmas 2026"')
        ->assertSee('Copies people and budgets from Christmas 2026.');
});

it('updates the copy note when I change the year', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2025)->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();

    occasionsPage($user)
        ->assertSeeHtml('wire:model.live.blur="year"')
        ->set('year', 2026)->assertSeeHtml('data-copies-from="Christmas 2025"')
        ->set('year', 2025)->assertDontSeeHtml('data-copies-from');
});

it('shows no copy note and no error when I clear the year', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();

    occasionsPage($user)->set('year', '')->assertOk()->assertDontSeeHtml('data-copies-from');
});

function occasionsXpath(User $user, string $name): DOMXPath
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.occasionsPage($user)->html());

    return new DOMXPath($dom);
}

it('shows the occasions inside one bordered list', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();

    $xpath = occasionsXpath($user, 'Christmas 2026');

    expect($xpath->query('//section/*[contains(@class,"border") and contains(@class,"rounded-xl") and contains(@class,"divide-y")]/*[@data-occasion="Christmas 2026"]')->length)->toBe(1);
});

it("puts delete inside each occasion's more-actions menu on the right of the row", function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();

    $xpath = occasionsXpath($user, 'Christmas 2026');
    $actions = $xpath->query('//*[@data-occasion="Christmas 2026"]/*[last()][contains(@class,"shrink-0")]');

    expect($actions->length)->toBe(1)
        ->and($xpath->query('.//*[@data-flux-dropdown]//button[@aria-label="More actions"]', $actions->item(0))->length)->toBe(1)
        ->and($xpath->query('.//*[@data-flux-dropdown]//*[@data-test="delete-occasion-button"]', $actions->item(0))->length)->toBe(1);
});

it('keeps the delete confirmation that names the occasion and its gift count', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    Gift::factory()->count(2)->forPersonOn($person, $occasion)->create();

    $xpath = occasionsXpath($user, 'Christmas 2026');
    $item = $xpath->query('//*[@data-occasion="Christmas 2026"]//*[@data-test="delete-occasion-button"]')->item(0);

    expect($item->getAttribute('wire:confirm'))->toBe('Delete Christmas 2026 and its 2 gifts?');
});

it('shows no bordered list when there are no occasions', function () {
    $user = User::factory()->create();

    $xpath = occasionsXpath($user, '');

    expect($xpath->query('//*[contains(@class,"divide-y")]')->length)->toBe(0);
});

it('shows a New birthdays button on the occasions page', function () {
    occasionsPage(User::factory()->create())->assertSeeHtml('data-test="new-birthdays-button"');
});

it('defaults the birthdays year to this year when there are no Birthdays occasions', function () {
    occasionsPage(User::factory()->create())->assertSet('birthdaysYear', now()->year);
});

it('defaults the birthdays year to the year after the latest Birthdays occasion', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->birthdays(now()->year + 2)->create();

    occasionsPage($user)->assertSet('birthdaysYear', now()->year + 3);
});

it('creates the Birthdays occasion and opens its page', function () {
    $user = User::factory()->create();

    $page = occasionsPage($user)->set('birthdaysYear', 2030)->call('createBirthdays');

    $occasion = Occasion::where('household_id', $user->household_id)->firstOrFail();
    expect($occasion->name)->toBe('Birthdays 2030');
    $page->assertRedirect(route('occasions.show', $occasion->id));
});

it('says which Birthdays occasion the budgets are copied from', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->birthdays(2026)->create();

    occasionsPage($user)->set('birthdaysYear', 2027)
        ->assertSeeHtml('data-birthdays-copies-from')->assertSee('Copies budgets from Birthdays 2026.')
        ->assertDontSeeHtml('data-copies-from');
});

it("shows an error when that year's Birthdays already exists", function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->birthdays(2030)->create();

    occasionsPage($user)->set('birthdaysYear', 2030)->call('createBirthdays')
        ->assertHasErrors(['birthdaysYear'])->assertSee('You already have Birthdays 2030.');
});

it('rejects a birthdays year outside 2000 to 2100', function () {
    $user = User::factory()->create();

    occasionsPage($user)->set('birthdaysYear', 1999)->call('createBirthdays')->assertHasErrors(['birthdaysYear'])
        ->set('birthdaysYear', 2101)->call('createBirthdays')->assertHasErrors(['birthdaysYear']);
    expect(Occasion::count())->toBe(0);
});
