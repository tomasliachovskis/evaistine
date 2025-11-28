<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();
        $yesterday = Carbon::now()->subDay();

        DB::table('blog_posts')->insert([
            [
                'title' => 'Geriausios nuolaidos šią savaitę',
                'slug' => 'geriausios-nuolaidos-si-savait',
                'content' => 'Atraskite geriausias nuolaidas šią savaitę! Peržiūrėkite naujausius pasiūlymus iš populiariausių parduotuvių. Sutaupykite pinigų perkant kasdienes prekes ir daugiau.',
                'published_at' => $now,
                'meta_title' => 'Geriausios nuolaidos šią savaitę - SuperAkcijos.lt',
                'meta_description' => 'Peržiūrėkite geriausias nuolaidas šią savaitę. Sutaupykite pinigų perkant kasdienes prekes iš populiariausių parduotuvių.',
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Kaip sutaupyti pinigų perkant maistą',
                'slug' => 'kaip-sutaupyti-pinigu-perkant-maista',
                'content' => 'Sužinokite, kaip sutaupyti pinigų perkant maistą. Palyginkite kainas tarp skirtingų parduotuvių, naudokite nuolaidų korteles ir sekite akcijas. Su tinkamais sprendimais galite sutaupyti iki 30% savo maisto biudžeto.',
                'published_at' => $yesterday,
                'meta_title' => 'Kaip sutaupyti pinigų perkant maistą - Patarimai',
                'meta_description' => 'Praktiniai patarimai, kaip sutaupyti pinigų perkant maistą. Palyginkite kainas ir naudokite nuolaidas.',
                'status' => 'published',
                'created_at' => $yesterday,
                'updated_at' => $yesterday,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('blog_posts')
            ->whereIn('slug', [
                'geriausios-nuolaidos-si-savait',
                'kaip-sutaupyti-pinigu-perkant-maista'
            ])
            ->delete();
    }
};

