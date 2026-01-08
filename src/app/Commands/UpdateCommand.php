<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\DocRepository;
use App\Services\Scraper;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\progress;

/**
 * Scrapes latest Flux UI documentation from fluxui.dev.
 *
 * Parses HTML pages to extract structured documentation,
 * handles rate limiting, and rebuilds the search index.
 */
class UpdateCommand extends Command
{
    protected $signature = 'update
        {--item= : Update single item (e.g., button, modal)}
        {--category= : Category for single item (component, layout, guide)}
        {--delay=500 : Delay between requests in milliseconds}
        {--dry-run : Show what would be scraped without saving}';

    protected $description = 'Scrape latest Flux UI documentation from fluxui.dev';

    public function handle(Scraper $scraper, DocRepository $repo): int
    {
        $singleItem = $this->option('item');
        $category = $this->option('category');
        $delay = (int) $this->option('delay');
        $dryRun = $this->option('dry-run');

        if ($singleItem) {
            return $this->updateSingle($scraper, $repo, $singleItem, $category, $dryRun);
        }

        return $this->updateAll($scraper, $repo, $delay, $dryRun);
    }

    /**
     * Update a single documentation item.
     */
    private function updateSingle(
        Scraper $scraper,
        DocRepository $repo,
        string $name,
        ?string $category,
        bool $dryRun
    ): int {
        // Try to detect category if not provided
        if (! $category) {
            $category = $this->detectCategory($repo, $name);
        }

        if (! $category) {
            $this->error("Could not determine category for: {$name}");
            $this->line('Use --category=component|layout|guide to specify');

            return self::FAILURE;
        }

        $this->info("Scraping {$category}: {$name}...");

        $doc = $scraper->scrape($category, $name);

        if (! $doc) {
            $this->error("Failed to scrape: {$name}");

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Dry run - would save:');
            $this->line(json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $pluralCategory = $category === 'component' ? 'components'
                : ($category === 'layout' ? 'layouts' : 'guides');
            $repo->save($pluralCategory, $name, $doc);
            $this->info("Saved: {$name}");

            // Rebuild indexes
            $repo->rebuildIndex();
            $repo->rebuildUsages();
            $this->info('Search and usages indexes updated.');
        }

        return self::SUCCESS;
    }

    /**
     * Update all documentation items.
     */
    private function updateAll(
        Scraper $scraper,
        DocRepository $repo,
        int $delay,
        bool $dryRun
    ): int {
        $this->info('Discovering documentation items from fluxui.dev...');

        $items = $scraper->discoverAll();

        if (empty($items)) {
            $this->error('No items discovered. Check network connection.');

            return self::FAILURE;
        }

        $this->info('Found '.count($items).' items to scrape.');
        $this->newLine();

        if ($dryRun) {
            $this->warn('Dry run mode - no files will be saved.');
            $this->newLine();
        }

        $progress = progress(
            label: 'Scraping documentation',
            steps: count($items)
        );

        $progress->start();

        $success = 0;
        $failed = 0;

        foreach ($items as $item) {
            $progress->hint("{$item['category']}: {$item['name']}");

            $doc = $scraper->scrape($item['category'], $item['name']);

            if ($doc && ! $dryRun) {
                $pluralCategory = $item['category'] === 'component' ? 'components'
                    : ($item['category'] === 'layout' ? 'layouts' : 'guides');
                $repo->save($pluralCategory, $item['name'], $doc);
                $success++;
            } elseif ($doc) {
                $success++;
            } else {
                $failed++;
            }

            $progress->advance();

            // Rate limiting
            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }

        $progress->finish();

        $this->newLine();

        if (! $dryRun) {
            $this->info('Rebuilding search index...');
            $repo->rebuildIndex();

            $this->info('Rebuilding component usages index...');
            $repo->rebuildUsages();
        }

        $this->info("Update complete: {$success} succeeded, {$failed} failed");

        if (! $dryRun) {
            $this->line("Data saved to: {$repo->getDataPath()}");
        }

        return self::SUCCESS;
    }

    /**
     * Try to detect the category of an existing item.
     */
    private function detectCategory(DocRepository $repo, string $name): ?string
    {
        // Check existing data
        foreach (['components' => 'component', 'layouts' => 'layout', 'guides' => 'guide'] as $plural => $singular) {
            $items = $repo->list($plural);
            if (isset($items[$plural]) && in_array($name, $items[$plural])) {
                return $singular;
            }
        }

        // Default to component for common names
        return 'component';
    }
}
