<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = $this->challengeTableName();

        if ($tableName === null) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'challenge_id')) {
                return;
            }

            $table->uuid('challenge_id')->nullable()->unique()->after('id');
            $table->text('ad_url')->nullable()->after('challenge_id');
        });
    }

    public function down(): void
    {
        $tableName = $this->challengeTableName();

        if ($tableName === null || ! Schema::hasColumn($tableName, 'challenge_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique(['challenge_id']);
            $table->dropColumn(['challenge_id', 'ad_url']);
        });
    }

    private function challengeTableName(): ?string
    {
        return match (true) {
            Schema::hasTable('otp_challenges') => 'otp_challenges',
            Schema::hasTable('auth_challenges') => 'auth_challenges',
            default => null,
        };
    }
};
