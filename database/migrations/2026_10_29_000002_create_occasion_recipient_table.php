<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occasion_recipient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occasion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('budget_cents')->nullable();
            $table->timestamps();

            $table->unique(['occasion_id', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occasion_recipient');
    }
};
