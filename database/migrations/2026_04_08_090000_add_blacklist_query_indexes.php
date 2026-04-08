<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_feature_levels', function (Blueprint $table) {
            $table->index(['client_id', 'is_level_two'], 'client_feature_levels_client_id_is_level_two_idx');
        });
    }

    public function down(): void
    {
        Schema::table('client_feature_levels', function (Blueprint $table) {
            $table->dropIndex('client_feature_levels_client_id_is_level_two_idx');
        });
    }
};
