<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Email notifications without an account (owner, 2026-10-02): an
        // email, the stores to cover and which emails to get. The token is
        // the key of the settings page (/pranesimai/{token}) linked from
        // every email. Nothing is sent until confirmed_at is set (double
        // opt-in, so nobody can sign up someone else's address).
        Schema::create('email_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Store slugs; null or empty means the main chains.
            $table->json('store_slugs')->nullable();
            // "Savaitės santrauka" (Thursday) and "Naujas leidinys".
            $table->boolean('wants_weekly')->default(true);
            $table->boolean('wants_new_leaflets')->default(true);
            $table->string('token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('weekly_sent_at')->nullable();
            $table->timestamps();
        });

        // Which leaflets a subscriber was already told about, so a leaflet
        // is announced once (same anti-join idea as price_watch_notifications).
        Schema::create('email_subscriber_flyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_subscriber_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_flyer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->unique(['email_subscriber_id', 'store_flyer_id'], 'email_subscriber_flyer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_subscriber_flyers');
        Schema::dropIfExists('email_subscribers');
    }
};
