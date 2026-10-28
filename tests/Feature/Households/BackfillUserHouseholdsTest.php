<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

function backfillHouseholds(): void
{
    (require database_path('migrations/2026_10_27_000003_backfill_user_households.php'))->up();
}

function orphanUser(string $name): User
{
    $user = User::factory()->create(['name' => $name]);
    DB::table('users')->where('id', $user->id)->update(['household_id' => null]);

    return $user;
}

it('gives every user without a household their own household', function () {
    $users = [orphanUser('Ann'), orphanUser('Bob')];

    backfillHouseholds();

    $ids = DB::table('users')->whereIn('id', array_map(fn ($u) => $u->id, $users))->pluck('household_id');
    expect($ids->filter()->unique())->toHaveCount(2);
});

it('names the backfilled household after the user', function () {
    $user = orphanUser('Ann');

    backfillHouseholds();

    $householdId = DB::table('users')->where('id', $user->id)->value('household_id');
    expect(DB::table('households')->where('id', $householdId)->first())
        ->name->toBe("Ann's household")
        ->created_at->not->toBeNull()
        ->updated_at->not->toBeNull();
});

it('leaves users who already have a household unchanged', function () {
    $user = User::factory()->create();
    $before = $user->household_id;

    backfillHouseholds();

    expect(DB::table('users')->where('id', $user->id)->value('household_id'))->toBe($before);
});

it('creates no households when every user already has one', function () {
    User::factory()->count(2)->create();
    $count = DB::table('households')->count();

    backfillHouseholds();

    expect(DB::table('households')->count())->toBe($count);
});
