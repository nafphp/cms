<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Page;

use Naf\CMS\Handlers\Support\ReadsJsonBody;
use Naf\CMS\Page\Layout;
use Naf\CMS\Page\Page;
use Naf\CMS\Page\PageBuilderPayloadNormalizer;
use Naf\CMS\Page\PageBuilderRenderer;
use Naf\CMS\Page\Template;
use Psr\Http\Message\ResponseInterface;
use Throwable;

use function Naf\CMS\cms;
use function Naf\json;
use function Naf\log;

/**
 * Render one block with its real view for the page-builder canvas
 *
 * The builder draws its own previews for the shipped block types. Plugin and
 * host block types have none there, so the builder asks this endpoint and
 * shows the returned document in a sandboxed frame. The block is checked for
 * valid data like any stored block; placement rules do not apply, because
 * nothing is saved.
 */
final class PreviewPageBlock
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $data    = $this->jsonBody();
        $content = is_array($data['content'] ?? null) ? $data['content'] : null;
        if ($content === null) {
            return json(['success' => false, 'errors' => ['content' => ['A block object is required.']]], 400);
        }

        $errors     = [];
        $normalized = (new PageBuilderPayloadNormalizer())->normalizeContent($content, $errors);
        if ($normalized === null || $errors !== []) {
            return json(['success' => false, 'errors' => ['content' => $errors]], 422);
        }

        try {
            return json(['success' => true, 'html' => $this->render($normalized)]);
        } catch (Throwable $exception) {
            log()->error('Page block preview failed', [
                'type'      => $normalized['type'],
                'exception' => $exception,
            ]);

            return json(['success' => false, 'reason' => 'The block preview could not be rendered.'], 500);
        }
    }

    /**
     * @param array<string, mixed> $content
     */
    private function render(array $content): string
    {
        $template = Template::create('page.block_preview')
            ->setParent('layouts.page_preview')
            ->addStylesheet('/css/nafcms.css')
            ->addBodyClass('dark bg-slate-950 text-slate-100');
        $layout = Layout::create($template)->addBlock('preview', cms()->blocks()->viewFor((string) $content['type']), [
            'label'   => (string) ($content['label'] ?? $content['type']),
            'region'  => 'main',
            'size'    => 'medium',
            'preview' => (string) $content['type'],
            'content' => $content,
        ]);

        return (new PageBuilderRenderer())->render(
            Page::create('preview.block')->setTitle((string) ($content['label'] ?? 'Block'))->setLayout($layout),
        );
    }
}
