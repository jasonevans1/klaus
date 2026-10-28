<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('users')->whereNull('household_id')->get(['id', 'name'])->each(function ($user) use ($now) {
            $householdId = DB::table('households')->insertGetId([
                'name' => "{$user->name}'s household",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('users')->where('id', $user->id)->update(['household_id' => $householdId]);
        });
    }

    public function down(): void
    {
        // Data migration; leave the data.
    }
};
