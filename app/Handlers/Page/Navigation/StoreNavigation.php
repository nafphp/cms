<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Page\Navigation;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Navigation\Navigation;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class StoreNavigation
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $data = $this->jsonBody();
        $errors = $this->validate($data);
        if ($errors !== []) {
            return json(['success' => false, 'errors' => $errors], 400);
        }

        try {
            $savedIds = [];
            $languages = $this->normalizeLanguages(is_array($data['languages'] ?? null) ? $data['languages'] : []);
            $itemsByLanguage = is_array($data['itemsByLanguage'] ?? null) ? $data['itemsByLanguage'] : [];
            $status = $this->normalizeStatus((string) ($data['status'] ?? 'draft'));

            foreach ($languages as $language) {
                $savedIds[$language] = \cms()->navigation()->save([
                    'id' => (int) ($data['ids'][$language] ?? 0),
                    'identifier' => (string) ($data['identifier'] ?? ''),
                    'title' => (string) ($data['title'] ?? ''),
                    'scope' => 'frontend',
                    'language' => $language,
                    'status' => $status,
                    'items' => $this->normalizeItems(is_array($itemsByLanguage[$language] ?? null) ? $itemsByLanguage[$language] : []),
                ]);
            }

            return json(['success' => true, 'ids' => $savedIds, 'id' => reset($savedIds) ?: 0]);
        } catch (Throwable $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array<int, string>>
     */
    private function validate(array $data): array
    {
        $errors = [];
        try {
            Navigation::normalizeIdentifier((string) ($data['identifier'] ?? ''));
        } catch (\InvalidArgumentException) {
            $errors['identifier'][] = 'Please enter an identifier such as main, footer, or legal.';
        }

        if (trim((string) ($data['title'] ?? '')) === '') {
            $errors['title'][] = 'Please enter a title.';
        }

        if ($this->normalizeLanguages(is_array($data['languages'] ?? null) ? $data['languages'] : []) === []) {
            $errors['languages'][] = 'Please choose at least one language.';
        }

        return $errors;
    }

    /**
     * @param array<int, mixed> $languages
     * @return array<int, string>
     */
    private function normalizeLanguages(array $languages): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn(mixed $language): string => strtolower(trim((string) $language)),
            $languages
        ), static fn(string $language): bool => $language !== '')));
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return in_array($status, ['draft', 'published', 'primary'], true) ? $status : 'draft';
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = trim((string) ($item['key'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            if ($key === '') {
                $key = 'item-' . ($index + 1);
            }

            $normalized[] = [
                'key' => Navigation::normalizeIdentifier($key),
                'label' => $label,
                'href' => trim((string) ($item['href'] ?? '')),
                'type' => trim((string) ($item['type'] ?? 'url')) ?: 'url',
                'target' => trim((string) ($item['target'] ?? '')),
                'icon' => trim((string) ($item['icon'] ?? '')),
                'order' => (int) ($item['order'] ?? (($index + 1) * 10)),
                'visible' => (bool) ($item['visible'] ?? true),
                'scope' => 'frontend',
                'routeNames' => [],
                'meta' => is_array($item['meta'] ?? null) ? $item['meta'] : [],
                'children' => $this->normalizeItems(is_array($item['children'] ?? null) ? $item['children'] : []),
            ];
        }

        return $normalized;
    }
}
