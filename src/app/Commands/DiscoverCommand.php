<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Analytics;
use App\Services\DocRepository;
use LaravelZero\Framework\Commands\Command;

/**
 * Discovers components found in examples but without dedicated documentation.
 *
 * Surfaces "hidden" components like flux:subheading that exist in code
 * examples but don't have their own documentation pages.
 */
class DiscoverCommand extends Command
{
    protected $signature = 'discover
        {--json : Output as JSON}';

    protected $description = 'List components found in examples without dedicated documentation';

    public function handle(DocRepository $repo, Analytics $analytics): int
    {
        $startTime = microtime(true);

        $undocumented = $repo->findUndocumentedComponents();

        if (empty($undocumented)) {
            $this->info('All components in examples have dedicated documentation.');
            $this->line('Run "fluxui-docs update" to refresh the index.');

            $analytics->track('discover', self::SUCCESS, [
                'undocumented_count' => 0,
            ], $startTime);

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode($undocumented, JSON_PRETTY_PRINT));
            $analytics->track('discover', self::SUCCESS, [
                'undocumented_count' => count($undocumented),
            ], $startTime);

            return self::SUCCESS;
        }

        $this->renderDiscovered($undocumented);

        $analytics->track('discover', self::SUCCESS, [
            'undocumented_count' => count($undocumented),
        ], $startTime);

        return self::SUCCESS;
    }

    /**
     * Render discovered components in a readable format.
     */
    private function renderDiscovered(array $undocumented): void
    {
        // Separate sub-components from truly undocumented
        $subComponents = [];
        $noDocumentation = [];

        foreach ($undocumented as $component => $data) {
            if ($data['type'] === 'sub_component') {
                $subComponents[$component] = $data;
            } else {
                $noDocumentation[$component] = $data;
            }
        }

        // Sub-components (documented via parent)
        if (! empty($subComponents)) {
            $this->info('Sub-components (documented via parent):');
            $this->newLine();

            foreach ($subComponents as $component => $data) {
                $parent = $data['parent'];
                $usageCount = count($data['usages']);
                $pages = array_column($data['usages'], 'page');

                $this->comment("  flux:{$component}");
                $this->line("    → see: {$parent}");
                $this->line('    → used in: '.implode(', ', array_unique($pages)));
            }

            $this->newLine();
        }

        // Components without any documentation
        if (! empty($noDocumentation)) {
            $this->info('Components in examples only (no dedicated docs):');
            $this->newLine();

            foreach ($noDocumentation as $component => $data) {
                $usageCount = count($data['usages']);
                $pages = array_column($data['usages'], 'page');

                $this->comment("  flux:{$component}");
                $this->line('    → used in: '.implode(', ', array_unique($pages)));
            }

            $this->newLine();
        }

        // Summary
        $totalSub = count($subComponents);
        $totalUndoc = count($noDocumentation);

        $this->line('Summary:');
        $this->line("  Sub-components: {$totalSub}");
        $this->line("  Undocumented: {$totalUndoc}");
        $this->newLine();

        $this->line('Use "fluxui-docs usages <component>" to see detailed usage.');
    }
}
