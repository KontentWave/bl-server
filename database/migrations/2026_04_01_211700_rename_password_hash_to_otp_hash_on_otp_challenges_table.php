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
            if (Schema::hasColumn($tableName, 'password_hash') && ! Schema::hasColumn($tableName, 'otp_hash')) {
                $table->renameColumn('password_hash', 'otp_hash');
            }
        });
    }

    public function down(): void
    {
        $tableName = $this->challengeTableName();

        if ($tableName === null) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'otp_hash') && ! Schema::hasColumn($tableName, 'password_hash')) {
                $table->renameColumn('otp_hash', 'password_hash');
            }
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
