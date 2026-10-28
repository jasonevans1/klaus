<?php

use App\Models\Household;
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

    $this->actingAs($user)->get(route('dashboard'))
        ->assertDontSee('Stranger household')
        ->assertDontSee('Stranger Danger');
});
