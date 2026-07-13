<?php

namespace App\Services;

use App\Models\Discount;
use Meilisearch\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class MeilisearchService
{
    protected $client;
    protected $indexName = 'discounts';

    public function __construct()
    {
        $config = Config::get('services.meilisearch');
        $this->client = new Client($config['host'], $config['key']);
    }

    public function ensureIndexConfigured(): void
    {
        $this->configureIndex();
    }

    protected function configureIndex()
    {
        try {
            $index = $this->client->index($this->indexName);

            $index->updateSearchableAttributes([
                'product_name',
                'product_brand',
            ]);

            $task = $index->updateFilterableAttributes([
                'store_id',
                'category_id',
                'category_name',
                'card',
                'condition',
                'discount_percent',
                'discounted_price',
                'original_price',
                'end_at_timestamp',
            ]);

            $this->waitForTask($index, $task);

            $index->updateSortableAttributes([
                'discounted_price',
                'original_price',
                'discount_percent',
                'savings_amount',
                'end_at_timestamp',
            ]);

            $index->updateRankingRules([
                'exactness',
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
            ]);

        } catch (\Exception $e) {
            Log::error('Meilisearch index configuration failed: ' . $e->getMessage());
        }
    }

    protected function waitForTask($index, $task, int $maxWaitTime = 10)
    {
        $taskUid = null;
        if (is_array($task)) {
            $taskUid = $task['taskUid'] ?? $task['uid'] ?? null;
        } elseif (is_object($task)) {
            $taskUid = $task->taskUid ?? $task->uid ?? (method_exists($task, 'getTaskUid') ? $task->getTaskUid() : null);
        }

        if ($taskUid) {
            try {
                $index->waitForTask($taskUid, $maxWaitTime * 1000);
            } catch (\Exception $e) {
                Log::warning('Meilisearch waitForTask failed in configureIndex: ' . $e->getMessage());
            }
        }
    }

    public function indexDiscount(Discount $discount)
    {
        if (!$this->isActiveDiscount($discount)) {
            $this->deleteDiscount($discount->id);
            return;
        }

        try {
            $document = $this->transformDiscount($discount);
            $index = $this->client->index($this->indexName);
            $task = $index->addDocuments([$document], 'id');

            $taskUid = null;
            if (is_array($task)) {
                $taskUid = $task['taskUid'] ?? $task['uid'] ?? null;
            } elseif (is_object($task)) {
                $taskUid = $task->taskUid ?? $task->uid ?? (method_exists($task, 'getTaskUid') ? $task->getTaskUid() : null);
            }

            if ($taskUid) {
                try {
                    $index->waitForTask($taskUid);
                } catch (\Exception $e) {
                    Log::warning('Meilisearch waitForTask failed in indexDiscount: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error('Meilisearch indexDiscount failed: ' . $e->getMessage());
        }
    }

    public function deleteDiscount(int $discountId)
    {
        $index = $this->client->index($this->indexName);
        try {
            $index->deleteDocument($discountId);
        } catch (\Exception $e) {
            Log::error('Meilisearch deleteDiscount failed: ' . $e->getMessage());
        }
    }

    public function search(string $query, array $filters = [], array $sort = [], int $page = 1, int $perPage = 24)
    {
        try {
            $index = $this->client->index($this->indexName);

            $searchParams = [
                'limit' => $perPage,
                'offset' => ($page - 1) * $perPage,
                'showRankingScore' => true,
//                'matchingStrategy' => 'last',
            ];

            $filterString = $this->buildFilterString($filters);
            if ($filterString) {
                $searchParams['filter'] = $filterString;
            }

            if (!empty($sort)) {
                $searchParams['sort'] = $sort;
            }

            if (empty(trim($query))) {
                $query = null;
            }

            $response = $index->search($query ?? '', $searchParams);

            return [
                'hits' => $response->getHits(),
                'total' => $response->getEstimatedTotalHits(),
                'page' => $page,
                'perPage' => $perPage,
            ];
        } catch (\Exception $e) {
            Log::error('Meilisearch search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function indexAllActiveDiscounts()
    {
        try {
            $index = $this->client->index($this->indexName);

            try {
                $index->deleteAllDocuments();
            } catch (\Exception $e) {
                Log::warning('Meilisearch deleteAllDocuments failed (index might be empty): ' . $e->getMessage());
            }

            $this->configureIndex();

            $discounts = Discount::with(['product.category', 'store'])
                ->get();

            Log::info('Meilisearch indexing discounts', ['count' => $discounts->count()]);

            if ($discounts->isEmpty()) {
                Log::warning('No active discounts found to index');
                return 0;
            }

            $documents = [];
            foreach ($discounts as $discount) {
                try {
                    $doc = $this->transformDiscount($discount);
                    if (!empty($doc['id'])) {
                        $documents[] = $doc;
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to transform discount', [
                        'discount_id' => $discount->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Meilisearch documents prepared', ['count' => count($documents)]);

            if (empty($documents)) {
                Log::warning('No valid documents to index after transformation');
                return 0;
            }

            if (count($documents) > 0) {
                Log::debug('Sample document structure', ['sample' => $documents[0]]);
            }

            try {
                $task = $index->addDocuments($documents, 'id');

                Log::info('Meilisearch addDocuments called', [
                    'documents_count' => count($documents),
                    'task_type' => gettype($task),
                    'task' => is_object($task) ? get_class($task) : $task,
                ]);

                $taskUid = null;
                if (is_array($task)) {
                    $taskUid = $task['taskUid'] ?? $task['uid'] ?? $task['taskUid'] ?? null;
                } elseif (is_object($task)) {
                    $taskUid = $task->taskUid ?? $task->uid ?? (method_exists($task, 'getTaskUid') ? $task->getTaskUid() : null);
                    if (!$taskUid && method_exists($task, 'toArray')) {
                        $taskArray = $task->toArray();
                        $taskUid = $taskArray['taskUid'] ?? $taskArray['uid'] ?? null;
                    }
                }

                Log::info('Meilisearch task info', ['task_uid' => $taskUid]);

                if ($taskUid) {
                    try {
                        $maxWaitTime = 300;
                        $startTime = time();
                        $pollInterval = 1;
                        $lastStatus = null;

                        Log::info('Meilisearch starting task polling', ['task_uid' => $taskUid]);

                        while (true) {
                            $currentTask = $index->getTask($taskUid);

                            $taskData = is_array($currentTask) ? $currentTask : (method_exists($currentTask, 'toArray') ? $currentTask->toArray() : get_object_vars($currentTask));
                            $status = $taskData['status'] ?? null;

                            if ($status !== $lastStatus) {
                                Log::info('Meilisearch task status changed', [
                                    'task_uid' => $taskUid,
                                    'status' => $status,
                                    'elapsed' => time() - $startTime,
                                    'task_data' => $taskData,
                                ]);
                                $lastStatus = $status;
                            }

                            if ($status === 'succeeded') {
                                Log::info('Meilisearch task succeeded', [
                                    'task_uid' => $taskUid,
                                    'elapsed' => time() - $startTime,
                                ]);
                                break;
                            }

                            if ($status === 'failed') {
                                $error = $taskData['error'] ?? 'Unknown error';
                                Log::error('Meilisearch task failed', [
                                    'task_uid' => $taskUid,
                                    'error' => $error,
                                    'task_data' => $taskData,
                                ]);
                                throw new \Exception('Meilisearch indexing task failed: ' . json_encode($error));
                            }

                            if ((time() - $startTime) > $maxWaitTime) {
                                Log::warning('Meilisearch task timeout', [
                                    'task_uid' => $taskUid,
                                    'current_status' => $status,
                                    'elapsed' => time() - $startTime,
                                ]);
                                break;
                            }

                            sleep($pollInterval);
                        }

                        Log::info('Meilisearch indexing task polling completed', ['task_uid' => $taskUid]);
                    } catch (\Exception $e) {
                        Log::error('Meilisearch task polling failed', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e;
                    }
                } else {
                    sleep(2);
                }
            } catch (\Exception $e) {
                Log::error('Meilisearch addDocuments failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            $stats = $index->stats();
            Log::info('Meilisearch index stats', ['stats' => $stats]);

            return count($documents);
        } catch (\Exception $e) {
            Log::error('Meilisearch indexAllActiveDiscounts failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getIndexStats()
    {
        try {
            $index = $this->client->index($this->indexName);
            return $index->stats();
        } catch (\Exception $e) {
            Log::error('Meilisearch getIndexStats failed: ' . $e->getMessage());
            return null;
        }
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
            'original_price' => (float) $discount->original_price,
            'discounted_price' => (float) $discount->discounted_price,
            'discount_percent' => (int) $discount->discount_percent,
            'savings_amount' => (float) $savingsAmount,
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

        if (isset($filters['category_ids']) && !empty($filters['category_ids'])) {
            $categoryIds = is_array($filters['category_ids']) ? $filters['category_ids'] : [$filters['category_ids']];
            $categoryIdsStr = implode(', ', array_map('intval', $categoryIds));
            $filterParts[] = "category_id IN [{$categoryIdsStr}]";
        }

        if (isset($filters['category_name']) && !empty($filters['category_name'])) {
            $categoryName = addslashes($filters['category_name']);
            $filterParts[] = "category_name = \"{$categoryName}\"";
        }

        if (isset($filters['end_at_timestamp']) && is_array($filters['end_at_timestamp'])) {
            if (isset($filters['end_at_timestamp']['>='])) {
                $filterParts[] = 'end_at_timestamp >= ' . intval($filters['end_at_timestamp']['>=']);
            }
            if (isset($filters['end_at_timestamp']['<='])) {
                $filterParts[] = 'end_at_timestamp <= ' . intval($filters['end_at_timestamp']['<=']);
            }
        }

        return !empty($filterParts) ? implode(' AND ', $filterParts) : null;
    }

    protected function isActiveDiscount(Discount $discount)
    {
        return $discount->end_at === null || $discount->end_at >= now()->startOfDay();
    }
}
