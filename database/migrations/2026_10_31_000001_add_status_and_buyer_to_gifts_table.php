<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->string('status', 20)->default('idea')->after('title');
            $table->foreignId('purchased_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchased_by_user_id');
            $table->dropColumn('status');
        });
    }
};
