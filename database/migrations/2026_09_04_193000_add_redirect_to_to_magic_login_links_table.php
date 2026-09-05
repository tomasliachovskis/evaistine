<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('magic_login_links', function (Blueprint $table) {
            // Where to send the user after a successful auto-login, instead
            // of the default /favorites (see AuthController::redirectAfterAuth)
            // — used by price-watch emails to land the click straight on the
            // discounted product's page rather than the favorites list.
            $table->string('redirect_to')->nullable()->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('magic_login_links', function (Blueprint $table) {
            $table->dropColumn('redirect_to');
        });
    }
};
