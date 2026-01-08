<?php

declare(strict_types=1);

namespace App\Services;

use Phar;

/**
 * Repository for reading and writing Flux UI documentation JSON files.
 *
 * Handles data directory resolution relative to the binary location,
 * supporting both development (src/) and production (skill root) contexts.
 */
class DocRepository
{
    private string $dataPath;

    public function __construct()
    {
        $this->dataPath = $this->resolveDataPath();
    }

    /**
     * List all documentation items, optionally filtered by category.
     */
    public function list(?string $category = null): array
    {
        $items = [];

        $categories = $category
            ? [$category]
            : ['components', 'layouts', 'guides'];

        foreach ($categories as $cat) {
            $items[$cat] = $this->listCategory($cat);
        }

        return $items;
    }

    /**
     * Find a documentation item by name.
     *
     * Searches all categories if not specified.
     */
    public function find(string $name, ?string $category = null): ?array
    {
        $categories = $category
            ? [$category]
            : ['components', 'layouts', 'guides'];

        foreach ($categories as $cat) {
            $path = "{$this->dataPath}/{$cat}/{$name}.json";

            if (file_exists($path)) {
                $content = file_get_contents($path);

                return json_decode($content, true);
            }
        }

        return null;
    }

    /**
     * Save a documentation item.
     */
    public function save(string $category, string $name, array $data): void
    {
        $dir = "{$this->dataPath}/{$category}";

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$name}.json";
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($path, $json."\n");
    }

    /**
     * Get suggestions for a name that wasn't found.
     */
    public function suggest(string $name, int $limit = 5): array
    {
        $all = $this->getAllNames();
        $suggestions = [];

        foreach ($all as $item) {
            $distance = levenshtein(mb_strtolower($name), mb_strtolower($item));
            $suggestions[$item] = $distance;
        }

        asort($suggestions);

        return array_slice(array_keys($suggestions), 0, $limit);
    }

    /**
     * Get all item names across all categories.
     */
    public function getAllNames(): array
    {
        $names = [];

        foreach (['components', 'layouts', 'guides'] as $category) {
            $names = array_merge($names, $this->listCategory($category));
        }

        return array_unique($names);
    }

    /**
     * Load the search index.
     */
    public function loadIndex(): ?array
    {
        $path = "{$this->dataPath}/index.json";

        if (! file_exists($path)) {
            return null;
        }

        return json_decode(file_get_contents($path), true);
    }

    /**
     * Save the search index.
     */
    public function saveIndex(array $index): void
    {
        $path = "{$this->dataPath}/index.json";
        $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($path, $json."\n");
    }

    /**
     * Rebuild the search index from all documentation files.
     */
    public function rebuildIndex(): array
    {
        $items = [];

        foreach (['components', 'layouts', 'guides'] as $category) {
            foreach ($this->listCategory($category) as $name) {
                $doc = $this->find($name, $category);

                if ($doc) {
                    $items[] = [
                        'name' => $doc['name'] ?? $name,
                        'title' => $doc['title'] ?? ucfirst($name),
                        'description' => $doc['description'] ?? '',
                        'category' => $category,
                        'pro' => $doc['pro'] ?? false,
                        'keywords' => $this->extractKeywords($doc),
                    ];
                }
            }
        }

        $index = [
            'version' => '1.0',
            'updated_at' => date('c'),
            'items' => $items,
        ];

        $this->saveIndex($index);

        return $index;
    }

    /**
     * Get the data path for external use.
     */
    public function getDataPath(): string
    {
        return $this->dataPath;
    }

    /**
     * Load the component usages index.
     */
    public function loadUsages(): ?array
    {
        $path = "{$this->dataPath}/usages.json";

        if (! file_exists($path)) {
            return null;
        }

        return json_decode(file_get_contents($path), true);
    }

    /**
     * Save the component usages index.
     */
    public function saveUsages(array $usages): void
    {
        $path = "{$this->dataPath}/usages.json";
        $json = json_encode($usages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($path, $json."\n");
    }

    /**
     * Rebuild the component usages index from all documentation files.
     *
     * Creates a reverse index: component -> pages where it's used in examples.
     */
    public function rebuildUsages(): array
    {
        $usages = [];

        foreach (['components', 'layouts', 'guides'] as $category) {
            foreach ($this->listCategory($category) as $name) {
                $doc = $this->find($name, $category);

                if (! $doc || empty($doc['components_used'])) {
                    continue;
                }

                // Build reverse index: which pages use each component
                foreach ($doc['components_used'] as $component) {
                    if (! isset($usages[$component])) {
                        $usages[$component] = [];
                    }

                    // Find which sections contain this component
                    $sectionsWithComponent = [];
                    foreach ($doc['sections'] ?? [] as $section) {
                        foreach ($section['examples'] ?? [] as $example) {
                            if (mb_stripos($example, "<flux:{$component}") !== false) {
                                $sectionsWithComponent[] = $section['title'];
                            }
                        }
                    }

                    $usages[$component][] = [
                        'page' => $name,
                        'category' => $category,
                        'sections' => array_values(array_unique($sectionsWithComponent)),
                    ];
                }
            }
        }

        // Sort by component name
        ksort($usages);

        $index = [
            'version' => '1.0',
            'updated_at' => date('c'),
            'usages' => $usages,
        ];

        $this->saveUsages($index);

        return $index;
    }

    /**
     * Get all documented component names (those with their own pages).
     */
    public function getDocumentedComponents(): array
    {
        return $this->listCategory('components');
    }

    /**
     * Find undocumented components (in examples but no dedicated page).
     */
    public function findUndocumentedComponents(): array
    {
        $documented = $this->getDocumentedComponents();
        $usages = $this->loadUsages();

        if (! $usages || empty($usages['usages'])) {
            return [];
        }

        $undocumented = [];

        foreach ($usages['usages'] as $component => $pages) {
            // Check if component has its own documentation page
            $baseName = explode('.', $component)[0]; // modal.close -> modal

            if (! in_array($component, $documented) && ! in_array($baseName, $documented)) {
                // Completely undocumented
                $undocumented[$component] = [
                    'type' => 'undocumented',
                    'usages' => $pages,
                ];
            } elseif (str_contains($component, '.') && in_array($baseName, $documented)) {
                // Sub-component of a documented parent
                $undocumented[$component] = [
                    'type' => 'sub_component',
                    'parent' => $baseName,
                    'usages' => $pages,
                ];
            }
        }

        ksort($undocumented);

        return $undocumented;
    }

    /**
     * Resolve the data directory path relative to the binary.
     */
    private function resolveDataPath(): string
    {
        // When running from PHAR, look for sibling data/ directory
        if (Phar::running()) {
            $pharPath = Phar::running(false);

            return dirname($pharPath).'/data';
        }

        // Development: go up from src/ to skill root
        return dirname(__DIR__, 3).'/data';
    }

    /**
     * List all items in a category.
     */
    private function listCategory(string $category): array
    {
        $path = "{$this->dataPath}/{$category}";

        if (! is_dir($path)) {
            return [];
        }

        $files = glob("{$path}/*.json");
        $names = [];

        foreach ($files as $file) {
            $names[] = basename($file, '.json');
        }

        sort($names);

        return $names;
    }

    /**
     * Extract searchable keywords from a doc.
     */
    private function extractKeywords(array $doc): array
    {
        $keywords = [];

        // Add title words
        if (! empty($doc['title'])) {
            $keywords = array_merge($keywords, explode(' ', mb_strtolower($doc['title'])));
        }

        // Add related components
        if (! empty($doc['related'])) {
            $keywords = array_merge($keywords, $doc['related']);
        }

        // Add section titles
        foreach ($doc['sections'] ?? [] as $section) {
            if (! empty($section['title'])) {
                $keywords[] = mb_strtolower($section['title']);
            }
        }

        // Add prop names from reference
        foreach ($doc['reference'] ?? [] as $component => $ref) {
            foreach ($ref['props'] ?? [] as $prop) {
                if (! empty($prop['name'])) {
                    $keywords[] = mb_strtolower($prop['name']);
                }
            }
        }

        // Add components used in examples (enables finding pages by component usage)
        if (! empty($doc['components_used'])) {
            $keywords = array_merge($keywords, $doc['components_used']);
        }

        // Add sub-components
        if (! empty($doc['sub_components'])) {
            $keywords = array_merge($keywords, $doc['sub_components']);
        }

        return array_values(array_unique($keywords));
    }
}
