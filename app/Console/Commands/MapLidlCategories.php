<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\CategoryMapper;
use App\Models\Store;
use Illuminate\Console\Command;

class MapLidlCategories extends Command
{
    protected $signature = 'lidl:map-categories';
    protected $description = 'Map Lidl categories to internal categories';

    public function handle()
    {
        $store = Store::where('name', 'Lidl')->first();
        
        if (!$store) {
            $this->error('Lidl store not found');
            return;
        }

        $mappings = [
            'Poreikių pasauliai/"pasidaryk pats" parduotuvė ir sodas/Akumuliatoriniai įrankiai' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/"pasidaryk pats" parduotuvė ir sodas/Automobiliai ir motociklai' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/"pasidaryk pats" parduotuvė ir sodas/Darbo drabužiai' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/"pasidaryk pats" parduotuvė ir sodas/Dirbtuvės ir aparatūra' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/"pasidaryk pats" parduotuvė ir sodas/Elektriniai įrankiai' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/"pasidaryk pats" parduotuvė ir sodas/Rankiniai įrankiai' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/"pasidaryk pats" parduotuvė ir sodas/Sodo įranga ir sodo įrankiai' => 'Augalai, gėlės',
            'Poreikių pasauliai/"pasidaryk pats" parduotuvė ir sodas/Statyba ir renovacija' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Gyvenimas ir apstatymas/Apšvietimas' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Gyvenimas ir apstatymas/Biuras' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Gyvenimas ir apstatymas/Daugialypė terpė ir technologijos' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Gyvenimas ir apstatymas/Dekoravimas' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Gyvenimas ir apstatymas/Miegamasis' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Gyvenimas ir apstatymas/Namų tekstilė' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Gyvenimas ir apstatymas/Prieškambaris ir sandėliukas' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Gyvenimas ir apstatymas/Virtuvė ir valgomasis' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Kūdikiai, vaikai ir žaislai/2-8 metų vaikų drabužiai' => 'Vaikų ir kūdikių prekės',
            'Poreikių pasauliai/Kūdikiai, vaikai ir žaislai/Kūdikių drabužiai' => 'Vaikų ir kūdikių prekės',
            'Poreikių pasauliai/Kūdikiai, vaikai ir žaislai/Kūdikių ir vaikų įranga' => 'Vaikų ir kūdikių prekės',
            'Poreikių pasauliai/Kūdikiai, vaikai ir žaislai/Mokykla ir kūryba' => 'Vaikų ir kūdikių prekės',
            'Poreikių pasauliai/Kūdikiai, vaikai ir žaislai/Žaislai' => 'Vaikų ir kūdikių prekės',
            'Poreikių pasauliai/Mada ir aksesuarai/Moterų mada' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Mada ir aksesuarai/Vyriška apranga' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Maistas ir šalia maisto/Delikatesai' => 'Bakalėja',
            'Poreikių pasauliai/Maistas ir šalia maisto/Dešra ir mėsa' => 'Mėsa ir žuvis',
            'Poreikių pasauliai/Maistas ir šalia maisto/Duona ir kepiniai' => 'Duonos gaminiai',
            'Poreikių pasauliai/Maistas ir šalia maisto/Džemas, marmeladas ir medus' => 'Bakalėja',
            'Poreikių pasauliai/Maistas ir šalia maisto/Gėlės ir augalai (Gyvi augalai ir puokštės)' => 'Augalai, gėlės',
            'Poreikių pasauliai/Maistas ir šalia maisto/Gėrimai' => 'Gėrimai, kava, arbata',
            'Poreikių pasauliai/Maistas ir šalia maisto/Gyvūnų ėdalas' => 'Gyvūnų prekės',
            'Poreikių pasauliai/Maistas ir šalia maisto/Kava, arbata ir kakava' => 'Gėrimai, kava, arbata',
            'Poreikių pasauliai/Maistas ir šalia maisto/Kiaušiniai ir pagrindiniai maisto produktai (makaronai, miltai, ankštinės daržovės ir kt.)' => 'Bakalėja',
            'Poreikių pasauliai/Maistas ir šalia maisto/Paruošti patiekalai (išskyrus šaldytas picas ir kt. bei kepinius)' => 'Bakalėja',
            'Poreikių pasauliai/Maistas ir šalia maisto/Prieskoniai, garstyčios ir padažai' => 'Bakalėja',
            'Poreikių pasauliai/Maistas ir šalia maisto/Riebalai, aliejus, actas ir konservai' => 'Bakalėja',
            'Poreikių pasauliai/Maistas ir šalia maisto/Šaldytas maistas (Šaldytas maistas)' => 'Šaldytas maistas ir ledai',
            'Poreikių pasauliai/Maistas ir šalia maisto/Sūris ir pieno produktai (įskaitant sviestą ir sūrį)' => 'Pieno produktai ir kiaušiniai',
            'Poreikių pasauliai/Maistas ir šalia maisto/Užkandžiai ir konditerijos gaminiai' => 'Saldumynai ir užkandžiai',
            'Poreikių pasauliai/Maistas ir šalia maisto/Vaisiai ir daržovės' => 'Vaisiai ir daržovės',
            'Poreikių pasauliai/Maistas ir šalia maisto/Vaistinė - higienos reikmenys, kūdikių maistas, kosmetika, valymo priemonės' => 'Kosmetika ir higiena',
            'Poreikių pasauliai/Maistas ir šalia maisto/Žuvis ir jūros gėrybės' => 'Mėsa ir žuvis',
            'Poreikių pasauliai/Sportas ir laisvalaikis/Fitnesas' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Sportas ir laisvalaikis/Gyvūnų augintinių reikmenys' => 'Gyvūnų prekės',
            'Poreikių pasauliai/Sportas ir laisvalaikis/Sportinė apranga' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Virtuvė ir namų ūkis/Grilis ir priedai' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Virtuvė ir namų ūkis/Maisto gaminimas ir kepimas' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Virtuvė ir namų ūkis/Namų ūkio valymas' => 'Buitinė chemija, valymo priemonės',
            'Poreikių pasauliai/Virtuvė ir namų ūkis/Saugojimas ir organizavimas' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Virtuvė ir namų ūkis/Stalo serviravimas ir indai' => 'Namų ūkio ir laisvalaikio prekės',
            'Poreikių pasauliai/Virtuvė ir namų ūkis/Virtuvės prietaisai' => 'Namų ūkio ir laisvalaikio prekės',
        ];

        $created = 0;
        $updated = 0;

        foreach ($mappings as $lidlCategory => $internalCategory) {
            $category = Category::where('name', $internalCategory)->first();
            
            if (!$category) {
                $this->warn("Internal category '{$internalCategory}' not found, skipping mapping for '{$lidlCategory}'");
                continue;
            }

            $existingMapper = CategoryMapper::where('store', $store->id)
                ->where('store_category', $lidlCategory)
                ->first();

            if ($existingMapper) {
                $existingMapper->update(['category_id' => $category->id]);
                $updated++;
                $this->info("Updated mapping: {$lidlCategory} -> {$internalCategory}");
            } else {
                CategoryMapper::create([
                    'store' => $store->id,
                    'store_category' => $lidlCategory,
                    'category_id' => $category->id,
                ]);
                $created++;
                $this->info("Created mapping: {$lidlCategory} -> {$internalCategory}");
            }
        }

        $this->info("Mapping completed: {$created} created, {$updated} updated");
    }
}
