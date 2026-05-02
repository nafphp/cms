<?php

declare(strict_types=1);

namespace NixCMS\Page;

use function NixPHP\View\view;

final class PageBuilderRenderer
{
    /**
     * @param array<string, mixed> $context
     */
    public function render(Page $page, array $context = []): string
    {
        $template = $page->template() ?? new Template('default');
        $view = $template->view() ?? 'page.templates.default';
        $blocks = [];

        foreach ($template->blocks() as $block) {
            $blocks[$block->id()] = view($block->view(), [
                ...$context,
                ...$block->data(),
                'page' => $page,
                'template' => $template,
                'block' => $block,
            ]);
        }

        $block = static function (string $id, string $default = '') use ($template, $blocks): string {
            if (!array_key_exists($id, $template->blocks())) {
                throw new \LogicException(sprintf(
                    'Template view "%s" tried to render undefined block "%s".',
                    $template->view() ?? 'page.templates.default',
                    $id
                ));
            }

            return $blocks[$id] ?? $default;
        };

        $variables = [
            ...$context,
            'page' => $page,
            'template' => $template,
            'title' => $page->title(),
            'pageTitle' => $page->title(),
            'blocks' => $blocks,
            'blockOutput' => implode("\n", $blocks),
            'block' => $block,
            'templateView' => $view,
        ];

        return view('page.template', $variables);
    }
}
