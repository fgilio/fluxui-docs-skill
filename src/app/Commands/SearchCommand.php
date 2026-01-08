<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Analytics;
use App\Services\SearchIndex;
use LaravelZero\Framework\Commands\Command;

/**
 * Fuzzy search for Flux UI documentation.
 *
 * Returns relevance-ranked results with scoring
 * based on name, title, and description matches.
 */
class SearchCommand extends Command
{
    protected $signature = 'search
        {query : Search term}
        {--limit=10 : Maximum number of results}
        {--json : Output as JSON}';

    protected $description = 'Search Flux UI documentation';

    public function handle(SearchIndex $index, Analytics $analytics): int
    {
        $startTime = microtime(true);
        $query = $this->argument('query');
        $limit = (int) $this->option('limit');

        $results = $index->search($query, $limit);

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));
            $analytics->track('search', self::SUCCESS, [
                'query' => $query,
                'result_count' => count($results),
            ], $startTime);

            return self::SUCCESS;
        }

        if (empty($results)) {
            $this->warn("No results for: {$query}");
            $this->line('Try a different search term or run "flux list" to see all items.');
            $analytics->track('search', self::SUCCESS, [
                'query' => $query,
                'result_count' => 0,
            ], $startTime);

            return self::SUCCESS;
        }

        $this->info("Results for: {$query}");
        $this->newLine();

        $tableData = array_map(function ($result) use ($query) {
            $name = $result['name'];
            $pro = ($result['pro'] ?? false) ? ' [Pro]' : '';

            // Add context when match is from examples (components_used)
            $context = '';
            if (($result['match_source'] ?? '') === 'examples') {
                $context = " (contains flux:{$query} in examples)";
            }

            return [
                $name.$context,
                $result['category'],
                mb_substr($result['description'] ?? '', 0, 40).(mb_strlen($result['description'] ?? '') > 40 ? '...' : '').$pro,
            ];
        }, $results);

        $this->table(['Name', 'Category', 'Description'], $tableData);

        $analytics->track('search', self::SUCCESS, [
            'query' => $query,
            'result_count' => count($results),
        ], $startTime);

        return self::SUCCESS;
    }
}
