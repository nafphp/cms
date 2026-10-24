<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

use Naf\CMS\Repositories\PageLayoutRepository;
use Naf\CMS\Repositories\PageRepository;

use function Naf\CMS\cms;

/**
 * Find stored pages whose blocks break the current placement rules
 *
 * Rendering and editing keep such blocks (see BlockPlacementPolicy), so after
 * narrowing the builder this is how to find the pages to tidy up. Permissions
 * are not part of the audit: they depend on who edits, not on the page.
 *
 * @internal
 */
final class PageBuilderAudit
{
    public function __construct(
        private readonly PageRepository $pages = new PageRepository(),
        private readonly ?PageLayoutRepository $layouts = new PageLayoutRepository(),
    ) {
    }

    /**
     * @return array<int, array{id: int, path: string, language: string, title: string, problems: array<int, string>}>
     */
    public function run(): array
    {
        $templates = [];
        foreach ([...cms()->pageTemplates()->definitions(), ...($this->layouts?->templates() ?? [])] as $definition) {
            $id = (string) ($definition['id'] ?? '');
            if ($id !== '') {
                $templates[$id] = $definition;
            }
        }

        $findings = [];
        foreach ($this->pages->summaries() as $summary) {
            $page = $this->pages->find((int) $summary['id']);
            if (!is_array($page)) {
                continue;
            }

            $problems = $this->problems($page, $templates);
            if ($problems !== []) {
                $findings[] = [
                    'id'       => (int) $summary['id'],
                    'path'     => (string) ($page['path'] ?? ''),
                    'language' => (string) ($page['language'] ?? ''),
                    'title'    => (string) ($page['title'] ?? ''),
                    'problems' => $problems,
                ];
            }
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, array<string, mixed>> $templates
     * @return array<int, string>
     */
    private function problems(array $page, array $templates): array
    {
        $templateId = (string) ($page['template_id'] ?? '');
        $template   = $templates[$templateId] ?? null;
        if ($template === null) {
            return [sprintf('Uses unknown page template "%s".', $templateId)];
        }

        $meta         = is_array($page['meta'] ?? null) ? $page['meta'] : [];
        $customLayout = array_key_exists('custom_layout', $meta);
        $problems     = [];
        $slots        = is_array($template['blocks'] ?? null) ? $template['blocks'] : [];
        if ($customLayout) {
            $slots = (new CustomLayoutNormalizer())->normalize(
                is_array($meta['custom_layout'] ?? null) ? $meta['custom_layout'] : [],
                $problems,
            );
        }

        (new PageBuilderPayloadNormalizer())->normalize(
            is_array($page['blocks'] ?? null) ? $page['blocks'] : [],
            $slots,
            $problems,
            BlockPlacementPolicy::forTemplate($template, null, [], $customLayout, checkPermissions: false),
        );

        return $problems;
    }
}
