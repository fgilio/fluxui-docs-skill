<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Scraper for Flux UI documentation from fluxui.dev.
 *
 * Parses HTML pages to extract structured documentation
 * including sections, code examples, and reference tables.
 */
class Scraper
{
    private Client $client;

    private string $baseUrl = 'https://fluxui.dev';

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'FluxUI-CLI/1.0 (Documentation Scraper)',
                'Accept' => 'text/html,application/xhtml+xml',
            ],
        ]);
    }

    /**
     * Discover all documentation items from the navigation.
     */
    public function discoverAll(): array
    {
        $items = [];

        try {
            $response = $this->client->get('/docs');
            $html = (string) $response->getBody();
            $crawler = new Crawler($html);

            // Find navigation links for components
            $crawler->filter('a[href^="/components/"]')->each(function (Crawler $link) use (&$items) {
                $href = $link->attr('href');
                if (preg_match('/\/components\/([^\/\?#]+)/', $href, $matches)) {
                    $name = $matches[1];
                    if (! $this->isDuplicate($items, $name, 'component')) {
                        $items[] = ['name' => $name, 'category' => 'component'];
                    }
                }
            });

            // Find navigation links for layouts
            $crawler->filter('a[href^="/layouts/"]')->each(function (Crawler $link) use (&$items) {
                $href = $link->attr('href');
                if (preg_match('/\/layouts\/([^\/\?#]+)/', $href, $matches)) {
                    $name = $matches[1];
                    if (! $this->isDuplicate($items, $name, 'layout')) {
                        $items[] = ['name' => $name, 'category' => 'layout'];
                    }
                }
            });

            // Find navigation links for guides
            $crawler->filter('a[href^="/docs/"]')->each(function (Crawler $link) use (&$items) {
                $href = $link->attr('href');
                if (preg_match('/\/docs\/([^\/\?#]+)/', $href, $matches)) {
                    $name = $matches[1];
                    // Skip if it's the main docs page
                    if ($name && $name !== 'docs' && ! $this->isDuplicate($items, $name, 'guide')) {
                        $items[] = ['name' => $name, 'category' => 'guide'];
                    }
                }
            });

        } catch (GuzzleException $e) {
            // Return empty on error
        }

        return $items;
    }

    /**
     * Scrape a single documentation page.
     */
    public function scrape(string $category, string $name): ?array
    {
        $url = $this->buildUrl($category, $name);

        try {
            $response = $this->client->get($url);
            $html = (string) $response->getBody();
        } catch (GuzzleException $e) {
            return null;
        }

        $crawler = new Crawler($html);

        $sections = $this->extractSections($crawler);
        $componentsUsed = $this->extractComponentsFromExamples($sections);
        $subComponents = $this->extractSubComponents($name, $componentsUsed);

        return [
            'name' => $name,
            'title' => $this->extractTitle($crawler),
            'description' => $this->extractDescription($crawler),
            'category' => $category,
            'url' => $this->baseUrl.$url,
            'pro' => $this->detectPro($crawler),
            'sections' => $sections,
            'reference' => $this->extractReference($crawler),
            'related' => $this->extractRelated($crawler),
            'components_used' => $componentsUsed,
            'sub_components' => $subComponents,
            'scraped_at' => date('c'),
        ];
    }

    /**
     * Check if an item already exists in the list.
     */
    private function isDuplicate(array $items, string $name, string $category): bool
    {
        foreach ($items as $item) {
            if ($item['name'] === $name && $item['category'] === $category) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the URL for a documentation page.
     */
    private function buildUrl(string $category, string $name): string
    {
        return match ($category) {
            'component' => "/components/{$name}",
            'layout' => "/layouts/{$name}",
            'guide' => "/docs/{$name}",
            default => throw new InvalidArgumentException("Unknown category: {$category}"),
        };
    }

    /**
     * Extract the page title from h1.
     */
    private function extractTitle(Crawler $crawler): string
    {
        $h1 = $crawler->filter('h1')->first();

        return $h1->count() > 0 ? trim($h1->text()) : '';
    }

    /**
     * Extract the page description from the first paragraph.
     */
    private function extractDescription(Crawler $crawler): string
    {
        // Try to find the intro paragraph (usually after h1 or in a specific container)
        $selectors = [
            'main p:first-of-type',
            'article p:first-of-type',
            '.prose p:first-of-type',
            'h1 + p',
        ];

        foreach ($selectors as $selector) {
            $p = $crawler->filter($selector)->first();
            if ($p->count() > 0) {
                $text = trim($p->text());
                if (mb_strlen($text) > 20) {
                    return $text;
                }
            }
        }

        return '';
    }

    /**
     * Detect if this is a Pro-only component.
     */
    private function detectPro(Crawler $crawler): bool
    {
        $html = $crawler->html();

        // Check for Pro badge or indicator
        $proIndicators = [
            'data-pro',
            'pro-badge',
            'Pro only',
            'requires Pro',
            'Flux Pro',
        ];

        foreach ($proIndicators as $indicator) {
            if (mb_stripos($html, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract sections from h2 and h3 headings.
     *
     * Uses parent container approach to handle both flat and nested DOM structures.
     * Includes h3 because some sites use h3 for subsections (e.g., "Floating flyout").
     */
    private function extractSections(Crawler $crawler): array
    {
        $sections = [];

        // Find h2 and h3 headings (some sites use h3 for subsections)
        $crawler->filter('h2, h3')->each(function (Crawler $heading) use (&$sections) {
            $title = trim($heading->text());
            $node = $heading->getNode(0);

            // Skip Reference section - handled separately
            if (mb_strtolower($title) === 'reference') {
                return;
            }

            // Skip h3s that are component names in Reference section (e.g., "flux:modal")
            if ($node && $node->nodeName === 'h3' && str_starts_with(mb_strtolower($title), 'flux:')) {
                return;
            }

            $section = [
                'title' => $title,
                'content' => '',
                'examples' => [],
            ];

            if (! $node) {
                return;
            }

            // Strategy 1: Check parent container for content
            // Many sites wrap sections in divs/sections containing heading + content
            $parent = $node->parentNode;
            if ($parent && $parent->nodeName !== 'body' && $parent->nodeName !== 'main') {
                $parentCrawler = new Crawler($parent);

                // Only use parent if it contains exactly one heading of this type
                $headingCount = $parentCrawler->filter($node->nodeName)->count();
                if ($headingCount === 1) {
                    $this->extractSectionContent($parentCrawler, $section);
                    $section['content'] = trim($section['content']);
                    $sections[] = $section;

                    return;
                }
            }

            // Strategy 2: Fall back to sibling traversal for flat structures
            $sibling = $node->nextSibling;
            while ($sibling) {
                // Stop at next heading of same or higher level
                if (in_array($sibling->nodeName, ['h2', 'h3'])) {
                    break;
                }

                if ($sibling->nodeType === XML_ELEMENT_NODE) {
                    $siblingCrawler = new Crawler($sibling);
                    $this->extractSectionContent($siblingCrawler, $section);
                }

                $sibling = $sibling->nextSibling;
            }

            $section['content'] = trim($section['content']);
            $sections[] = $section;
        });

        return $sections;
    }

    /**
     * Extract content and code examples from a crawler node.
     */
    private function extractSectionContent(Crawler $crawler, array &$section): void
    {
        // Extract paragraphs
        $crawler->filter('p')->each(function (Crawler $p) use (&$section) {
            $text = trim($p->text());
            if ($text && mb_strlen($text) > 5) {
                $section['content'] .= $text."\n";
            }
        });

        // Extract code blocks
        $crawler->filter('pre code, pre')->each(function (Crawler $code) use (&$section) {
            $codeText = trim($code->text());
            if ($codeText && ! in_array($codeText, $section['examples'])) {
                $section['examples'][] = $codeText;
            }
        });
    }

    /**
     * Extract the Reference section with props, slots, and attributes.
     */
    private function extractReference(Crawler $crawler): array
    {
        $reference = [];

        // Find h2 with "Reference" text
        $refH2 = $crawler->filter('h2')->reduce(function (Crawler $node) {
            return mb_strtolower(trim($node->text())) === 'reference';
        })->first();

        if ($refH2->count() === 0) {
            return $reference;
        }

        // Find h3 headings after Reference (component names like flux:button)
        $node = $refH2->getNode(0);
        if (! $node) {
            return $reference;
        }

        $sibling = $node->nextSibling;
        $currentComponent = null;

        while ($sibling) {
            if ($sibling->nodeName === 'h2') {
                break; // Stop at next h2
            }

            if ($sibling->nodeType === XML_ELEMENT_NODE) {
                $siblingCrawler = new Crawler($sibling);

                // h3 = component name
                if ($sibling->nodeName === 'h3') {
                    $currentComponent = trim($siblingCrawler->text());
                    $reference[$currentComponent] = [
                        'props' => [],
                        'slots' => [],
                        'attributes' => [],
                    ];
                }

                // table = props/slots/attributes
                if ($sibling->nodeName === 'table' && $currentComponent) {
                    $this->parseReferenceTable($siblingCrawler, $reference[$currentComponent]);
                }
            }

            $sibling = $sibling->nextSibling;
        }

        return $reference;
    }

    /**
     * Parse a reference table for props, slots, or attributes.
     */
    private function parseReferenceTable(Crawler $table, array &$ref): void
    {
        $headers = [];
        $table->filter('thead th, thead td')->each(function (Crawler $th) use (&$headers) {
            $headers[] = mb_strtolower(trim($th->text()));
        });

        // Determine table type from headers
        $isPropTable = in_array('prop', $headers) || in_array('type', $headers) || in_array('default', $headers);
        $isSlotTable = in_array('slot', $headers);
        $isAttrTable = in_array('attribute', $headers) || in_array('data attribute', $headers);

        $table->filter('tbody tr')->each(function (Crawler $row) use (&$ref, $isPropTable, $isSlotTable, $isAttrTable) {
            $cells = [];
            $row->filter('td')->each(function (Crawler $td) use (&$cells) {
                $cells[] = trim($td->text());
            });

            if (count($cells) < 2) {
                return;
            }

            if ($isPropTable) {
                $ref['props'][] = [
                    'name' => $cells[0] ?? '',
                    'type' => $cells[1] ?? '',
                    'default' => $cells[2] ?? '',
                    'description' => $cells[3] ?? '',
                ];
            } elseif ($isSlotTable) {
                $ref['slots'][] = [
                    'name' => $cells[0] ?? '',
                    'description' => $cells[1] ?? '',
                ];
            } elseif ($isAttrTable) {
                $ref['attributes'][] = [
                    'name' => $cells[0] ?? '',
                    'description' => $cells[1] ?? '',
                ];
            }
        });
    }

    /**
     * Extract related components.
     */
    private function extractRelated(Crawler $crawler): array
    {
        $related = [];

        // Look for "Related" section or links to other components
        $crawler->filter('a[href^="/components/"]')->each(function (Crawler $a) use (&$related) {
            $href = $a->attr('href');
            if (preg_match('/\/components\/([^\/\?#]+)/', $href, $matches)) {
                $name = $matches[1];
                if (! in_array($name, $related)) {
                    $related[] = $name;
                }
            }
        });

        // Limit to reasonable number
        return array_slice($related, 0, 10);
    }

    /**
     * Extract all flux:* component names from code examples.
     */
    private function extractComponentsFromExamples(array $sections): array
    {
        $components = [];

        foreach ($sections as $section) {
            foreach ($section['examples'] ?? [] as $example) {
                // Match <flux:component-name or <flux:component.subname
                if (preg_match_all('/<flux:([a-z][a-z0-9.-]*)/i', $example, $matches)) {
                    $components = array_merge($components, $matches[1]);
                }
            }
        }

        // Remove duplicates and sort
        $unique = array_unique($components);
        sort($unique);

        return array_values($unique);
    }

    /**
     * Extract sub-components (e.g., modal.close, modal.trigger for modal).
     */
    private function extractSubComponents(string $parentName, array $componentsUsed): array
    {
        $subComponents = [];
        $prefix = $parentName.'.';

        foreach ($componentsUsed as $component) {
            if (str_starts_with($component, $prefix)) {
                $subComponents[] = $component;
            }
        }

        sort($subComponents);

        return $subComponents;
    }
}
