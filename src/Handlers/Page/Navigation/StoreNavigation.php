<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Page\Navigation;

use InvalidArgumentException;
use Naf\CMS\Handlers\Support\ReadsJsonBody;
use Naf\CMS\Navigation\Navigation;
use Psr\Http\Message\ResponseInterface;
use Throwable;

use function Naf\CMS\cms;
use function Naf\json;

final class StoreNavigation
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $data   = $this->jsonBody();
        $errors = $this->validate($data);
        if ($errors !== []) {
            return json(['success' => false, 'errors' => $errors], 400);
        }

        try {
            $ids      = is_array($data['ids'] ?? null) ? $data['ids'] : [];
            $targetId = (int) ($ids[''] ?? ($data['id'] ?? 0));
            if ($targetId <= 0) {
                $targetId = (int) (reset($ids) ?: 0);
            }

            $identifier         = (string) ($data['identifier'] ?? '');
            $originalIdentifier = (string) ($data['originalIdentifier'] ?? $identifier);
            $status             = $this->normalizeStatus((string) ($data['status'] ?? 'draft'));

            $savedId = cms()->navigation()->save([
                'id'         => $targetId,
                'identifier' => $identifier,
                'title'      => (string) ($data['title'] ?? ''),
                'scope'      => 'frontend',
                'language'   => '',
                'status'     => $status,
                'items'      => $this->normalizeItems(is_array($data['items'] ?? null) ? $data['items'] : []),
            ]);

            cms()->navigation()->deleteTranslationsExcept($identifier, 'frontend', $savedId);
            if ($originalIdentifier !== '' && $originalIdentifier !== $identifier) {
                cms()->navigation()->deleteTranslationsExcept($originalIdentifier, 'frontend', 0);
            }

            return json(['success' => true, 'ids' => ['' => $savedId], 'id' => $savedId]);
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
        } catch (InvalidArgumentException) {
            $errors['identifier'][] = 'Please enter an identifier such as main, footer, or legal.';
        }

        if (trim((string) ($data['title'] ?? '')) === '') {
            $errors['title'][] = 'Please enter a title.';
        }

        return $errors;
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

            $key   = trim((string) ($item['key'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            if ($key === '') {
                $key = 'item-' . ($index + 1);
            }

            $normalized[] = [
                'key'        => Navigation::normalizeIdentifier($key),
                'label'      => $label,
                'href'       => trim((string) ($item['href'] ?? '')),
                'type'       => trim((string) ($item['type'] ?? 'url')) ?: 'url',
                'target'     => trim((string) ($item['target'] ?? '')),
                'icon'       => trim((string) ($item['icon'] ?? '')),
                'order'      => (int) ($item['order'] ?? (($index + 1) * 10)),
                'visible'    => (bool) ($item['visible'] ?? true),
                'scope'      => 'frontend',
                'routeNames' => [],
                'meta'       => is_array($item['meta'] ?? null) ? $item['meta'] : [],
                'children'   => $this->normalizeItems(is_array($item['children'] ?? null) ? $item['children'] : []),
            ];
        }

        return $normalized;
    }
}
