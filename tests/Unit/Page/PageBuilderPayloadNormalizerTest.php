<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use Naf\CMS\Page\PageBuilderPayloadNormalizer;
use PHPUnit\Framework\TestCase;

final class PageBuilderPayloadNormalizerTest extends TestCase
{
    public function testRepresentativeNestedLayoutIsStableAcrossSaveAndReloadNormalization(): void
    {
        $payload = [
            'main' => [
                'type'         => 'section',
                'label'        => 'Section',
                'previewTitle' => 'Section',
                'props'        => [
                    'width'    => 'wide',
                    'padding'  => 'medium',
                    'tone'     => 'soft',
                    'children' => [[
                        'type'         => 'columns',
                        'label'        => 'Columns',
                        'previewTitle' => 'Columns',
                        'props'        => [
                            'columns'        => 2,
                            'gap'            => 'large',
                            'align'          => 'stretch',
                            'containerStyle' => 'card',
                            'children'       => [[
                                'type'         => 'text',
                                'label'        => 'Text block',
                                'previewTitle' => 'Short text',
                                'props'        => [
                                    'text'          => 'Hello',
                                    'tone'          => 'lead',
                                    'align'         => 'left',
                                    'verticalAlign' => 'top',
                                ],
                            ]],
                        ],
                    ]],
                ],
            ],
        ];
        $slots = [['id' => 'main', 'region' => 'main', 'accepts' => ['section']]];

        $firstErrors  = [];
        $first        = (new PageBuilderPayloadNormalizer())->normalize($payload, $slots, $firstErrors);
        $secondErrors = [];
        $second       = (new PageBuilderPayloadNormalizer())->normalize($first, $slots, $secondErrors);

        self::assertSame([], $firstErrors);
        self::assertSame([], $secondErrors);
        self::assertSame($first, $second);
    }

    public function testRejectsInvalidSelectUnsafeUrlAndUnknownField(): void
    {
        $errors = [];
        (new PageBuilderPayloadNormalizer())->normalize([
            'hero' => [
                'type'  => 'hero',
                'props' => [
                    'title'     => 'Unsafe',
                    'buttonUrl' => 'javascript:alert(1)',
                    'surface'   => 'not-a-surface',
                    'unknown'   => 'value',
                ],
            ],
        ], [['id' => 'hero', 'region' => 'hero', 'accepts' => ['hero']]], $errors);

        $message = implode("\n", $errors);
        self::assertStringContainsString('unsafe URL', $message);
        self::assertStringContainsString('invalid option', $message);
        self::assertStringContainsString('unknown field "unknown"', $message);
    }

    public function testRejectsUnknownBlockTypeAndExcessiveDepth(): void
    {
        $unknownErrors = [];
        (new PageBuilderPayloadNormalizer())->normalize([
            'main' => ['type' => 'plugin-that-is-not-registered', 'props' => []],
        ], [['id' => 'main', 'region' => 'main']], $unknownErrors);
        self::assertStringContainsString('unknown block type', implode("\n", $unknownErrors));

        $child = ['type' => 'text', 'props' => ['text' => 'Deep', 'tone' => 'body', 'align' => 'left', 'verticalAlign' => 'top', 'surface' => 'none']];
        for ($depth = 0; $depth < PageBuilderPayloadNormalizer::MAX_DEPTH + 2; $depth++) {
            $child = ['type' => 'stack', 'props' => ['gap' => 'medium', 'align' => 'stretch', 'children' => [$child]]];
        }

        $depthErrors = [];
        (new PageBuilderPayloadNormalizer())->normalize([
            'main' => $child,
        ], [['id' => 'main', 'region' => 'main', 'accepts' => ['stack']]], $depthErrors);
        self::assertStringContainsString('nesting limit', implode("\n", $depthErrors));
    }
}
