<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Existing rows (created by the old NextAuth JWT bridge, matched by email
    // only) get these as null — Socialite's findOrCreate below still falls
    // back to matching by email first, so those accounts keep working.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('oauth_provider')->nullable()->after('password');
            $table->string('oauth_provider_id')->nullable()->after('oauth_provider');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['oauth_provider', 'oauth_provider_id']);
        });
    }
};
