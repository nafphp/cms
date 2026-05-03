<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Page\Pages;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Page\CustomLayoutOptions;
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
        $meta = $this->normalizeMeta(is_array($data['meta'] ?? null) ? $data['meta'] : []);
        if ($this->isCustomTemplate($template) && !array_key_exists('custom_layout', $meta)) {
            $meta['custom_layout'] = [];
        }
        $blockDefinitions = $this->isCustomTemplate($template)
            ? (is_array($meta['custom_layout'] ?? null) ? $meta['custom_layout'] : [])
            : (is_array($template['blocks'] ?? null) ? $template['blocks'] : []);

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
                    $blockDefinitions
                ),
                'meta' => $meta,
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
            $template = $templates[$templateId];
            $templateBlocks = is_array($template['blocks'] ?? null) ? $template['blocks'] : [];
            if ($this->isCustomTemplate($template)) {
                $layoutErrors = [];
                $templateBlocks = $this->normalizeCustomLayout(
                    is_array($data['meta']['custom_layout'] ?? null) ? $data['meta']['custom_layout'] : [],
                    $layoutErrors
                );
                foreach ($layoutErrors as $message) {
                    $errors['meta.custom_layout'][] = $message;
                }
            } elseif (is_array($data['meta']['custom_layout'] ?? null)) {
                $layoutErrors = [];
                $this->normalizeCustomLayout($data['meta']['custom_layout'], $layoutErrors);
                foreach ($layoutErrors as $message) {
                    $errors['meta.custom_layout'][] = $message;
                }
            }

            $allowed = array_flip(array_values(array_filter(array_map(
                static fn(mixed $block): string => is_array($block) ? (string) ($block['id'] ?? '') : '',
                $templateBlocks
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
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function normalizeMeta(array $meta): array
    {
        if (array_key_exists('custom_layout', $meta)) {
            $layoutErrors = [];
            $meta['custom_layout'] = $this->normalizeCustomLayout(
                is_array($meta['custom_layout']) ? $meta['custom_layout'] : [],
                $layoutErrors
            );
        }

        return $meta;
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

    /**
     * @param array<int, mixed> $layout
     * @param array<int, string> $errors
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCustomLayout(array $layout, array &$errors): array
    {
        $normalized = [];
        $seen = [];

        foreach (array_values($layout) as $index => $slot) {
            if (!is_array($slot)) {
                $errors[] = sprintf('Custom layout slot %d must be an object.', $index + 1);
                continue;
            }

            $id = trim((string) ($slot['id'] ?? ''));
            $label = trim((string) ($slot['label'] ?? ''));
            $region = trim((string) ($slot['region'] ?? 'main'));
            $size = trim((string) ($slot['size'] ?? 'medium'));
            $position = trim((string) ($slot['position'] ?? 'stretch'));
            $maxHeight = trim((string) ($slot['maxHeight'] ?? 'none'));
            $overflow = trim((string) ($slot['overflow'] ?? 'visible'));
            $surface = trim((string) ($slot['surface'] ?? 'none'));

            if ($id === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $id)) {
                $errors[] = sprintf('Custom layout slot %d needs a valid id.', $index + 1);
                continue;
            }

            if (isset($seen[$id])) {
                $errors[] = sprintf('Custom layout contains duplicate slot id "%s".', $id);
                continue;
            }
            $seen[$id] = true;

            if ($label === '') {
                $errors[] = sprintf('Custom layout slot "%s" needs a label.', $id);
                continue;
            }

            $invalidOption = $this->invalidCustomLayoutOption([
                'region' => [$region, CustomLayoutOptions::REGIONS],
                'size' => [$size, CustomLayoutOptions::SIZES],
                'position' => [$position, CustomLayoutOptions::POSITIONS],
                'max height' => [$maxHeight, CustomLayoutOptions::MAX_HEIGHTS],
                'overflow mode' => [$overflow, CustomLayoutOptions::OVERFLOWS],
                'surface' => [$surface, CustomLayoutOptions::SURFACES],
            ]);
            if ($invalidOption !== null) {
                $errors[] = sprintf('Custom layout slot "%s" uses an invalid %s.', $id, $invalidOption);
                continue;
            }

            $definition = [
                'id' => $id,
                'label' => $label,
                'region' => $region,
                'size' => $size,
                'position' => $position,
                'maxHeight' => $maxHeight,
                'overflow' => $overflow,
                'surface' => $surface,
            ];

            $accepts = [];
            foreach (is_array($slot['accepts'] ?? null) ? $slot['accepts'] : [] as $type) {
                $type = trim((string) $type);
                if ($type !== '' && preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $type)) {
                    $accepts[$type] = $type;
                }
            }
            if ($accepts !== []) {
                $definition['accepts'] = array_values($accepts);
            }

            $defaultContentType = trim((string) ($slot['defaultContentType'] ?? ''));
            if ($defaultContentType !== '' && preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $defaultContentType)) {
                $definition['defaultContentType'] = $defaultContentType;
            }

            $normalized[] = $definition;
        }

        return $normalized;
    }

    /**
     * @param array<string, array{0: string, 1: array<int, string>}> $options
     */
    private function invalidCustomLayoutOption(array $options): ?string
    {
        foreach ($options as $label => [$value, $allowed]) {
            if (!in_array($value, $allowed, true)) {
                return $label;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $template
     */
    private function isCustomTemplate(array $template): bool
    {
        return (string) ($template['kind'] ?? 'static') === 'custom';
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
        if ($path === '/') {
            return false;
        }

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
