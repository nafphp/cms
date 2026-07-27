<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Ai\ToolRouting;

use NixPHP\CMS\Services\Ai\ToolRouting\ToolContext;
use NixPHP\CMS\Services\Ai\ToolRouting\ToolHardFilter;
use NixPHP\CMS\Services\Ai\ToolRouting\ToolRoute;
use NixPHP\CMS\Services\Ai\ToolRouting\ToolRouterInterface;

final class KeywordToolRouter implements ToolRouterInterface
{
    /**
     * @param array<int, array<string, mixed>> $tools
     */
    public function __construct(
        private array $tools,
        private ToolHardFilter $hardFilter = new ToolHardFilter(),
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function select(string $input, ToolContext $context, int $limit = 8): array
    {
        $limit = max(1, min(32, $limit));
        $input = trim($input);

        if ($input === '' || $this->wantsCatalog($input)) {
            return array_slice($this->tools, 0, $limit);
        }

        $candidates = $this->hardFilter->filter($this->tools, $input, $context);
        $query = strtolower($this->queryText($input, $context));
        $queryTokens = $this->tokens($query);
        $writeIntent = $this->hasWriteIntent($query);
        $routes = [];

        foreach ($candidates as $tool) {
            $route = $this->route($tool, $query, $queryTokens, $context, $writeIntent);
            if ($route->score > 0.0) {
                $routes[] = $route;
            }
        }

        usort($routes, static fn(ToolRoute $left, ToolRoute $right): int => $right->score <=> $left->score);

        $selected = array_map(static fn(ToolRoute $route): array => [
            ...$route->tool,
            'route' => [
                'score' => $route->score,
                'reasons' => $route->reasons,
            ],
        ], array_slice($routes, 0, $limit));

        if ($selected !== []) {
            return $this->withSearchTool($selected, $candidates, $limit);
        }

        return $this->searchOnly($candidates, 'no-match-search');
    }

    /**
     * @param array<int, array<string, mixed>> $selected
     * @param array<int, array<string, mixed>> $candidates
     * @return array<int, array<string, mixed>>
     */
    private function withSearchTool(array $selected, array $candidates, int $limit): array
    {
        if ($limit < 4 || count($selected) >= $limit) {
            return $selected;
        }

        if (in_array('cms_tools_search', array_column($selected, 'name'), true)) {
            return $selected;
        }

        foreach ($candidates as $candidate) {
            if (($candidate['name'] ?? '') !== 'cms_tools_search') {
                continue;
            }

            $selected[] = [
                ...$candidate,
                'route' => [
                    'score' => 0.1,
                    'reasons' => ['fallback-search'],
                ],
            ];
            break;
        }

        return $selected;
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @return array<int, array<string, mixed>>
     */
    private function searchOnly(array $candidates, string $reason): array
    {
        foreach ($candidates as $candidate) {
            if (($candidate['name'] ?? '') !== 'cms_tools_search') {
                continue;
            }

            return [[
                ...$candidate,
                'route' => [
                    'score' => 0.1,
                    'reasons' => [$reason],
                ],
            ]];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $tool
     * @param array<int, string> $queryTokens
     */
    private function route(array $tool, string $query, array $queryTokens, ToolContext $context, bool $writeIntent): ToolRoute
    {
        $meta = $this->meta($tool);
        $toolText = strtolower(trim(implode(' ', [
            (string) ($tool['name'] ?? ''),
            (string) ($tool['description'] ?? ''),
            (string) ($meta['routingText'] ?? ''),
            implode(' ', is_array($meta['examples'] ?? null) ? $meta['examples'] : []),
            implode(' ', is_array($meta['tags'] ?? null) ? $meta['tags'] : []),
            implode(' ', $this->capabilities($meta)),
        ])));

        $score = 0.0;
        $reasons = [];

        $tokenScore = $this->tokenScore($queryTokens, $toolText);
        if ($tokenScore > 0.0) {
            $score += $tokenScore;
            $reasons[] = 'tokens';
        }

        $areaScore = $this->areaScore($context, $meta, $toolText);
        if ($areaScore > 0.0) {
            $score += $areaScore;
            $reasons[] = 'area';
        }

        $capabilityScore = $this->capabilityScore($query, $meta);
        if ($capabilityScore > 0.0) {
            $score += $capabilityScore;
            $reasons[] = 'capability';
        }

        if ($writeIntent && ($meta['mode'] ?? '') === 'write') {
            $score += 6.0;
            $reasons[] = 'write';
        }

        if ($this->matchesExamples($query, is_array($meta['examples'] ?? null) ? $meta['examples'] : [])) {
            $score += 4.0;
            $reasons[] = 'examples';
        }

        return new ToolRoute($tool, $score, array_values(array_unique($reasons)));
    }

    private function queryText(string $message, ToolContext $context): string
    {
        $parts = [$message, $context->area, $context->pageId, $context->layoutId, $context->themeId, $context->contentType];
        foreach (['selectedRegion', 'insertAfterBlockId', 'theme'] as $key) {
            if (isset($context->uiContext[$key]) && is_scalar($context->uiContext[$key])) {
                $parts[] = (string) $context->uiContext[$key];
            }
        }
        if (isset($context->uiContext['availableBlockModules']) && is_array($context->uiContext['availableBlockModules'])) {
            $parts[] = implode(' ', array_filter($context->uiContext['availableBlockModules'], 'is_scalar'));
        }

        return implode(' ', array_filter($parts, static fn(mixed $part): bool => is_scalar($part) && trim((string) $part) !== ''));
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $text): array
    {
        $words = preg_split('/[^a-zA-Z0-9\x{00C0}-\x{017F}]+/u', strtolower($text)) ?: [];
        $stop = array_flip([
            'und', 'oder', 'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem',
            'ist', 'sind', 'war', 'wie', 'was', 'wer', 'wen', 'wem', 'wann', 'wo', 'mir', 'mal',
            'dazu', 'etwas', 'bitte', 'hier', 'mit', 'ohne', 'für', 'fuer', 'kann', 'kannst',
            'with', 'the', 'for', 'and', 'this', 'that',
        ]);

        return array_values(array_unique(array_filter(
            $words,
            static fn(string $word): bool => mb_strlen($word) >= 3 && !isset($stop[$word])
        )));
    }

    /**
     * @param array<int, string> $tokens
     */
    private function tokenScore(array $tokens, string $toolText): float
    {
        $score = 0.0;
        foreach ($tokens as $token) {
            if (str_contains($toolText, $token)) {
                $score += 1.0;
            }
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function areaScore(ToolContext $context, array $meta, string $toolText): float
    {
        $area = $context->normalizedArea();
        if ($area === '') {
            return 0.0;
        }

        $capabilities = $this->capabilities($meta);

        return match ($area) {
            'pagebuilder' => $this->hasAny($capabilities, ['layout.read', 'layout.write', 'block.create', 'block.update', 'pages.read', 'pages.write']) || str_contains($toolText, 'page') ? 3.0 : 0.0,
            'themeeditor' => $this->hasAny($capabilities, ['theme.read', 'theme.write', 'themes.write']) || str_contains($toolText, 'theme') ? 3.0 : 0.0,
            'articleeditor' => $this->hasAny($capabilities, ['content.read', 'content.write', 'articles.write']) || str_contains($toolText, 'article') ? 3.0 : 0.0,
            'formeditor', 'formbuilder' => $this->hasAny($capabilities, ['content.read', 'content.write', 'forms.write']) || str_contains($toolText, 'form') ? 3.0 : 0.0,
            default => 0.0,
        };
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function capabilityScore(string $query, array $meta): float
    {
        $capabilities = $this->capabilities($meta);
        $score = 0.0;

        $map = [
            'block.create' => ['block', 'textblock', 'bildblock', 'füge', 'fuege', 'add'],
            'block.update' => ['block', 'ändere', 'aendere', 'update'],
            'layout.write' => ['layout', 'slot', 'region', 'verschiebe'],
            'pages.read' => ['pagebuilder', 'builder', 'zeigemodus', 'zeige', 'klick', 'anklicken', 'inspect', 'beschreibe'],
            'theme.write' => ['theme', 'farbe', 'color', 'css', 'hintergrund'],
            'content.read' => ['artikel', 'article', 'inhalt', 'content', 'draft', 'entwurf'],
            'content.write' => ['erstelle', 'create', 'append', 'ergänze', 'aendere', 'ändere', 'draft'],
            'articles.write' => ['artikel', 'article', 'draft', 'entwurf'],
            'media.read' => ['media', 'bild', 'image', 'datei'],
            'system.read' => ['system', 'status', 'queue', 'health', 'plugin'],
            'external.fetch' => ['wetter', 'weather', 'trend', 'holiday', 'wechselkurs', 'news'],
            'admin.navigate' => ['navigiere', 'navigate', 'gehe', 'öffne', 'oeffne', 'open', 'pagebuilder', 'builder', 'editor'],
        ];

        foreach ($map as $capability => $needles) {
            if (!$this->hasAny($capabilities, [$capability])) {
                continue;
            }

            foreach ($needles as $needle) {
                if (str_contains($query, $needle)) {
                    $score += 2.0;
                    break;
                }
            }
        }

        return $score;
    }

    /**
     * @param array<int, mixed> $examples
     */
    private function matchesExamples(string $query, array $examples): bool
    {
        $queryTokens = $this->tokens($query);
        foreach ($examples as $example) {
            $exampleText = strtolower((string) $example);
            $hits = 0;
            foreach ($queryTokens as $token) {
                if (str_contains($exampleText, $token)) {
                    $hits++;
                }
            }
            if ($hits >= 2) {
                return true;
            }
        }

        return false;
    }

    private function hasWriteIntent(string $query): bool
    {
        foreach (['create', 'add', 'append', 'write', 'update', 'delete', 'publish', 'commit', 'erstelle', 'füge', 'fuege', 'ergänze', 'aendere', 'ändere', 'speichere', 'loesche', 'lösche'] as $needle) {
            if (str_contains($query, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function wantsCatalog(string $message): bool
    {
        $message = strtolower($message);

        foreach (['welche tools', 'alle tools', 'tool liste', 'available tools', 'list tools'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $tool
     * @return array<string, mixed>
     */
    private function meta(array $tool): array
    {
        return isset($tool['meta']) && is_array($tool['meta']) ? $tool['meta'] : [];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<int, string>
     */
    private function capabilities(array $meta): array
    {
        return is_array($meta['capabilities'] ?? null) ? array_values(array_map('strval', $meta['capabilities'])) : [];
    }

    /**
     * @param array<int, mixed> $haystack
     * @param array<int, string> $needles
     */
    private function hasAny(array $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $haystack, true)) {
                return true;
            }
        }

        return false;
    }
}
