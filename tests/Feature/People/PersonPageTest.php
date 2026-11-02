<?php

declare(strict_types=1);

use App\Enums\GiftStatus;
use App\Enums\RecipientGroup;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Livewire\Livewire;

function personPageFor(User $user, Recipient $person)
{
    return Livewire::actingAs($user)->test('pages::people.show', ['person' => $person->id]);
}

it("shows a person's name, group, birthday, age and interests", function () {
    $this->travelTo(Carbon\Carbon::parse('2026-06-01'));
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create([
        'name' => 'Pluto', 'group' => RecipientGroup::Family,
        'birthday_month' => 5, 'birthday_day' => 14, 'birthday_year' => 2019,
        'interests' => "Squeaky toys\nLong walks",
    ]);

    personPageFor($user, $person)
        ->assertSee('Pluto')
        ->assertSee(RecipientGroup::Family->label())
        ->assertSee('May 14, 2019')
        ->assertSee('data-profile-age="7"', false)
        ->assertSee("Squeaky toys\nLong walks");
});

it('shows an age of 0 for a baby', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-06-01'));
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create([
        'birthday_month' => 1, 'birthday_day' => 10, 'birthday_year' => 2026,
    ]);

    personPageFor($user, $person)->assertSee('data-profile-age="0"', false);
});

it("lists the person's year-round ideas and badges my private ones", function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    Gift::factory()->yearRound()->create(['household_id' => $user->household_id, 'recipient_id' => $person->id, 'title' => 'Kettle', 'price_cents' => 6000, 'store' => 'Chewy']);
    Gift::factory()->yearRound()->private($user)->create(['household_id' => $user->household_id, 'recipient_id' => $person->id, 'title' => 'Perfume']);

    personPageFor($user, $person)
        ->assertSeeHtml('data-idea="Kettle" data-price="$60" data-store="Chewy" data-private="no"')
        ->assertSeeHtml('data-idea="Perfume"')
        ->assertSeeHtml('data-private="yes"')
        ->assertSee('Private');
});

it("hides my partner's private ideas on the person page", function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $person = Recipient::factory()->forHousehold($user->household)->create();
    Gift::factory()->yearRound()->private($partner)->create(['household_id' => $user->household_id, 'recipient_id' => $person->id, 'title' => 'Secret thing']);

    personPageFor($user, $person)->assertDontSee('Secret thing')->assertSee('No ideas yet.');
});

it("opens an archived person's page", function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Old Friend', 'archived_at' => now()]);

    personPageFor($user, $person)->assertSee('Old Friend')->assertSee('Archived');
});

it("returns 404 for another household's person", function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->create();

    $this->actingAs($user)->get(route('people.show', $person))->assertNotFound();
});

it('links each name on the People page to their page', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Linky']);

    Livewire::actingAs($user)->test('pages::people.index')->assertSeeHtml('href="'.route('people.show', $person).'"');
});

it('shows what the person got last Christmas under that year', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    $year = now()->year - 1;
    $occasion = Occasion::factory()->forHousehold($user->household)->christmas($year)->create();
    Gift::factory()->given($user)->create(['household_id' => $user->household_id, 'recipient_id' => $person->id, 'occasion_id' => $occasion->id, 'title' => 'Dog bed']);

    personPageFor($user, $person)
        ->assertSee('History')
        ->assertSeeHtml('data-year="'.$year.'"')
        ->assertSeeHtml('data-history-occasion="'.$occasion->name.'"')
        ->assertSeeHtml('data-history-gift="Dog bed"')
        ->assertSeeHtml('href="'.route('occasions.show', $occasion).'"');
});

it('groups history by year, newest first, then by occasion', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    $gift = fn (Occasion $occasion, string $title) => Gift::factory()->given($user)->create(['household_id' => $user->household_id, 'recipient_id' => $person->id, 'occasion_id' => $occasion->id, 'title' => $title]);
    $old = Occasion::factory()->forHousehold($user->household)->christmas(2023)->create();
    $birthday = Occasion::factory()->forHousehold($user->household)->create(['name' => 'Pluto birthday', 'date' => '2024-05-14']);
    $recent = Occasion::factory()->forHousehold($user->household)->christmas(2024)->create();
    $gift($old, 'Old ball');
    $gift($birthday, 'Bone');
    $gift($recent, 'Sweater');

    personPageFor($user, $person)
        ->assertSeeInOrder(['data-year="2024"', 'Christmas 2024', 'Sweater', 'Pluto birthday', 'Bone', 'data-year="2023"', 'Christmas 2023', 'Old ball'], false)
        ->assertSee('May 14, 2024');
});

it("shows each gift's price, status and who bought it", function () {
    $user = User::factory()->create(['name' => 'Jason']);
    $person = Recipient::factory()->forHousehold($user->household)->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->christmas(now()->year - 1)->create();
    Gift::factory()->given($user)->create(['household_id' => $user->household_id, 'recipient_id' => $person->id, 'occasion_id' => $occasion->id, 'title' => 'Dog bed', 'price_cents' => 8000]);

    personPageFor($user, $person)
        ->assertSeeHtml('data-history-gift="Dog bed" data-price="$80" data-status="'.GiftStatus::Given->label().'" data-buyer="Jason" data-private="no"');
});

it("hides my partner's private gifts from the history", function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $person = Recipient::factory()->forHousehold($user->household)->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->christmas(now()->year - 1)->create();
    $row = ['household_id' => $user->household_id, 'recipient_id' => $person->id, 'occasion_id' => $occasion->id];
    Gift::factory()->given($partner)->private($partner)->create($row + ['title' => 'Secret watch']);
    Gift::factory()->given($user)->private($user)->create($row + ['title' => 'My surprise']);

    personPageFor($user, $person)
        ->assertDontSee('Secret watch')
        ->assertSeeHtml('data-history-gift="My surprise"')
        ->assertSee('Private');
});

it('says Nothing yet when the person has no history', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();

    personPageFor($user, $person)->assertSee('Nothing yet.');
});
