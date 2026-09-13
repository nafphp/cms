<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block;
use function Naf\CMS\cms;
use function Naf\log;

final class PageBuilderBlockOptions
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function navigationChoices(): array
    {
        $choices = [];

        try {
            $summaries = cms()->navigation()->summaries('frontend');
        } catch (\Throwable $e) {
            log()->warning('PageBuilderBlockOptions.navigationChoices failed', [
                'exception' => $e,
            ]);
            $summaries = [];
        }

        foreach ($summaries as $summary) {
            $identifier = trim((string) ($summary['identifier'] ?? ''));
            if ($identifier === '') {
                continue;
            }

            $language = trim((string) ($summary['language'] ?? ''));
            try {
                $items = cms()->navigation()->tree($identifier, $language !== '' ? $language : null);
            } catch (\Throwable $e) {
                log()->warning('PageBuilderBlockOptions.navigationTree failed', [
                    'exception' => $e,
                    'identifier' => $identifier,
                    'language' => $language,
                ]);
                $items = [];
            }

            $status = (string) ($summary['status'] ?? 'draft');
            $priority = (int) ($summary['priority'] ?? 0);

            $choices[] = [
                'identifier' => $identifier,
                'label' => (string) (($summary['title'] ?? null) ?: $identifier),
                'language' => $language,
                'status' => $status,
                'priority' => $priority,
                'itemCount' => (int) ($summary['item_count'] ?? count($items)),
                'items' => $items,
                'rank' => match ($status) {
                    'primary' => 300,
                    'published' => 200,
                    default => 100,
                } + $priority + match ($identifier) {
                    'main', 'primary', 'header' => 25,
                    default => 0,
                },
            ];
        }

        usort(
            $choices,
            static fn(array $a, array $b): int => [$b['rank'], $b['itemCount'], $a['label']]
                <=> [$a['rank'], $a['itemCount'], $b['label']]
        );

        if ($choices === []) {
            $choices[] = [
                'identifier' => 'main',
                'label' => 'Main navigation',
                'language' => '',
                'status' => 'preview',
                'priority' => 0,
                'itemCount' => 4,
                'items' => [
                    ['label' => 'Home', 'href' => '/', 'children' => []],
                    ['label' => 'News', 'href' => '/news', 'children' => []],
                    ['label' => 'Pages', 'href' => '/pages', 'children' => []],
                    ['label' => 'Contact', 'href' => '/contact', 'children' => []],
                ],
            ];
        }

        return array_map(static function (array $choice): array {
            unset($choice['rank']);
            return $choice;
        }, $choices);
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    public static function navigationOptions(): array
    {
        return array_map(static fn(array $navigation): array => [
            'label' => trim(sprintf(
                '%s%s%s',
                (string) $navigation['label'],
                (string) ($navigation['status'] ?? '') !== '' ? ' - ' . (string) $navigation['status'] : '',
                (int) ($navigation['priority'] ?? 0) > 0 ? ' - Prio ' . (int) $navigation['priority'] : ''
            )),
            'value' => (string) $navigation['identifier'],
        ], self::navigationChoices());
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    public static function formOptions(): array
    {
        try {
            $options = cms()->forms()->choices(false);
        } catch (\Throwable $e) {
            log()->warning('PageBuilderBlockOptions.formOptions failed', [
                'exception' => $e,
            ]);
            $options = [];
        }

        return $options !== [] ? $options : [['label' => 'Contact form', 'value' => 'contact']];
    }
}
