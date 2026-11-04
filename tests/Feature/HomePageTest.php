<?php

declare(strict_types=1);

use App\Models\User;

it('shows a guest the Klaus headline and the three feature lines', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeText('Christmas and birthday gifts, planned together.')
        ->assertSeeText('Klaus keeps one list for your household, so you never buy the same gift twice.')
        ->assertSeeText('One shared budget')
        ->assertSeeText('Private gifts')
        ->assertSeeText('Birthday reminders');
});

it('links a guest to log in', function () {
    $this->get(route('home'))->assertSee('href="'.route('login').'"', false);
});

it('links a guest to create a household', function () {
    $this->get(route('home'))
        ->assertSee('href="'.route('register').'"', false)
        ->assertSeeText('Get started');
});

it('sends a signed-in visitor to the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});

it('no longer shows the Laravel starter page', function () {
    $this->get(route('home'))
        ->assertDontSee('Laravel')
        ->assertDontSee('laravel.com')
        ->assertDontSee("Let's get started");
});

it('shows the Klaus bow mark', function () {
    $this->get(route('home'))->assertSee('M12 11C9 5 3 5 3.5 9.5S9 12 12 11z', false);
});

it('titles the browser tab Klaus', function () {
    expect($this->get(route('home'))->getContent())->toMatch('#<title>\s*Klaus\s*</title>#');
});
