<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_bindings', function (Blueprint $table): void {
            $table->uuid('enrollment_id')->nullable()->unique();
            $table->string('enrollment_kind', 16)->nullable();
            $table->text('ad_url')->nullable();
            $table->string('lifecycle_state', 16)->nullable();
            $table->unsignedInteger('lifecycle_version')->nullable();
            $table->dateTime('last_renewed_at')->nullable();
            $table->dateTime('next_renewal_at')->nullable();
            $table->dateTime('offboarded_at')->nullable();
            $table->string('offboarding_reason', 32)->nullable();
            $table->dateTime('fetch_window_at')->nullable();
            $table->unsignedTinyInteger('fetch_count')->default(0);
            $table->unsignedTinyInteger('renewal_failures')->default(0);
            $table->dateTime('retry_at')->nullable();
        });
        Schema::create('device_key_selections', function (Blueprint $table): void {
            $table->string('key_hash', 64)->primary();
            $table->unsignedBigInteger('binding_id')->nullable();
        });
        Schema::create('renewal_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('enrollment_id');
            $table->uuid('request_id');
            $table->string('digest', 64);
            $table->string('outcome', 48);
            $table->dateTime('retry_at')->nullable();
            $table->dateTime('created_at')->index();
            $table->unique(['enrollment_id', 'request_id']);
        });
        Schema::create('device_enrollment_generations', function (Blueprint $table): void {
            $table->uuid('enrollment_id')->primary();
            $table->string('key_hash', 64)->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_enrollment_generations');
        Schema::dropIfExists('renewal_receipts');
        Schema::dropIfExists('device_key_selections');
        Schema::table('device_bindings', function (Blueprint $table): void {
            $table->dropUnique(['enrollment_id']);
            $table->dropColumn(['enrollment_id', 'enrollment_kind', 'ad_url',
                'lifecycle_state', 'lifecycle_version', 'last_renewed_at',
                'next_renewal_at', 'offboarded_at', 'offboarding_reason',
                'fetch_window_at', 'fetch_count', 'renewal_failures', 'retry_at']);
        });
    }
};
