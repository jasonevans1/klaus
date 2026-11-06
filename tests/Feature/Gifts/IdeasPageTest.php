<?php

declare(strict_types=1);

use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function ideasPage(User $user)
{
    return Livewire::actingAs($user)->test('pages::ideas.index');
}

function ideaFor(User $user, Recipient $person, array $attributes = [])
{
    return Gift::factory()->yearRound()->create(array_merge([
        'household_id' => $user->household_id,
        'recipient_id' => $person->id,
        'created_by_user_id' => $user->id,
    ], $attributes));
}

it('shows each active person with their year-round ideas', function () {
    $user = User::factory()->create();
    $pluto = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Pluto']);
    Recipient::factory()->forHousehold($user->household)->create(['name' => 'Mum']);
    ideaFor($user, $pluto, ['title' => 'Dog bed', 'price_cents' => 8000, 'store' => 'Petco']);

    ideasPage($user)
        ->assertSee('Mum')
        ->assertSee('Pluto')
        ->assertSee('Dog bed')
        ->assertSee('$80')
        ->assertSee('Petco');
});

it('leaves out archived people and gifts that are on an occasion', function () {
    $user = User::factory()->create();
    $archived = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Gone Gary', 'archived_at' => now()]);
    $active = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Active Ann']);
    ideaFor($user, $archived, ['title' => 'Archived idea']);
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    Gift::factory()->forPersonOn($active, $occasion)->create(['title' => 'Christmas gift', 'household_id' => $user->household_id]);

    ideasPage($user)
        ->assertSee('Active Ann')
        ->assertDontSee('Gone Gary')
        ->assertDontSee('Archived idea')
        ->assertDontSee('Christmas gift');
});

it('shows a message when the household has no people', function () {
    ideasPage(User::factory()->create())->assertSee('No people yet.');
});

it('adds an idea for a person from the Ideas page', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();

    ideasPage($user)
        ->call('addIdea', $person->id)
        ->set('form.title', 'Kite')
        ->set('form.price', '12.50')
        ->set('form.store', 'Toy shop')
        ->set('form.url', 'https://example.com/kite')
        ->call('saveIdea')
        ->assertHasNoErrors()
        ->assertSee('Kite');

    $idea = Gift::firstWhere('title', 'Kite');
    expect($idea->occasion_id)->toBeNull()
        ->and($idea->recipient_id)->toBe($person->id)
        ->and($idea->price_cents)->toBe(1250)
        ->and($idea->created_by_user_id)->toBe($user->id);
});

it('requires a title for a new idea', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();

    ideasPage($user)->call('addIdea', $person->id)->call('saveIdea')->assertHasErrors('form.title');
});

it('keeps the modal open for the same person on save and add another', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();

    ideasPage($user)
        ->call('addIdea', $person->id)
        ->set('form.title', 'One')
        ->call('saveIdeaAndAddAnother')
        ->set('form.title', 'Two')
        ->call('saveIdea')
        ->assertHasNoErrors();

    expect(Gift::where('recipient_id', $person->id)->pluck('title')->all())->toBe(['One', 'Two']);
});

it('edits and deletes an idea from the Ideas page', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    $idea = ideaFor($user, $person, ['title' => 'Old title']);

    ideasPage($user)
        ->call('editIdea', $idea->id)
        ->assertSet('form.title', 'Old title')
        ->set('form.title', 'New title')
        ->call('saveIdea')
        ->assertHasNoErrors()
        ->assertSee('New title')
        ->call('deleteIdea', $idea->id)
        ->assertDontSee('New title');

    expect(Gift::find($idea->id))->toBeNull();
});

it('badges my private ideas and hides my partner\'s private ideas', function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $person = Recipient::factory()->forHousehold($user->household)->create();
    ideaFor($user, $person, ['title' => 'My secret', 'is_private' => true]);
    ideaFor($partner, $person, ['title' => 'Their secret', 'is_private' => true]);
    ideaFor($partner, $person, ['title' => 'Shared idea']);

    ideasPage($user)
        ->assertSee('My secret')
        ->assertSee('Private')
        ->assertSee('Shared idea')
        ->assertDontSee('Their secret');
});

it('links to the Ideas page from the sidebar', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee(route('ideas.index'), false);
});

it('requires login to see the Ideas page', function () {
    $this->get(route('ideas.index'))->assertRedirect(route('login'));
});

function ideasXpath(Testable $page): DOMXPath
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$page->html());

    return new DOMXPath($dom);
}

function ideaRowPath(string $title, string $inner = ''): string
{
    return '//*[@data-idea="'.$title.'"]'.$inner;
}

it('shows each group of people on the ideas page inside one bordered list', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->create(['name' => 'Abe']);

    $xpath = ideasXpath(ideasPage($user));

    expect($xpath->query('//*[@data-group]/*[contains(@class,"border") and contains(@class,"rounded-xl") and contains(@class,"divide-y")]/*[@*[name()="wire:key"]]/*[@data-person="Abe"]')->length)->toBe(1);
});

it('keeps the add idea button on the right of each person line', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->create(['name' => 'Abe']);

    $xpath = ideasXpath(ideasPage($user));

    expect($xpath->query('//*[@data-person="Abe"]/*[last()][contains(@class,"shrink-0")]//*[@data-test="add-idea-button"]')->length)->toBe(1);
});

it('keeps each idea\'s edit button and more-actions menu together on the right of the idea row', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    ideaFor($user, $person, ['title' => 'Scarf']);

    $xpath = ideasXpath(ideasPage($user));
    $actions = $xpath->query(ideaRowPath('Scarf', '/*[last()][contains(@class,"shrink-0")]'));

    expect($actions->length)->toBe(1)
        ->and($xpath->query('.//*[@data-test="edit-idea-button"]', $actions->item(0))->length)->toBe(1)
        ->and($xpath->query('.//*[@data-flux-dropdown]//button[@aria-label="More actions"]', $actions->item(0))->length)->toBe(1)
        ->and($xpath->query(ideaRowPath('Scarf', '//*[@data-flux-dropdown]//*[@data-test="edit-idea-button"]'))->length)->toBe(0);
});

it('puts delete inside the idea\'s more-actions menu with its confirmation', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    ideaFor($user, $person, ['title' => 'Scarf']);

    $item = ideasXpath(ideasPage($user))->query(ideaRowPath('Scarf', '//*[@data-flux-dropdown]//*[@data-test="delete-idea-button"]'))->item(0);

    expect($item)->not->toBeNull()
        ->and($item->getAttribute('wire:confirm'))->toBe('Delete Scarf?');
});

it('deletes an idea from the more-actions menu', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    $idea = ideaFor($user, $person, ['title' => 'Scarf']);

    $page = ideasPage($user);
    $item = ideasXpath($page)->query(ideaRowPath('Scarf', '//*[@data-flux-dropdown]//*[@data-test="delete-idea-button"]'))->item(0);
    preg_match('/^(\w+)\((.*)\)$/', $item->getAttribute('wire:click'), $call);
    $page->call($call[1], (int) $call[2]);

    expect(Gift::find($idea->id))->toBeNull();
});

it('lists each person\'s ideas only under that person when several people in a group have ideas', function () {
    $user = User::factory()->create();
    $mum = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Mum']);
    $pluto = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Pluto']);
    ideaFor($user, $mum, ['title' => 'Scarf']);
    ideaFor($user, $pluto, ['title' => 'Dog bed']);

    $xpath = ideasXpath(ideasPage($user));

    expect($xpath->query('//*[@data-person="Mum"]/ancestor::*[@*[name()="wire:key"]][1]//*[@data-idea="Scarf"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-person="Mum"]/ancestor::*[@*[name()="wire:key"]][1]//*[@data-idea="Dog bed"]')->length)->toBe(0);
});
