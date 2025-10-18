<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Api\ProductController;
use App\Services\DiscountResponseFormatter;

class TestRedisCacheCommand extends Command
{
    protected $signature = 'redis:test-cache';
    protected $description = 'Test Redis cache implementation and show all keys';

    public function handle()
    {
        $this->info('Testing Redis Cache Implementation');
        $this->line('================================');

        // Test Redis connection
        try {
            Redis::ping();
            $this->info('✅ Redis connection successful');
        } catch (\Exception $e) {
            $this->error('❌ Redis connection failed: ' . $e->getMessage());
            return 1;
        }

        // Clear existing cache first
        $this->info('Clearing existing cache...');
        Cache::flush();

        // Test cache warming
        $this->info('Warming cache with sample data...');
        try {
            $controller = new ProductController(new DiscountResponseFormatter());
            
            // Warm some caches
            $this->line('  - Warming getAllDiscounts...');
            $controller->getAllDiscounts();
            
            $this->line('  - Warming getCategories...');
            $controller->getCategories();
            
            $this->line('  - Warming getStores...');
            $controller->getStores();
            
            $this->line('  - Warming getFavoriteHome...');
            $controller->getFavoriteHome();
            
            $this->info('✅ Cache warming completed!');
        } catch (\Exception $e) {
            $this->error('❌ Error during cache warming: ' . $e->getMessage());
            return 1;
        }

        // Show all Redis keys
        $this->info('Checking Redis keys...');
        try {
            $keys = Redis::keys('*');
            $this->info("Found " . count($keys) . " keys in Redis:");
            
            if (count($keys) > 0) {
                $headers = ['Key', 'TTL (seconds)', 'Type', 'Size'];
                $rows = [];
                
                foreach ($keys as $key) {
                    $ttl = Redis::ttl($key);
                    $type = Redis::type($key);
                    $size = strlen(Redis::get($key) ?: '');
                    
                    $rows[] = [
                        $key,
                        $ttl > 0 ? $ttl : 'No expiry',
                        $type,
                        $size . ' bytes'
                    ];
                }
                
                $this->table($headers, $rows);
            } else {
                $this->warn('No keys found in Redis.');
            }
        } catch (\Exception $e) {
            $this->error('❌ Error checking Redis keys: ' . $e->getMessage());
            return 1;
        }

        // Test cache tags
        $this->info('Testing cache tags...');
        try {
            $taggedKeys = Redis::keys('*discounts*');
            $this->info("Found " . count($taggedKeys) . " discount-related keys:");
            
            foreach ($taggedKeys as $key) {
                $this->line("  - {$key}");
            }
        } catch (\Exception $e) {
            $this->error('❌ Error checking tagged keys: ' . $e->getMessage());
        }

        // Test cache invalidation
        $this->info('Testing cache invalidation...');
        try {
            $controller->clearCache();
            $this->info('✅ Cache cleared successfully!');
            
            $keysAfterClear = Redis::keys('*');
            $this->info("Keys remaining after clear: " . count($keysAfterClear));
        } catch (\Exception $e) {
            $this->error('❌ Error clearing cache: ' . $e->getMessage());
        }

        $this->info('Redis Cache Test Complete!');
        return 0;
    }
}
