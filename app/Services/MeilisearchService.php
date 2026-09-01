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
                'product_name_stem',
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
                'product_id',
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
            'product_name_stem' => self::buildNameStem($product ? ($product->name ?? '') : ''),
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

    /**
     * Finds products with a similar name by matching stemmed keywords, excluding
     * the given product and deduplicating by product_id (a product can have
     * multiple active discounts across stores). Restricted to the same category
     * so a shared stem word can't pull in an unrelated product from elsewhere in
     * the catalog.
     */
    public function findSimilarDiscounts(string $stemQuery, int $excludeProductId, int $categoryId, int $limit = 7)
    {
        if (trim($stemQuery) === '') {
            return collect();
        }

        try {
            $index = $this->client->index($this->indexName);

            $response = $index->search($stemQuery, [
                'attributesToSearchOn' => ['product_name_stem'],
                'filter' => "product_id != {$excludeProductId} AND category_id = {$categoryId}",
                'limit' => $limit * 4,
            ]);

            $discountIds = [];
            $seenProductIds = [];

            foreach ($response->getHits() as $hit) {
                $productId = $hit['product_id'] ?? null;

                if ($productId === null || isset($seenProductIds[$productId])) {
                    continue;
                }

                $seenProductIds[$productId] = true;
                $discountIds[] = $hit['id'];

                if (count($discountIds) >= $limit) {
                    break;
                }
            }

            if (empty($discountIds)) {
                return collect();
            }

            $discounts = Discount::query()
                ->whereIn('id', $discountIds)
                ->with(['product.category', 'product.discounts.store', 'store'])
                ->get()
                ->keyBy('id');

            return collect($discountIds)
                ->map(fn ($id) => $discounts->get($id))
                ->filter()
                ->values();
        } catch (\Exception $e) {
            Log::error('Meilisearch findSimilarDiscounts failed: ' . $e->getMessage());

            return collect();
        }
    }

    /**
     * Builds a whitespace-joined list of lightly stemmed keywords from a product
     * name, for fuzzy "similar product" matching. Strips brand names (written in
     * ALL CAPS in this catalog), package sizes/units, generic filler words, and
     * common Lithuanian noun/adjective case endings so that inflected forms of the
     * same word (e.g. "arbata", "arbatos", "arbatoms") share a stem.
     */
    public static function buildNameStem(?string $name): string
    {
        if (empty($name)) {
            return '';
        }

        // Drop parenthetical noise, e.g. "(įv. rūšių)"
        $name = preg_replace('/\([^)]*\)/u', ' ', $name);

        // Catalog convention: truncated words are written with a trailing period
        // (e.g. "sald." standing for "saldintas"/"saldumynai"/"saldainiai"/...).
        // Such stubs are ambiguous across unrelated words, so they need a higher
        // length bar than complete words to count as a similarity signal.
        preg_match_all('/([A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+)\./u', $name, $abbrevMatches);
        $abbreviated = array_flip(array_map(
            fn ($w) => mb_strtolower($w, 'UTF-8'),
            $abbrevMatches[1]
        ));

        $words = preg_split('/[^A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($words)) {
            return '';
        }

        static $stopwords = [
            'iv', 'įv', 'rūšių', 'rūšies', 'skonio', 'skonis', 'proc', 'vnt', 'pak',
            'g', 'kg', 'ml', 'cl', 'l', 'x',
        ];

        static $suffixes = null;
        if ($suffixes === null) {
            $suffixes = [
                'iams', 'omis', 'umas', 'ose', 'oje', 'iai', 'ėms', 'oms',
                // 'as' (nominative singular, e.g. "avokadas"/"pomidoras"/
                // "agurkas") was missing — its plural "-ai" was already
                // stripped, so a generic named in the plural (very common
                // for produce) never matched the singular product form at
                // all. Found live: "Didysis avokadas..." didn't match the
                // "Avokadai" generic.
                'ai', 'as', 'os', 'io', 'ių', 'ų', 'is',
                'ė', 'ę', 'ą', 'į', 'a', 'o', 'e', 'i', 'u',
            ];
            usort($suffixes, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));
        }

        $stems = [];

        foreach ($words as $word) {
            $lower = mb_strtolower($word, 'UTF-8');

            // Brand / model names are written in ALL CAPS in this catalog.
            if ($word === mb_strtoupper($word, 'UTF-8') && mb_strlen($word, 'UTF-8') > 1) {
                continue;
            }

            $minLength = isset($abbreviated[$lower]) ? 6 : 4;

            if (mb_strlen($lower, 'UTF-8') < $minLength || in_array($lower, $stopwords, true)) {
                continue;
            }

            $stem = $lower;

            // Was >= 6, which skipped stemming entirely for common 5-letter
            // declined nouns like "sūris"/"sūrio" (cheese) — both stayed as
            // literal, different strings instead of unifying to "sūr". The
            // "remaining stem >= 3 chars" guard below already protects
            // against over-stripping short words.
            if (mb_strlen($lower, 'UTF-8') >= 5) {
                foreach ($suffixes as $suffix) {
                    $suffixLen = mb_strlen($suffix, 'UTF-8');

                    if (mb_substr($lower, -$suffixLen, null, 'UTF-8') === $suffix
                        && mb_strlen($lower, 'UTF-8') - $suffixLen >= 3) {
                        $stem = mb_substr($lower, 0, mb_strlen($lower, 'UTF-8') - $suffixLen, 'UTF-8');
                        break;
                    }
                }
            }

            $stems[] = $stem;
        }

        return implode(' ', array_unique($stems));
    }
}
