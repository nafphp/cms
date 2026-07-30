<?php

declare(strict_types=1);

namespace NixPHP\CMS\Page;

use NixPHP\I18n\Core\Translator;
use NixPHP\CMS\Page\Layout;
use NixPHP\CMS\Page\Page;
use function NixPHP\app;
use function NixPHP\I18n\t;
use function NixPHP\log;
use function NixPHP\View\view;

final class PageBuilderRenderer
{
    /**
     * Render a page for the current visitor.
     *
     * The page's own language is deliberately not forced onto the translator.
     * A stored page exists in one language, but it is served to every visitor,
     * so pinning the render to it made labels and content blocks ignore the
     * requested language: an English visitor got a German article list even
     * where English translations existed. The visitor's language wins; blocks
     * fall back per item when a translation is missing.
     *
     * Pass $language to pin the render anyway, e.g. to preview a specific
     * translation independently of who is looking at it.
     *
     * @param array<string, mixed> $context
     */
    public function render(Page $page, array $context = [], ?string $language = null): string
    {
        $translator = $this->translator();
        $language = strtolower(trim((string) $language));

        if (!$translator instanceof Translator || $language === '') {
            return $this->renderWithCurrentLanguage($page, $context);
        }

        $previousLanguage = $translator->getLanguage();
        $translator->setLanguage($language);

        try {
            return $this->renderWithCurrentLanguage($page, $context);
        } finally {
            if ($previousLanguage !== null) {
                $translator->setLanguage($previousLanguage);
            }
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderWithCurrentLanguage(Page $page, array $context = []): string
    {
        $layout = $page->pageLayout() ?? new Layout('default');
        $template = $layout->template();
        $view = $layout->templateView() ?? 'page.templates.default';
        $blocks = [];

        foreach ($layout->blocks() as $block) {
            $blocks[$block->id()] = view($block->template()->view() ?? '', [
                ...$context,
                ...$block->data(),
                'page' => $page,
                'layout' => $layout,
                'template' => $template,
                'block' => $block,
            ]);
        }

        $block = static function (string $id, string $default = '') use ($layout, $blocks): string {
            if (!array_key_exists($id, $layout->blocks())) {
                throw new \LogicException(sprintf(
                    'Layout template "%s" tried to render undefined block "%s".',
                    $layout->templateView() ?? 'page.templates.default',
                    $id
                ));
            }

            return $blocks[$id] ?? $default;
        };

        $variables = [
            ...$context,
            'page' => $page,
            'layout' => $layout,
            'template' => $template,
            'title' => t($page->title()),
            'pageTitle' => t($page->title()),
            'blocks' => $blocks,
            'blockOutput' => implode("\n", $blocks),
            'block' => $block,
            'templateView' => $view,
        ];

        return view('page.template', $variables);
    }

    private function translator(): ?Translator
    {
        try {
            $translator = app()->container()->get(Translator::class);
            return $translator instanceof Translator ? $translator : null;
        } catch (\Throwable $e) {
            log()->warning('PageBuilderRenderer.translator unavailable', [
                'exception' => $e,
            ]);
            return null;
        }
    }
}
