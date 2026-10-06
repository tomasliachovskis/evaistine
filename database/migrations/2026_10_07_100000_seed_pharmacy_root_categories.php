<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The 12 root categories of eVaistine.lt (researched on Eurovaistinė,
// Gintarinė, Camelia, Benu, Apotheka and vaistai.lt; see docs/evaistine.md).
// Kept as its own list rather than read from config/categories.php, so the
// migration keeps doing the same thing if the config changes later.
return new class extends Migration
{
    private const ROOTS = [
        'Nereceptiniai vaistai' => 'nereceptiniai-vaistai',
        'Vitaminai ir maisto papildai' => 'vitaminai-ir-maisto-papildai',
        'Veido priežiūra' => 'veido-prieziura',
        'Kūno priežiūra ir apsauga nuo saulės' => 'kuno-prieziura-ir-apsauga-nuo-saules',
        'Plaukų priežiūra' => 'plauku-prieziura',
        'Dekoratyvinė kosmetika ir kvepalai' => 'dekoratyvine-kosmetika-ir-kvepalai',
        'Higiena' => 'higiena',
        'Mamai ir vaikui' => 'mamai-ir-vaikui',
        'Medicinos prekės ir prietaisai' => 'medicinos-prekes-ir-prietaisai',
        'Ortopedija ir kompresinės prekės' => 'ortopedija-ir-kompresines-prekes',
        'Akių priežiūra ir optika' => 'akiu-prieziura-ir-optika',
        'Sportas, svorio kontrolė, arbatos ir spec. maistas' => 'sportas-svorio-kontrole-arbatos-ir-spec-maistas',
    ];

    public function up(): void
    {
        foreach (self::ROOTS as $name => $slug) {
            if (DB::table('categories')->where('slug', $slug)->exists()) {
                continue;
            }

            DB::table('categories')->insert([
                'name' => $name,
                'slug' => $slug,
                'parent_id' => null,
                'hide' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Only drops roots no product points at yet.
        foreach (self::ROOTS as $slug) {
            $id = DB::table('categories')->where('slug', $slug)->value('id');
            if ($id && ! DB::table('products')->where('category_id', $id)->exists()) {
                DB::table('categories')->where('id', $id)->delete();
            }
        }
    }
};
