<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('occasions', function (Blueprint $table) {
            $table->foreignId('birthday_recipient_id')->nullable()->after('date')
                ->constrained('recipients')->nullOnDelete();
            $table->unique(['birthday_recipient_id', 'date']);
        });

        Schema::table('recipients', function (Blueprint $table) {
            $table->unsignedSmallInteger('last_birthday_year')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('occasions', function (Blueprint $table) {
            $table->dropForeign(['birthday_recipient_id']);
            $table->dropUnique(['birthday_recipient_id', 'date']);
            $table->dropColumn('birthday_recipient_id');
        });

        Schema::table('recipients', function (Blueprint $table) {
            $table->dropColumn('last_birthday_year');
        });
    }
};
