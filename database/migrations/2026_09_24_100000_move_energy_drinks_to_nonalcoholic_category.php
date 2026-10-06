<?php

use Illuminate\Database\Migrations\Migration;

/**
 * superakcijos.lt moved energy drinks between two grocery root categories
 * here, using App\Support\EnergyDrinkCategory. eVaistine.lt has neither the
 * grocery categories nor that class, so this is a no-op kept only so the
 * migration history matches upstream.
 */
return new class extends Migration
{
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};
