<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auth_challenges') && ! Schema::hasTable('otp_challenges')) {
            Schema::rename('auth_challenges', 'otp_challenges');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('otp_challenges') && ! Schema::hasTable('auth_challenges')) {
            Schema::rename('otp_challenges', 'auth_challenges');
        }
    }
};
