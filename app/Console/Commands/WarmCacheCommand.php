<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CacheWarmingService;

class WarmCacheCommand extends Command
{
    protected $signature = 'cache:warm {--type=all : Type of cache to warm (all, stores, categories, products, favorites)}';
    protected $description = 'Warm up application cache for better performance';

    public function handle(CacheWarmingService $cacheWarmingService)
    {
        $type = $this->option('type');

        $this->info("Starting cache warming for type: {$type}");

        switch ($type) {
            case 'all':
                $cacheWarmingService->warmCriticalCaches();
                $cacheWarmingService->warmStoreCategoryCaches();
                $cacheWarmingService->warmFavoritesCache();
                break;
            case 'stores':
                $cacheWarmingService->warmStoreCaches();
                break;
            case 'categories':
                $cacheWarmingService->warmCategoryCaches();
                break;
            case 'products':
                $cacheWarmingService->warmPopularProductsCache();
                break;
            case 'favorites':
                $cacheWarmingService->warmFavoritesCache();
                break;
            default:
                $this->error("Invalid cache type: {$type}");
                return 1;
        }

        $this->info("Cache warming completed successfully!");
        return 0;
    }
}
