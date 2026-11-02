<?php

use App\Models\Gift;
use App\Models\Household;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;

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
