<?php

namespace App\Services;

/**
 * Fuzzy search implementation for Flux UI documentation.
 *
 * Provides relevance-ranked search results with scoring
 * based on name, title, description, and keyword matches.
 */
class SearchIndex
{
    public function __construct(
        private DocRepository $repository
    ) {}

    /**
     * Search documentation by query string.
     *
     * Returns ranked results with relevance scoring and match context.
     */
    public function search(string $query, int $limit = 10): array
    {
        $index = $this->repository->loadIndex();

        if (! $index || empty($index['items'])) {
            return [];
        }

        $query = strtolower(trim($query));
        $results = [];

        foreach ($index['items'] as $item) {
            $scoreData = $this->calculateScore($query, $item);

            if ($scoreData['score'] > 0) {
                $results[] = array_merge($item, $scoreData);
            }
        }

        // Sort by score descending
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Calculate relevance score for an item against a query.
     *
     * Returns score and match context for display.
     */
    private function calculateScore(string $query, array $item): array
    {
        $score = 0;
        $matchSource = null;
        $name = strtolower($item['name'] ?? '');
        $title = strtolower($item['title'] ?? '');
        $description = strtolower($item['description'] ?? '');
        $keywords = array_map('strtolower', $item['keywords'] ?? []);

        // Exact name match - highest priority
        if ($name === $query) {
            return ['score' => 100, 'match_source' => 'name'];
        }

        // Exact title match
        if ($title === $query) {
            return ['score' => 90, 'match_source' => 'title'];
        }

        // Name starts with query
        if (str_starts_with($name, $query)) {
            $score += 70;
            $matchSource = 'name';
        }
        // Name contains query
        elseif (str_contains($name, $query)) {
            $score += 50;
            $matchSource = 'name';
        }

        // Title starts with query
        if (str_starts_with($title, $query)) {
            $score += 40;
            $matchSource ??= 'title';
        }
        // Title contains query
        elseif (str_contains($title, $query)) {
            $score += 30;
            $matchSource ??= 'title';
        }

        // Description contains query
        if (str_contains($description, $query)) {
            $score += 20;
            $matchSource ??= 'description';
        }

        // Keyword matches - track if match is from components_used
        foreach ($keywords as $keyword) {
            $isComponentUsage = $this->isComponentUsageKeyword($query, $keyword);

            if ($keyword === $query) {
                $score += 15;
                if ($isComponentUsage && ! $matchSource) {
                    $matchSource = 'examples';
                }
            } elseif (str_contains($keyword, $query)) {
                $score += 10;
                if ($isComponentUsage && ! $matchSource) {
                    $matchSource = 'examples';
                }
            }
        }

        // Fuzzy match on name using Levenshtein
        $distance = levenshtein($query, $name);
        if ($distance <= 2 && $distance > 0) {
            $score += max(0, 25 - ($distance * 10));
            $matchSource ??= 'fuzzy';
        }

        return [
            'score' => $score,
            'match_source' => $matchSource ?? 'keyword',
            'matched_query' => $query,
        ];
    }

    /**
     * Check if a keyword match is likely from components_used.
     *
     * Component usage keywords follow flux component naming patterns.
     */
    private function isComponentUsageKeyword(string $query, string $keyword): bool
    {
        // Component names are lowercase, may contain dots (sub-components) or hyphens
        $componentPattern = '/^[a-z][a-z0-9.-]*$/';

        return preg_match($componentPattern, $keyword)
            && preg_match($componentPattern, $query)
            && ($keyword === $query || str_contains($keyword, $query));
    }
}
