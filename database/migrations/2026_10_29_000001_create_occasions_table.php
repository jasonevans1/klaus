<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occasions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name', 100);
            $table->date('date');
            $table->timestamps();

            $table->index(['household_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occasions');
    }
};
