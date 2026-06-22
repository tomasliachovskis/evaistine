<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_flyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('catalog_name')->nullable();
            $table->string('issue_number')->nullable();
            $table->string('title')->nullable();
            $table->string('image_url')->nullable();
            $table->string('pdf_url')->nullable();
            $table->string('view_url')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('source')->default('manual');
            $table->timestamps();

            $table->index(['store_id', 'is_active', 'sort_order']);
        });

        if (!Schema::hasColumn('stores', 'flyer_image_url')) {
            return;
        }

        $stores = DB::table('stores')->get();

        foreach ($stores as $store) {
            $catalogs = null;

            if (Schema::hasColumn('stores', 'flyer_catalogs') && $store->flyer_catalogs) {
                $catalogs = json_decode($store->flyer_catalogs, true);
            }

            if (is_array($catalogs) && count($catalogs) > 0) {
                foreach ($catalogs as $index => $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $imageUrl = $item['image_url'] ?? null;
                    $pdfUrl = $item['pdf_url'] ?? null;

                    if (!$imageUrl && (!$pdfUrl || $pdfUrl === '#')) {
                        continue;
                    }

                    DB::table('store_flyers')->insert([
                        'store_id' => $store->id,
                        'title' => $item['title'] ?? null,
                        'image_url' => $imageUrl,
                        'pdf_url' => $pdfUrl && $pdfUrl !== '#' ? $pdfUrl : null,
                        'view_url' => $item['view_url'] ?? "/akcijos/{$store->slug}",
                        'valid_from' => $item['valid_from'] ?? $store->flyer_valid_from,
                        'valid_to' => $item['valid_to'] ?? $store->flyer_valid_to,
                        'sort_order' => $index,
                        'is_active' => true,
                        'source' => 'manual',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                continue;
            }

            $imageUrl = $store->flyer_image_url ?? null;
            $pdfUrl = $store->flyer_pdf_url ?? null;

            if (!$imageUrl && (!$pdfUrl || $pdfUrl === '#')) {
                continue;
            }

            DB::table('store_flyers')->insert([
                'store_id' => $store->id,
                'image_url' => $imageUrl,
                'pdf_url' => $pdfUrl && $pdfUrl !== '#' ? $pdfUrl : null,
                'view_url' => "/akcijos/{$store->slug}",
                'valid_from' => $store->flyer_valid_from,
                'valid_to' => $store->flyer_valid_to,
                'sort_order' => 0,
                'is_active' => true,
                'source' => 'pdf_import',
                'created_at' => $store->flyer_updated_at ?? now(),
                'updated_at' => $store->flyer_updated_at ?? now(),
            ]);
        }

        Schema::table('stores', function (Blueprint $table) {
            $columns = [
                'flyer_pdf_url',
                'flyer_image_url',
                'flyer_valid_from',
                'flyer_valid_to',
                'flyer_updated_at',
            ];

            if (Schema::hasColumn('stores', 'flyer_catalogs')) {
                $columns[] = 'flyer_catalogs';
            }

            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('flyer_pdf_url')->nullable()->after('meta_description');
            $table->string('flyer_image_url')->nullable()->after('flyer_pdf_url');
            $table->date('flyer_valid_from')->nullable()->after('flyer_image_url');
            $table->date('flyer_valid_to')->nullable()->after('flyer_valid_from');
            $table->timestamp('flyer_updated_at')->nullable()->after('flyer_valid_to');
            $table->json('flyer_catalogs')->nullable()->after('flyer_updated_at');
        });

        $flyers = DB::table('store_flyers')
            ->orderBy('store_id')
            ->orderBy('sort_order')
            ->orderByDesc('valid_from')
            ->get()
            ->groupBy('store_id');

        foreach ($flyers as $storeId => $storeFlyers) {
            $primary = $storeFlyers->first();

            DB::table('stores')->where('id', $storeId)->update([
                'flyer_pdf_url' => $primary->pdf_url,
                'flyer_image_url' => $primary->image_url,
                'flyer_valid_from' => $primary->valid_from,
                'flyer_valid_to' => $primary->valid_to,
                'flyer_updated_at' => $primary->updated_at,
            ]);

            if ($storeFlyers->count() > 1) {
                $catalogs = $storeFlyers->map(function ($flyer) use ($primary) {
                    return [
                        'title' => $flyer->title,
                        'image_url' => $flyer->image_url,
                        'pdf_url' => $flyer->pdf_url,
                        'view_url' => $flyer->view_url,
                        'valid_from' => $flyer->valid_from,
                        'valid_to' => $flyer->valid_to,
                    ];
                })->values()->all();

                DB::table('stores')->where('id', $storeId)->update([
                    'flyer_catalogs' => json_encode($catalogs),
                ]);
            }
        }

        Schema::dropIfExists('store_flyers');
    }
};
