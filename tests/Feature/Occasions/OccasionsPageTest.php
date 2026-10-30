<?php

declare(strict_types=1);

use App\Models\Occasion;
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
