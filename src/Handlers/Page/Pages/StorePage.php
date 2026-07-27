<?php

declare(strict_types=1);

namespace src\Handlers\Page\Pages;

use src\Handlers\Support\ReadsJsonBody;
use src\Page\CustomLayoutNormalizer;
use src\Page\PageBuilderPayloadNormalizer;
use src\Page\PageTemplateCatalog;
use src\Repositories\PageLayoutRepository;
use src\Security\UrlPolicy;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;
use function NixCMS\cms;

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
        $blockDefinitions = array_key_exists('custom_layout', $meta)
            ? (is_array($meta['custom_layout'] ?? null) ? $meta['custom_layout'] : [])
            : (is_array($template['blocks'] ?? null) ? $template['blocks'] : []);

        try {
            $id = cms()->pages()->save([
                'id' => (int) ($data['id'] ?? 0),
                'identifier' => $this->identifier($data),
                'path' => (string) ($data['path'] ?? ''),
                'title' => (string) ($data['title'] ?? ''),
                'language' => (string) ($data['language'] ?? ''),
                'status' => (string) ($data['status'] ?? 'draft'),
                'template_id' => (string) ($data['templateId'] ?? ''),
                'theme_id' => (string) ($data['themeId'] ?? ''),
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
        $templates = [];

        foreach ([
            ...(new PageTemplateCatalog())->codeDefinitions(),
            ...(new PageLayoutRepository())->templates(),
        ] as $definition) {
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

        $encoded = json_encode($data);
        if (!is_string($encoded) || strlen($encoded) > PageBuilderPayloadNormalizer::MAX_JSON_BYTES) {
            $errors['payload'][] = sprintf('Builder payload must not exceed %d bytes.', PageBuilderPayloadNormalizer::MAX_JSON_BYTES);
        }

        if (($data['builderVersion'] ?? null) !== PageBuilderPayloadNormalizer::VERSION) {
            $errors['builderVersion'][] = sprintf('Builder payload version %d is required.', PageBuilderPayloadNormalizer::VERSION);
        }

        if (trim((string) ($data['title'] ?? '')) === '') {
            $errors['title'][] = 'Please enter a title.';
        }

        if (trim((string) ($data['path'] ?? '')) === '') {
            $errors['path'][] = 'Please enter a path.';
        } elseif ($this->collidesWithCodePage((string) ($data['path'] ?? ''))) {
            $errors['path'][] = 'This path is already used by a code-defined page.';
        }

        $canonicalUrl = trim((string) ($data['meta']['seo']['canonicalUrl'] ?? ''));
        if ($canonicalUrl !== '' && UrlPolicy::link($canonicalUrl, false, false) === null) {
            $errors['meta.seo.canonicalUrl'][] = 'Canonical URL must be a safe internal, HTTP, or HTTPS URL.';
        }

        $templateId = (string) ($data['templateId'] ?? '');
        if ($templateId === '' || !isset($templates[$templateId])) {
            $errors['templateId'][] = 'Please choose an existing page template.';
        }

        $language = strtolower(trim((string) ($data['language'] ?? '')));
        if ($language === '' || !in_array($language, cms()->settings()->languages(), true)) {
            $errors['language'][] = 'Please choose an enabled page language.';
        }

        if (!is_array($data['blocks'] ?? null)) {
            $errors['blocks'][] = 'Blocks must be an object keyed by template block id.';
        } elseif (isset($templates[$templateId])) {
            $template = $templates[$templateId];
            $templateBlocks = is_array($template['blocks'] ?? null) ? $template['blocks'] : [];
            if ($this->isCustomTemplate($template) || is_array($data['meta']['custom_layout'] ?? null)) {
                $layoutErrors = [];
                $templateBlocks = $this->normalizeCustomLayout(
                    is_array($data['meta']['custom_layout'] ?? null) ? $data['meta']['custom_layout'] : [],
                    $layoutErrors
                );
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

            $blockErrors = [];
            (new PageBuilderPayloadNormalizer())->normalize($data['blocks'], $templateBlocks, $blockErrors);
            foreach ($blockErrors as $message) {
                $errors['blocks'][] = $message;
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
        $meta['version'] = PageBuilderPayloadNormalizer::VERSION;
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
        $errors = [];
        return (new PageBuilderPayloadNormalizer())->normalize($blocks, $templateBlocks, $errors);
    }

    /**
     * @param array<int, mixed> $layout
     * @param array<int, string> $errors
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCustomLayout(array $layout, array &$errors): array
    {
        return (new CustomLayoutNormalizer())->normalize($layout, $errors);
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

        foreach (cms()->pages()->all() as $page) {
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
