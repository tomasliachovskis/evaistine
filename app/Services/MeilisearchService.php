<?php

namespace App\Services;

use App\Models\Discount;
use Meilisearch\Client;
use Illuminate\Support\Facades\Config;

class MeilisearchService
{
    protected $client;
    protected $indexName = 'discounts';

    public function __construct()
    {
        $config = Config::get('services.meilisearch');
        $this->client = new Client($config['host'], $config['key']);
        $this->configureIndex();
    }

    protected function configureIndex()
    {
        try {
            $index = $this->client->index($this->indexName);

            $index->updateSearchableAttributes([
                'product_name',
                'product_brand',
                'category_name',
                'store_name',
            ]);

            $index->updateFilterableAttributes([
                'store_id',
                'category_id',
                'card',
                'condition',
                'discount_percent',
                'discounted_price',
                'original_price',
                'end_at_timestamp',
            ]);

            $index->updateSortableAttributes([
                'discounted_price',
                'original_price',
                'discount_percent',
                'savings_amount',
                'end_at_timestamp',
            ]);

            $index->updateRankingRules([
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness',
            ]);
        } catch (\Exception $e) {
        }
    }

    public function indexDiscount(Discount $discount)
    {
        if (!$this->isActiveDiscount($discount)) {
            $this->deleteDiscount($discount->id);
            return;
        }

        $document = $this->transformDiscount($discount);
        $index = $this->client->index($this->indexName);
        $index->addDocuments([$document]);
    }

    public function deleteDiscount(int $discountId)
    {
        $index = $this->client->index($this->indexName);
        try {
            $index->deleteDocument($discountId);
        } catch (\Exception $e) {
        }
    }

    public function search(string $query, array $filters = [], array $sort = [], int $page = 1, int $perPage = 25)
    {
        $index = $this->client->index($this->indexName);

        $searchParams = [
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];

        $filterString = $this->buildFilterString($filters);
        if ($filterString) {
            $searchParams['filter'] = $filterString;
        }

        if (!empty($sort)) {
            $searchParams['sort'] = $sort;
        }

        $response = $index->search($query, $searchParams);

        if (method_exists($response, 'getHits')) {
            $hits = $response->getHits();
            $total = $response->getEstimatedTotalHits();
        } elseif (method_exists($response, 'toArray')) {
            $data = $response->toArray();
            $hits = $data['hits'] ?? [];
            $total = $data['estimatedTotalHits'] ?? 0;
        } elseif (isset($response->hits)) {
            $hits = $response->hits;
            $total = $response->estimatedTotalHits ?? 0;
        } else {
            $hits = [];
            $total = 0;
        }

        return [
            'hits' => $hits,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function indexAllActiveDiscounts()
    {
        $index = $this->client->index($this->indexName);

        try {
            $index->deleteAllDocuments();
        } catch (\Exception $e) {
        }

        $this->configureIndex();

        $discounts = Discount::with(['product.category', 'store'])->get();

        $documents = [];
        foreach ($discounts as $discount) {
            $documents[] = $this->transformDiscount($discount);
        }

        if (!empty($documents)) {
            $index->addDocuments($documents);
        }

        return count($documents);
    }

    protected function transformDiscount(Discount $discount)
    {
        $product = $discount->product;
        $store = $discount->store;

        $savingsAmount = $discount->original_price - $discount->discounted_price;

        return [
            'id' => $discount->id,
            'product_id' => $discount->product_id,
            'product_name' => $product ? ($product->name ?? '') : '',
            'product_brand' => $product ? ($product->brand ?? '') : '',
            'product_slug' => $product ? ($product->slug ?? '') : '',
            'category_id' => $product ? ($product->category_id ?? null) : null,
            'category_name' => ($product && $product->category) ? $product->category->name : null,
            'category_slug' => ($product && $product->category) ? $product->category->slug : null,
            'store_id' => $discount->store_id,
            'store_name' => $store ? ($store->name ?? '') : '',
            'store_slug' => $store ? ($store->slug ?? '') : '',
            'original_price' => $discount->original_price,
            'discounted_price' => $discount->discounted_price,
            'discount_percent' => $discount->discount_percent,
            'savings_amount' => $savingsAmount,
            'condition' => $discount->condition,
            'card' => $discount->card,
            'start_at' => $discount->start_at ? $discount->start_at->timestamp : null,
            'end_at' => $discount->end_at ? $discount->end_at->timestamp : null,
            'end_at_timestamp' => $discount->end_at ? $discount->end_at->timestamp : null,
            'product_url' => $discount->product_url,
        ];
    }

    protected function buildFilterString(array $filters)
    {
        $filterParts = [];

        if (isset($filters['card']) && $filters['card']) {
            $filterParts[] = 'card = true';
        }

        if (isset($filters['plus']) && $filters['plus']) {
            $filterParts[] = 'condition = "1+1"';
        }

        if (isset($filters['store_ids']) && !empty($filters['store_ids'])) {
            $storeIds = is_array($filters['store_ids']) ? $filters['store_ids'] : [$filters['store_ids']];
            $storeIdsStr = implode(', ', array_map('intval', $storeIds));
            $filterParts[] = "store_id IN [{$storeIdsStr}]";
        }

        if (isset($filters['category_id'])) {
            $filterParts[] = 'category_id = ' . intval($filters['category_id']);
        }

        return !empty($filterParts) ? implode(' AND ', $filterParts) : null;
    }

    protected function isActiveDiscount(Discount $discount)
    {
        return $discount->end_at === null || $discount->end_at >= now();
    }
}

