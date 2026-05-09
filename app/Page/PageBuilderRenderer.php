<?php

declare(strict_types=1);

namespace NixCMS\Page;

use NixPHP\I18n\Core\Translator;
use function NixPHP\app;
use function NixPHP\I18n\t;
use function NixPHP\View\view;

final class PageBuilderRenderer
{
    /**
     * @param array<string, mixed> $context
     */
    public function render(Page $page, array $context = []): string
    {
        $translator = $this->translator();
        $previousLanguage = $translator?->getLanguage();
        $pageLanguage = strtolower(trim((string) ($page->language() ?? '')));
        if ($translator instanceof Translator && $pageLanguage !== '') {
            $translator->setLanguage($pageLanguage);
        }

        try {
            return $this->renderWithCurrentLanguage($page, $context);
        } finally {
            if ($translator instanceof Translator && $previousLanguage !== null) {
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
        } catch (\Throwable) {
            return null;
        }
    }
}
