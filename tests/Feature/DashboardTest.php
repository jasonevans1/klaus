<?php

use App\Models\Gift;
use App\Models\Household;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

it('shows my household name on the dashboard', function () {
    $household = Household::factory()->create(['name' => 'Evans household']);
    $user = User::factory()->forHousehold($household)->create();

    $this->actingAs($user)->get(route('dashboard'))->assertSee('Evans household');
});

it('lists my household\'s members on the dashboard', function () {
    $household = Household::factory()->create();
    User::factory()->forHousehold($household)->create(['name' => 'Jason Evans']);
    $partner = User::factory()->forHousehold($household)->create(['name' => 'Sam Partner']);

    $this->actingAs($partner)->get(route('dashboard'))
        ->assertSee('Jason Evans')
        ->assertSee('Sam Partner');
});

it('does not show another household\'s name on the dashboard', function () {
    $other = Household::factory()->create(['name' => 'Stranger household']);
    User::factory()->forHousehold($other)->create(['name' => 'Stranger Danger']);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertDontSee('Stranger household')
        ->assertDontSee('Stranger Danger');
});

it('shows upcoming birthdays on the dashboard', function () {
    $this->travelTo('2026-11-01');
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->withBirthday(11, 1)->create(['name' => 'Anna']);
    Recipient::factory()->forHousehold($user->household)->withBirthday(11, 2)->create(['name' => 'Ben']);
    Recipient::factory()->forHousehold($user->household)->withBirthday(11, 10)->create(['name' => 'Cleo']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee("Anna's birthday is today")
        ->assertSee("Ben's birthday is tomorrow")
        ->assertSee("Cleo's birthday is in 9 days (Nov 10)")
        ->assertDontSee('no gift yet');
});

it('shows occasions with people who have no gift yet on the dashboard', function () {
    $this->travelTo('2026-11-01');
    $user = User::factory()->create();
    $house = $user->household;
    $mum = Recipient::factory()->forHousehold($house)->create(['name' => 'Mum']);
    $dad = Recipient::factory()->forHousehold($house)->create(['name' => 'Dad']);
    Occasion::factory()->forHousehold($house)->create(['name' => 'Christmas 2026', 'date' => '2026-11-11'])->recipients()->attach([$mum->id, $dad->id]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('Christmas 2026')
        ->assertSee('(in 10 days (Nov 11))', false)
        ->assertSee('2 people with no gift yet')
        ->assertSee('Dad')
        ->assertSee('Mum');
});

it('links reminders to their occasion', function () {
    $this->travelTo('2026-11-01');
    $user = User::factory()->create();
    $house = $user->household;
    $mum = Recipient::factory()->forHousehold($house)->withBirthday(11, 5)->create(['name' => 'Mum']);
    $birthday = Occasion::factory()->birthday($mum, '2026-11-05')->create();
    $birthday->recipients()->attach($mum);
    $christmas = Occasion::factory()->forHousehold($house)->create(['date' => '2026-11-20']);
    $christmas->recipients()->attach($mum);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee(route('occasions.show', $birthday), false)
        ->assertSee(route('occasions.show', $christmas), false);
});

it('says all caught up when nothing needs attention', function () {
    $this->travelTo('2026-11-01');
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertSee('All caught up.');
});

it('never mentions my partner\'s private gifts on the dashboard', function () {
    $this->travelTo('2026-11-01');
    $user = User::factory()->create();
    $partner = User::factory()->forHousehold($user->household)->create();
    $anna = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Anna']);
    $occasion = Occasion::factory()->forHousehold($user->household)->create(['date' => '2026-11-20']);
    $occasion->recipients()->attach($anna);
    Gift::factory()->forPersonOn($anna, $occasion)->private($partner)->create(['title' => 'Secret diamond']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertDontSee('Secret diamond')
        ->assertSee('1 person with no gift yet')
        ->assertSee('data-people="Anna"', false);
});

it('shows a date in the past as the full date', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test('pages::dashboard')->instance();

    expect($component->when(-3, CarbonImmutable::parse('2026-12-25')))->toBe('Dec 25, 2026');
});

it('sends an unverified member to verify their email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

function cardFor(User $user, string $today = '2026-11-04'): TestResponse
{
    test()->travelTo($today);

    return test()->actingAs($user)->get(route('dashboard'));
}

function cardPerson(Occasion $occasion, ?int $budgetCents): Recipient
{
    $person = Recipient::factory()->forHousehold($occasion->household)->create();
    $occasion->recipients()->attach($person->id, ['budget_cents' => $budgetCents]);

    return $person;
}

it('shows the current Christmas with what has been spent of its budget', function () {
    $user = User::factory()->create();
    $christmas = Occasion::factory()->forHousehold($user->household)->create();
    $person = cardPerson($christmas, 90000);
    Gift::factory()->forPersonOn($person, $christmas)->bought($user)->create(['price_cents' => 64000]);

    cardFor($user)
        ->assertSee('data-christmas="Christmas 2026"', false)
        ->assertSeeText('Christmas 2026')
        ->assertSeeText('in 51 days (Dec 25)')
        ->assertSeeText('$640 of $900 spent')
        ->assertDontSee('data-over-budget', false);
});

it('shows how far over budget Christmas is', function () {
    $user = User::factory()->create();
    $christmas = Occasion::factory()->forHousehold($user->household)->create();
    $person = cardPerson($christmas, 10000);
    Gift::factory()->forPersonOn($person, $christmas)->bought($user)->create(['price_cents' => 11500]);

    cardFor($user)->assertSeeText('$115 of $100 spent')->assertSeeText('$15 over');
});

it('shows no over badge when Christmas has no budget', function () {
    $user = User::factory()->create();
    $christmas = Occasion::factory()->forHousehold($user->household)->create();
    $person = cardPerson($christmas, null);
    Gift::factory()->forPersonOn($person, $christmas)->bought($user)->create(['price_cents' => 5000]);

    cardFor($user)->assertSeeText('$50 spent · No budget')->assertDontSeeText('over');
});

it('shows the date of a Christmas that has already passed', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2025)->create();

    cardFor($user)->assertSeeText('Christmas 2025')->assertSeeText('Dec 25, 2025');
});

it('shows gift counts for each status', function () {
    $user = User::factory()->create();
    $christmas = Occasion::factory()->forHousehold($user->household)->create();
    $person = cardPerson($christmas, null);
    Gift::factory()->count(2)->forPersonOn($person, $christmas)->create();
    Gift::factory()->forPersonOn($person, $christmas)->wrapped($user)->create();

    cardFor($user)
        ->assertSeeText('Ideas 2')
        ->assertSeeText('Bought 0')
        ->assertSeeText('Wrapped 1')
        ->assertSeeText('Given 0');
});

it('links Christmas to its shopping list and wrapping checklist', function () {
    $user = User::factory()->create();
    $christmas = Occasion::factory()->forHousehold($user->household)->create();

    cardFor($user)
        ->assertSee(route('occasions.show', $christmas), false)
        ->assertSee(route('occasions.shopping', $christmas), false)
        ->assertSee(route('occasions.wrapping', $christmas), false);
});

it('shows my private gifts line only to me', function () {
    $user = User::factory()->create();
    $partner = User::factory()->forHousehold($user->household)->create();
    $christmas = Occasion::factory()->forHousehold($user->household)->create();
    $person = cardPerson($christmas, null);
    Gift::factory()->forPersonOn($person, $christmas)->private($partner)->bought($partner)->create(['price_cents' => 8500]);

    cardFor($partner)->assertSeeText('My private gifts: $85 spent');
    cardFor($user)->assertDontSee('data-my-private-spent', false);
});

it('invites me to start Christmas when there is none', function () {
    $user = User::factory()->create();

    cardFor($user)
        ->assertSeeText('No Christmas yet.')
        ->assertSee('Start Christmas')
        ->assertSee(route('occasions.index'), false);
});

it('lists other occasions coming up with their spending', function () {
    $user = User::factory()->create();
    $pluto = Recipient::factory()->forHousehold($user->household)->withBirthday(11, 20)->create(['name' => 'Pluto']);
    $birthday = Occasion::factory()->birthday($pluto, '2026-11-20')->create();
    $birthday->recipients()->attach($pluto->id, ['budget_cents' => 5000]);
    Gift::factory()->forPersonOn($pluto, $birthday)->bought($user)->create(['price_cents' => 2500]);

    cardFor($user)
        ->assertSeeText('Coming up')
        ->assertSee('data-upcoming="Pluto&#039;s birthday 2026"', false)
        ->assertSeeText('in 16 days (Nov 20)')
        ->assertSeeText('$25 of $50 spent');
});

it('says nothing else is coming up when the list is empty', function () {
    $user = User::factory()->create();

    cardFor($user)->assertSeeText('Nothing else coming up in the next 60 days.');
});
