<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_feature_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 64);
            $table->unsignedInteger('unique_reporter_count')->default(0);
            $table->boolean('is_level_two')->default(false);
            $table->timestamp('promoted_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_feature_levels');
    }
};
