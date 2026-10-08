<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The two placeholder posts from 2025_11_28_193600_insert_temporary_blog_posts
// were superakcijos' grocery posts (one titled "... - SuperAkcijos.lt").
// Deleted so a fresh production DB doesn't publish them on /naujienos.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('blog_posts')
            ->whereIn('slug', ['geriausios-nuolaidos-si-savait', 'kaip-sutaupyti-pinigu-perkant-maista'])
            ->delete();
    }

    public function down(): void
    {
        (require database_path('migrations/2025_11_28_193600_insert_temporary_blog_posts.php'))->up();
    }
};
