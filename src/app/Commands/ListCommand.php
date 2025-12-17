<?php

namespace App\Commands;

use App\Services\Analytics;
use App\Services\DocRepository;
use LaravelZero\Framework\Commands\Command;

/**
 * Lists available Flux UI documentation items.
 *
 * Displays components, layouts, and guides with optional
 * category filtering and JSON output support.
 */
class ListCommand extends Command
{
    protected $signature = 'docs
        {--category= : Filter by category (components, layouts, guides)}
        {--json : Output as JSON array}';

    protected $description = 'List available Flux UI documentation';

    public function handle(DocRepository $repo, Analytics $analytics): int
    {
        $startTime = microtime(true);
        $category = $this->option('category');

        // Validate category
        if ($category && ! in_array($category, ['components', 'layouts', 'guides'])) {
            $this->error("Invalid category: {$category}");
            $this->line('Valid categories: components, layouts, guides');
            $analytics->track('docs', self::FAILURE, ['category' => $category], $startTime);

            return self::FAILURE;
        }

        $items = $repo->list($category);

        if ($this->option('json')) {
            $this->line(json_encode($items, JSON_PRETTY_PRINT));
            $analytics->track('docs', self::SUCCESS, ['category' => $category], $startTime);

            return self::SUCCESS;
        }

        $totalCount = 0;

        foreach ($items as $cat => $names) {
            if (empty($names)) {
                continue;
            }

            $this->info(ucfirst($cat) . ' (' . count($names) . ')');
            $this->line('  ' . implode(', ', $names));
            $this->newLine();

            $totalCount += count($names);
        }

        if ($totalCount === 0) {
            $this->warn('No documentation found. Run "flux update" to fetch docs.');
        }

        $analytics->track('docs', self::SUCCESS, ['category' => $category], $startTime);

        return self::SUCCESS;
    }
}
