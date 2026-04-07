<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->char('reporter_hash', 64);
            $table->string('feature', 64);
            $table->timestamps();

            $table->unique(['client_id', 'reporter_hash', 'feature']);
            $table->index(['client_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
