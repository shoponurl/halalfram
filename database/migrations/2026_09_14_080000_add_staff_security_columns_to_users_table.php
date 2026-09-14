<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Filament authenticator-app 2FA (both encrypted by model casts)
            $table->text('app_authentication_secret')->nullable()->after('password');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
            // Deactivate staff without deleting them (audit history must keep pointing at the user)
            $table->boolean('is_active')->default(true)->after('app_authentication_recovery_codes');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes', 'is_active', 'last_login_at']);
        });
    }
};
