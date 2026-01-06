<?php

namespace App\Commands;

use App\Services\Analytics;
use App\Services\DocRepository;
use Fgilio\AgentSkillFoundation\Console\AgentCommand;
use LaravelZero\Framework\Commands\Command;

/**
 * Displays full documentation for a Flux UI item.
 *
 * Renders markdown-style output with sections, code examples,
 * and reference tables for props, slots, and attributes.
 */
class ShowCommand extends Command
{
    use AgentCommand;
    protected $signature = 'show
        {name : Component, layout, or guide name}
        {--section= : Show specific section only}
        {--json : Output raw JSON}';

    protected $description = 'Show documentation for a Flux UI item';

    public function handle(DocRepository $repo, Analytics $analytics): int
    {
        $startTime = microtime(true);
        $name = $this->argument('name');
        $doc = $repo->find($name);

        if (! $doc) {
            $suggestions = $repo->suggest($name, 5);
            $analytics->track('show', self::FAILURE, ['item' => $name, 'found' => false], $startTime);

            if ($this->wantsJson()) {
                return $this->jsonError("Not found: {$name}", [
                    'suggestions' => $suggestions,
                ]);
            }

            $this->error("Not found: {$name}");
            $this->newLine();

            if (! empty($suggestions)) {
                $this->line('Did you mean:');
                foreach ($suggestions as $suggestion) {
                    $this->line("  - {$suggestion}");
                }
            }

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $analytics->track('show', self::SUCCESS, ['item' => $name, 'found' => true], $startTime);

            return self::SUCCESS;
        }

        $this->renderDoc($doc, $this->option('section'));
        $analytics->track('show', self::SUCCESS, ['item' => $name, 'found' => true], $startTime);

        return self::SUCCESS;
    }

    /**
     * Render documentation in a readable format.
     */
    private function renderDoc(array $doc, ?string $section): void
    {
        // Title and description
        $pro = ($doc['pro'] ?? false) ? ' [Pro]' : '';
        $this->info("# {$doc['title']}{$pro}");

        if (! empty($doc['description'])) {
            $this->line($doc['description']);
        }

        if (! empty($doc['url'])) {
            $this->line("Source: {$doc['url']}");
        }

        $this->newLine();

        // Sections
        foreach ($doc['sections'] ?? [] as $s) {
            if ($section && strtolower($s['title']) !== strtolower($section)) {
                continue;
            }

            $this->comment("## {$s['title']}");

            if (! empty($s['content'])) {
                $this->line($s['content']);
            }

            foreach ($s['examples'] ?? [] as $example) {
                $this->newLine();
                $this->line('```blade');
                $this->line($example);
                $this->line('```');
            }

            $this->newLine();
        }

        // Reference section
        if (! empty($doc['reference']) && (! $section || strtolower($section) === 'reference')) {
            $this->renderReference($doc['reference']);
        }

        // Related components
        if (! empty($doc['related']) && ! $section) {
            $this->comment('## Related');
            $this->line(implode(', ', $doc['related']));
            $this->newLine();
        }
    }

    /**
     * Render the reference section with props, slots, and attributes.
     */
    private function renderReference(array $reference): void
    {
        $this->comment('## Reference');
        $this->newLine();

        foreach ($reference as $componentName => $data) {
            $this->info("### {$componentName}");
            $this->newLine();

            // Props table
            if (! empty($data['props'])) {
                $this->line('**Props:**');
                $tableData = array_map(function ($prop) {
                    return [
                        $prop['name'] ?? '',
                        $prop['type'] ?? '',
                        $prop['default'] ?? '-',
                        mb_substr($prop['description'] ?? '', 0, 40),
                    ];
                }, $data['props']);
                $this->table(['Prop', 'Type', 'Default', 'Description'], $tableData);
                $this->newLine();
            }

            // Slots table
            if (! empty($data['slots'])) {
                $this->line('**Slots:**');
                $tableData = array_map(function ($slot) {
                    return [
                        $slot['name'] ?? '',
                        $slot['description'] ?? '',
                    ];
                }, $data['slots']);
                $this->table(['Slot', 'Description'], $tableData);
                $this->newLine();
            }

            // Attributes table
            if (! empty($data['attributes'])) {
                $this->line('**Data Attributes:**');
                $tableData = array_map(function ($attr) {
                    return [
                        $attr['name'] ?? '',
                        $attr['description'] ?? '',
                    ];
                }, $data['attributes']);
                $this->table(['Attribute', 'Description'], $tableData);
                $this->newLine();
            }
        }
    }
}
