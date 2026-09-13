<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Ai;

final class LocalAiToolCatalog
{
    /**
     * @param array<int, array<string, mixed>> $definitions
     * @return array<int, array<string, mixed>>
     */
    public function enrich(array $definitions): array
    {
        return array_map(fn(array $definition): array => [
            ...$definition,
            'meta' => $this->metadata($definition),
        ], $definitions);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function metadata(array $definition): array
    {
        $name = strtolower(trim((string) ($definition['name'] ?? '')));
        $description = strtolower(trim((string) ($definition['description'] ?? '')));
        $text = $name . ' ' . $description;

        $provided = is_array($definition['meta'] ?? null) ? $definition['meta'] : [];
        foreach (['title', 'routingText', 'routingDescription', 'examples', 'capabilities', 'mode', 'risk', 'destructive', 'autoRun', 'category', 'source', 'scopes'] as $key) {
            if (array_key_exists($key, $definition) && !array_key_exists($key, $provided)) {
                $provided[$key] = $definition[$key];
            }
        }

        $risk = $this->resolveRisk($provided, $description, $name);

        return [
            'title' => $this->title($name),
            'category' => $this->category($text, $name, $risk),
            'source' => str_starts_with($name, 'external_') ? 'external' : 'cms',
            'risk' => $risk,
            'destructive' => $this->resolveDestructive($provided, $description, $name),
            'mode' => $this->mode($text, $name, $risk),
            'capabilities' => $this->capabilities($text, $name, $risk),
            'routingText' => trim($this->routingText($definition)),
            'examples' => $this->examples($definition, $name),
            'tags' => $this->tags($name, $description),
            ...$provided,
        ];
    }

    /** @param array<string, mixed> $metadata */
    private function resolveRisk(array $metadata, string $description, string $name): string
    {
        $declared = $metadata['risk'] ?? null;
        if (in_array($declared, ['read', 'write'], true)) {
            return $declared;
        }

        return $this->risk($description, $name);
    }

    /** @param array<string, mixed> $metadata */
    private function resolveDestructive(array $metadata, string $description, string $name): bool
    {
        if (array_key_exists('destructive', $metadata) && is_bool($metadata['destructive'])) {
            return $metadata['destructive'];
        }

        return $this->destructive($description, $name);
    }

    private function title(string $name): string
    {
        $name = preg_replace('/^(cms|external)_/', '', $name) ?? $name;
        return ucwords(str_replace('_', ' ', $name));
    }

    private function category(string $text, string $name, string $risk): string
    {
        if ($name === 'cms_tools_search') {
            return 'cms_tools';
        }

        if ($this->contains($text, ['trend', 'news', 'current topics', 'public-interest'])) {
            return 'external_trends';
        }

        if ($this->contains($text, ['weather', 'holiday', 'holidays', 'air quality', 'uv', 'pollen', 'campaign calendar'])) {
            return 'external_planning';
        }

        if ($this->contains($text, ['exchange', 'currency', 'pricing', 'rates'])) {
            return 'external_business';
        }

        if ($this->contains($text, ['audit', 'log', 'activity log', 'events'])) {
            return 'cms_audit';
        }

        if ($this->contains($text, ['navigate', 'navigation target', 'admin ui', 'admin area'])) {
            return 'cms_navigation';
        }

        if ($risk === 'write') {
            return 'cms_write';
        }

        if ($this->contains($text, ['article', 'page', 'media', 'content', 'taxonomy', 'version'])) {
            return 'cms_content';
        }

        if ($this->contains($text, ['health', 'queue', 'plugin', 'system', 'status', 'overview', 'role', 'permission', 'user'])) {
            return 'cms_status';
        }

        return str_starts_with($name, 'external_') ? 'external_other' : 'plugin';
    }

    /**
     * Write detection deliberately ignores free prose in the description.
     * Substring matching there classified read-only tools as writes — "published
     * asset status" or "publishing schedules" both contain "publish" — and the
     * hard filter then hid those tools from every non-write request.
     */
    private function risk(string $description, string $name): string
    {
        $verbs = [
            'create', 'append', 'write', 'update', 'delete', 'publish', 'archive', 'schedule', 'store', 'save',
            'remove', 'discard', 'rollback', 'purge',
        ];

        return $this->hasVerb($name, $verbs) || $this->hasLeadingVerb($description, $verbs) ? 'write' : 'read';
    }

    private function destructive(string $description, string $name): bool
    {
        $verbs = ['delete', 'discard', 'rollback', 'archive', 'remove', 'purge'];

        return $this->hasVerb($name, $verbs) || $this->hasLeadingVerb($description, $verbs);
    }

    /**
     * Matches a verb against the underscore-separated tool name.
     *
     * @param array<int, string> $verbs
     */
    private function hasVerb(string $name, array $verbs): bool
    {
        $parts = preg_split('/[^a-z0-9]+/', $name) ?: [];

        foreach ($parts as $part) {
            if (in_array($part, $verbs, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tool descriptions open with a third-person verb ("Creates ...", "Returns ...").
     * That first word is the only prose signal specific enough to trust.
     *
     * @param array<int, string> $verbs
     */
    private function hasLeadingVerb(string $text, array $verbs): bool
    {
        $first = strtolower(trim((string) (strtok(ltrim($text), " \t\n") ?: ''), ".,:;!?()[]{}"));

        foreach ($verbs as $verb) {
            if (in_array($first, [$verb, $verb . 's', $verb . 'es'], true)) {
                return true;
            }
        }

        return false;
    }

    private function mode(string $text, string $name, string $risk): string
    {
        if ($this->contains($text, ['draft_create', 'draft_append', 'create draft', 'append to an existing draft'])) {
            return 'write';
        }

        return $risk === 'write' ? 'write' : 'read';
    }

    /**
     * @return array<int, string>
     */
    private function capabilities(string $text, string $name, string $risk): array
    {
        $capabilities = [];

        if ($this->contains($text, ['article', 'draft', 'content', 'version'])) {
            $capabilities[] = 'content.read';
        }
        if ($this->contains($text, ['draft_create', 'create draft', 'draft_append', 'append'])) {
            $capabilities[] = 'content.write';
            $capabilities[] = 'articles.write';
        }
        if ($this->contains($text, ['page', 'layout', 'block'])) {
            $capabilities[] = 'layout.read';
        }
        if ($this->contains($text, ['pagebuilder', 'page builder', 'show mode', 'zeigemodus'])) {
            $capabilities[] = 'pages.read';
        }
        if ($this->contains($text, ['media', 'image', 'file'])) {
            $capabilities[] = 'media.read';
        }
        if ($this->contains($text, ['theme', 'color', 'css'])) {
            $capabilities[] = 'theme.read';
        }
        if ($this->contains($text, ['theme', 'color', 'css']) && $risk === 'write') {
            $capabilities[] = 'theme.write';
        }
        if ($this->contains($text, ['health', 'system', 'queue', 'plugin', 'status'])) {
            $capabilities[] = 'system.read';
        }
        if ($name === 'cms_tools_search' || $this->contains($text, ['tool registry', 'registered cms tools', 'tool search'])) {
            $capabilities[] = 'tools.read';
        }
        if ($this->contains($text, ['navigate', 'admin ui', 'admin area'])) {
            $capabilities[] = 'admin.navigate';
        }
        if ($this->contains($text, ['user', 'role', 'permission'])) {
            $capabilities[] = 'users.read';
        }
        if (str_starts_with($name, 'external_')) {
            $capabilities[] = 'external.fetch';
        }

        return array_values(array_unique($capabilities));
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function routingText(array $definition): string
    {
        $schema = $definition['inputSchema'] ?? [];
        $properties = is_array($schema) && isset($schema['properties']) && is_array($schema['properties'])
            ? implode(' ', array_keys($schema['properties']))
            : '';
        $examples = $definition['examples'] ?? [];
        $exampleText = is_array($examples) ? implode(' ', array_filter($examples, 'is_scalar')) : '';

        return trim(implode(' ', [
            (string) ($definition['name'] ?? ''),
            (string) ($definition['description'] ?? ''),
            (string) ($definition['routingDescription'] ?? ''),
            $exampleText,
            $properties,
        ]));
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<int, string>
     */
    private function examples(array $definition, string $name): array
    {
        $examples = is_array($definition['examples'] ?? null) ? $definition['examples'] : [];
        $examples = array_values(array_filter(
            array_map(static fn(mixed $example): string => trim((string) $example), $examples),
            static fn(string $example): bool => $example !== '',
        ));

        return match ($name) {
            'cms_tools_search' => [
                ...$examples,
                'Find more tools for this request',
                'Search tools by capability',
                'Welche Tools passen hierzu?',
            ],
            default => $examples,
        };
    }

    /**
     * @return array<int, string>
     */
    private function tags(string $name, string $description): array
    {
        $words = preg_split('/[^a-z0-9]+/', $name . ' ' . $description) ?: [];
        $stop = array_flip(['cms', 'external', 'returns', 'return', 'and', 'for', 'the', 'with', 'from', 'into', 'optional', 'current']);
        $tags = [];

        foreach ($words as $word) {
            $word = strtolower(trim($word));
            if (strlen($word) < 3 || isset($stop[$word])) {
                continue;
            }

            $tags[$word] = $word;
            if (count($tags) >= 18) {
                break;
            }
        }

        return array_values($tags);
    }

    /**
     * @param array<int, string> $needles
     */
    private function contains(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
