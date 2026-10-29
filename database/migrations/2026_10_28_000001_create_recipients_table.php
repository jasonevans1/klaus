<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('group');
            $table->string('name', 100);
            $table->boolean('is_shared')->default(false);
            $table->unsignedTinyInteger('birthday_month')->nullable();
            $table->unsignedTinyInteger('birthday_day')->nullable();
            $table->unsignedSmallInteger('birthday_year')->nullable();
            $table->text('interests')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipients');
    }
};
