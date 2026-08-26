<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE scraper_runs MODIFY type ENUM('scrape', 'process', 'flyer_scrape', 'hours_scrape')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE scraper_runs MODIFY type ENUM('scrape', 'process', 'flyer_scrape')");
    }
};
