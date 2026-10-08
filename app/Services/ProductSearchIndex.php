<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Config;
use Meilisearch\Client;

// Meilisearch "products" index: every product, with or without a current
// offer. Keyword pages map their products through it (KeywordPageProductMapper),
// so Lithuanian inflections ("vitamino D", "magnio citratas") match without
// listing every form in search_terms. The "discounts" index
// (MeilisearchService) stays the site search's index.
class ProductSearchIndex
{
    private const INDEX = 'products';

    // INDEX with services.meilisearch.index_prefix in front.
    private string $index;

    private const BATCH = 5000;

    private Client $client;

    public function __construct()
    {
        $config = Config::get('services.meilisearch');
        $this->client = new Client($config['host'], $config['key']);
        $this->index = ($config['index_prefix'] ?? '').self::INDEX;
    }

    public static function enabled(): bool
    {
        return (bool) config('services.meilisearch.enabled');
    }

    public function configure(): void
    {
        $index = $this->client->index($this->index);

        $this->wait($index->updateSearchableAttributes(['name', 'brand', 'name_stem']));
        $this->wait($index->updateFilterableAttributes(['category_id']));
        // Typos only on longer words: "magnis" may match "magnio", but a
        // dose or short code ("D3", "B12", "N60") never matches its neighbour.
        $this->wait($index->updateTypoTolerance([
            'minWordSizeForTypos' => ['oneTypo' => 5, 'twoTypos' => 9],
            'disableOnNumbers' => true,
        ]));
        $this->wait($index->updatePagination(['maxTotalHits' => 5000]));
    }

    // Full reindex. Returns the number of products sent.
    public function indexAll(): int
    {
        // Fails harmlessly (as a task) when the index already exists.
        $this->wait($this->client->createIndex($this->index, ['primaryKey' => 'id']));
        $this->configure();

        $index = $this->client->index($this->index);
        $this->wait($index->deleteAllDocuments());

        $count = 0;
        Product::query()
            ->select(['id', 'name', 'brand', 'category_id'])
            ->chunkById(self::BATCH, function ($products) use ($index, &$count) {
                $documents = $products->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => (string) $product->name,
                    'brand' => (string) ($product->brand ?? ''),
                    'name_stem' => MeilisearchService::buildNameStem($product->name),
                    'category_id' => $product->category_id,
                ])->all();

                $this->wait($index->addDocuments($documents, 'id'), 120);
                $count += count($documents);
            });

        return $count;
    }

    /**
     * Products whose name/brand contain every word of $term (Meilisearch
     * "all" matching strategy), optionally within the given categories.
     *
     * @param  list<int>  $categoryIds
     * @return list<array{id: int, name: string, brand: string}>
     */
    public function search(string $term, array $categoryIds = [], int $limit = 5000): array
    {
        $params = [
            'limit' => $limit,
            'matchingStrategy' => 'all',
            'attributesToRetrieve' => ['id', 'name', 'brand'],
        ];

        if ($categoryIds !== []) {
            $params['filter'] = 'category_id IN [' . implode(', ', array_map('intval', $categoryIds)) . ']';
        }

        return array_map(fn (array $hit) => [
            'id' => (int) $hit['id'],
            'name' => (string) ($hit['name'] ?? ''),
            'brand' => (string) ($hit['brand'] ?? ''),
        ], $this->client->index($this->index)->search($term, $params)->getHits());
    }

    private function wait(mixed $task, int $seconds = 30): void
    {
        $uid = is_array($task) ? ($task['taskUid'] ?? null) : ($task->taskUid ?? null);
        if ($uid !== null) {
            $this->client->waitForTask($uid, $seconds * 1000);
        }
    }
}
