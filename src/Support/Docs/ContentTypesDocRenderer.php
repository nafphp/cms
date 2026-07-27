<?php

declare(strict_types=1);

namespace NixPHP\CMS\Support\Docs;

use NixPHP\CMS\Content\ContentTypeInterface;
use NixPHP\CMS\Support\Docs\DocBlockRenderer;
use NixPHP\CMS\Support\Docs\MarkdownTable;
use function NixPHP\CMS\cms;

final class ContentTypesDocRenderer implements DocBlockRenderer
{
    public function name(): string
    {
        return 'contenttypes';
    }

    public function render(): string
    {
        $types = cms()->contentTypes()->all();
        uasort($types, static fn(ContentTypeInterface $a, ContentTypeInterface $b): int => $a->id() <=> $b->id());

        return MarkdownTable::render(
            ['ID', 'Label', 'Model class'],
            array_map(
                static fn(ContentTypeInterface $type): array => [
                    '`' . $type->id() . '`',
                    $type->label(),
                    '`' . $type->modelClass() . '`',
                ],
                array_values($types)
            )
        );
    }
}
