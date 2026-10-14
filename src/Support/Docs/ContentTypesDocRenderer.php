<?php

declare(strict_types=1);

namespace Naf\CMS\Support\Docs;

use Naf\CMS\Content\ContentTypeInterface;

use function Naf\CMS\cms;

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
                array_values($types),
            ),
        );
    }
}
