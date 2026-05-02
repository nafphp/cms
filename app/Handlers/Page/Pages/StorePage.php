<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Page\Pages;

use NixCMS\Handlers\Support\ReadsJsonBody;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class StorePage
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $data = $this->jsonBody();
        $templates = $this->templates();
        $errors = $this->validate($data, $templates);
        if ($errors !== []) {
            return json(['success' => false, 'errors' => $errors], 400);
        }

        $template = $templates[(string) ($data['templateId'] ?? '')];

        try {
            $id = \cms()->pages()->save([
                'id' => (int) ($data['id'] ?? 0),
                'identifier' => $this->identifier($data),
                'path' => (string) ($data['path'] ?? ''),
                'title' => (string) ($data['title'] ?? ''),
                'language' => (string) ($data['language'] ?? ''),
                'status' => (string) ($data['status'] ?? 'draft'),
                'template_id' => (string) ($data['templateId'] ?? ''),
                'layout' => (string) ($template['layout'] ?? 'layouts.page'),
                'blocks' => $this->normalizeBlocks(
                    is_array($data['blocks'] ?? null) ? $data['blocks'] : [],
                    is_array($template['blocks'] ?? null) ? $template['blocks'] : []
                ),
                'meta' => is_array($data['meta'] ?? null) ? $data['meta'] : [],
            ]);

            return json(['success' => true, 'id' => $id]);
        } catch (Throwable $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 500);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function templates(): array
    {
        $file = BASE_PATH . '/app/Resources/pages/page_templates.php';
        $definitions = is_file($file) ? require $file : [];
        $templates = [];

        foreach (is_array($definitions) ? $definitions : [] as $definition) {
            if (is_array($definition) && trim((string) ($definition['id'] ?? '')) !== '') {
                $templates[(string) $definition['id']] = $definition;
            }
        }

        return $templates;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string, mixed>> $templates
     * @return array<string, array<int, string>>
     */
    private function validate(array $data, array $templates): array
    {
        $errors = [];

        if (trim((string) ($data['title'] ?? '')) === '') {
            $errors['title'][] = 'Please enter a title.';
        }

        if (trim((string) ($data['path'] ?? '')) === '') {
            $errors['path'][] = 'Please enter a path.';
        } elseif ($this->collidesWithCodePage((string) ($data['path'] ?? ''))) {
            $errors['path'][] = 'This path is already used by a code-defined page.';
        }

        $templateId = (string) ($data['templateId'] ?? '');
        if ($templateId === '' || !isset($templates[$templateId])) {
            $errors['templateId'][] = 'Please choose an existing page template.';
        }

        $language = strtolower(trim((string) ($data['language'] ?? '')));
        if ($language === '' || !in_array($language, \cms()->settings()->languages(), true)) {
            $errors['language'][] = 'Please choose an enabled page language.';
        }

        if (!is_array($data['blocks'] ?? null)) {
            $errors['blocks'][] = 'Blocks must be an object keyed by template block id.';
        } elseif (isset($templates[$templateId])) {
            $allowed = array_flip(array_values(array_filter(array_map(
                static fn(mixed $block): string => is_array($block) ? (string) ($block['id'] ?? '') : '',
                is_array($templates[$templateId]['blocks'] ?? null) ? $templates[$templateId]['blocks'] : []
            ))));

            foreach (array_keys($data['blocks']) as $blockId) {
                if (!isset($allowed[(string) $blockId])) {
                    $errors['blocks'][] = sprintf('Block "%s" is not part of the selected template.', (string) $blockId);
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $blocks
     * @param array<int, array<string, mixed>> $templateBlocks
     * @return array<string, mixed>
     */
    private function normalizeBlocks(array $blocks, array $templateBlocks): array
    {
        $allowed = [];
        foreach ($templateBlocks as $block) {
            $id = trim((string) ($block['id'] ?? ''));
            if ($id !== '') {
                $allowed[$id] = true;
            }
        }

        $normalized = [];
        foreach ($blocks as $id => $content) {
            $id = (string) $id;
            if (!isset($allowed[$id]) || $content === null) {
                continue;
            }

            $normalized[$id] = is_array($content) ? $content : [];
        }

        return $normalized;
    }

    /** @param array<string, mixed> $data */
    private function identifier(array $data): string
    {
        $identifier = trim((string) ($data['identifier'] ?? ''));
        if ($identifier !== '') {
            return $identifier;
        }

        $path = trim((string) ($data['path'] ?? ''));
        if ($path === '/' || $path === '') {
            return 'home';
        }

        return trim(str_replace('/', '.', trim($path, '/')), '.');
    }

    private function collidesWithCodePage(string $path): bool
    {
        $path = $this->normalizePath($path);

        foreach (\cms()->pages()->all() as $page) {
            if (str_starts_with($page->id(), 'db.page.')) {
                continue;
            }

            if ($page->path() === $path) {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
