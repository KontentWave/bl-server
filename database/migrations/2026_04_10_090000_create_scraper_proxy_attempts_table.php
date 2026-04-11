<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scraper_proxy_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 64);
            $table->string('endpoint_host');
            $table->unsignedSmallInteger('endpoint_port')->nullable();
            $table->text('target_url');
            $table->string('target_host');
            $table->string('method', 10);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('outcome', 32);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error_type')->nullable();
            $table->text('error_message')->nullable();
            $table->string('response_preview', 255)->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['target_host', 'attempted_at'], 'scraper_proxy_attempts_target_host_attempted_at_idx');
            $table->index(['outcome', 'attempted_at'], 'scraper_proxy_attempts_outcome_attempted_at_idx');
            $table->index(['http_status', 'attempted_at'], 'scraper_proxy_attempts_http_status_attempted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraper_proxy_attempts');
    }
};
