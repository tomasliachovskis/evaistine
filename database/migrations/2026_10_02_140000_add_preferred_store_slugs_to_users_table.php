<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // "Mano parduotuvės": store slugs the user shops at. Offer
            // listings show only these stores (applied in the browser, see
            // components/my-stores-sheet.blade.php). Guests keep the same
            // list in localStorage; this copy follows a signed-in user to
            // other devices.
            $table->json('preferred_store_slugs')->nullable()->after('price_watch_unsubscribed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferred_store_slugs');
        });
    }
};
