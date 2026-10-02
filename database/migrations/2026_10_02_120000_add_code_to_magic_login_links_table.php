<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magic_login_links', function (Blueprint $table) {
            // 6-digit login code mailed next to the link, typed into the
            // login window itself (AuthController::verifyLoginCode). Stored
            // hashed; null for links that carry no code (price-watch emails).
            $table->string('code_hash')->nullable()->after('token');
            // Wrong guesses; the code stops working at MAX_CODE_ATTEMPTS.
            $table->unsignedTinyInteger('code_attempts')->default(0)->after('code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('magic_login_links', function (Blueprint $table) {
            $table->dropColumn(['code_hash', 'code_attempts']);
        });
    }
};
