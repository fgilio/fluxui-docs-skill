<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Analytics;
use App\Services\DocRepository;
use LaravelZero\Framework\Commands\Command;

/**
 * Shows where a component is used in documentation examples.
 *
 * Displays pages and sections containing the specified component,
 * enabling discovery of usage patterns and undocumented components.
 */
class UsagesCommand extends Command
{
    protected $signature = 'usages
        {component : Component name (e.g., subheading, modal.close)}
        {--json : Output as JSON}';

    protected $description = 'Show where a component is used in documentation examples';

    public function handle(DocRepository $repo, Analytics $analytics): int
    {
        $startTime = microtime(true);
        $component = mb_strtolower($this->argument('component'));

        // Remove flux: prefix if provided
        $component = preg_replace('/^flux:/', '', $component);

        $usages = $repo->loadUsages();

        if (! $usages || empty($usages['usages'])) {
            $this->warn('Usages index not found. Run "fluxui-docs update" first.');
            $analytics->track('usages', self::FAILURE, [
                'component' => $component,
                'found' => false,
            ], $startTime);

            return self::FAILURE;
        }

        $componentUsages = $usages['usages'][$component] ?? null;

        if (! $componentUsages) {
            $this->warn("No usages found for: flux:{$component}");
            $this->newLine();

            // Suggest similar components
            $similar = $this->findSimilar($component, array_keys($usages['usages']));
            if (! empty($similar)) {
                $this->line('Similar components:');
                foreach ($similar as $suggestion) {
                    $this->line("  - flux:{$suggestion}");
                }
            }

            $analytics->track('usages', self::SUCCESS, [
                'component' => $component,
                'found' => false,
            ], $startTime);

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'component' => $component,
                'usages' => $componentUsages,
            ], JSON_PRETTY_PRINT));

            $analytics->track('usages', self::SUCCESS, [
                'component' => $component,
                'found' => true,
                'usage_count' => count($componentUsages),
            ], $startTime);

            return self::SUCCESS;
        }

        $this->renderUsages($component, $componentUsages, $repo);

        $analytics->track('usages', self::SUCCESS, [
            'component' => $component,
            'found' => true,
            'usage_count' => count($componentUsages),
        ], $startTime);

        return self::SUCCESS;
    }

    /**
     * Render component usages in a readable format.
     */
    private function renderUsages(string $component, array $usages, DocRepository $repo): void
    {
        $this->info("flux:{$component} is used in:");
        $this->newLine();

        // Check if component has its own documentation
        $hasOwnDocs = $repo->find($component) !== null;

        if ($hasOwnDocs) {
            $this->line('  Note: This component has its own documentation page.');
            $this->line("  Run \"fluxui-docs show {$component}\" for full details.");
            $this->newLine();
        }

        foreach ($usages as $usage) {
            $page = $usage['page'];
            $category = $usage['category'];
            $sections = $usage['sections'] ?? [];

            $this->comment("  {$category}/{$page}");

            if (! empty($sections)) {
                foreach ($sections as $section) {
                    $this->line("    └─ {$section}");
                }
            }
        }

        $this->newLine();
        $this->line('Total: '.count($usages).' page(s)');
    }

    /**
     * Find similar component names using Levenshtein distance.
     */
    private function findSimilar(string $name, array $all, int $limit = 5): array
    {
        $distances = [];

        foreach ($all as $item) {
            $distance = levenshtein($name, $item);
            if ($distance <= 3) {
                $distances[$item] = $distance;
            }
        }

        asort($distances);

        return array_slice(array_keys($distances), 0, $limit);
    }
}
