<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use Naf\CMS\Content\Page\Block\AbstractBlockType;
use Naf\CMS\Content\Page\Block\BlockTypeRegistry;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Page\BlockPlacementPolicy;
use Naf\CMS\Page\PageBuilderPayloadNormalizer;
use Naf\CMS\Support\UserHelper;
use PHPUnit\Framework\TestCase;
use Tests\Support\CoreBlockTypes;

final class BlockPlacementPolicyTest extends TestCase
{
    private BlockTypeRegistry $types;

    protected function setUp(): void
    {
        parent::setUp();
        $this->types = (new BlockTypeRegistry())->addMany(CoreBlockTypes::all());
    }

    public function testRenderingChecksDataOnlyAndKeepsBlocksThatBreakPlacementRules(): void
    {
        $this->types->disable('quote');
        $slots  = [['id' => 'main', 'region' => 'header', 'accepts' => ['text']]];
        $blocks = ['main' => $this->block('quote', ['quote' => 'Still here'])];

        $errors = [];
        $result = $this->normalizer()->normalize($blocks, $slots, $errors);

        self::assertSame([], $errors);
        self::assertSame('Still here', $result['main']['props']['quote']);
    }

    public function testSavingRejectsANewBlockOfADisabledType(): void
    {
        $this->types->disable('quote');

        $errors = $this->save(['main' => $this->block('quote')], [['id' => 'main', 'region' => 'main']]);

        self::assertSame(['blocks.main uses block type "quote", which is not available in the page builder.'], $errors);
    }

    public function testADisabledBlockThatWasAlreadyStoredStaysEditableInPlace(): void
    {
        $this->types->only('text');
        $slots    = [['id' => 'main', 'region' => 'main'], ['id' => 'aside', 'region' => 'aside']];
        $existing = ['main' => $this->block('quote', ['quote' => 'Old'])];

        self::assertSame([], $this->save(['main' => $this->block('quote', ['quote' => 'Edited'])], $slots, $existing));

        // Moving it counts as placing it anew.
        self::assertSame(
            ['blocks.aside uses block type "quote", which is not available in the page builder.'],
            $this->save(['aside' => $this->block('quote', ['quote' => 'Old'])], $slots, $existing),
        );
    }

    public function testSlotAcceptsAndRegionsApplyWhenSaving(): void
    {
        $errors = $this->save(
            ['main' => $this->block('quote')],
            [['id' => 'main', 'region' => 'header', 'accepts' => ['text', 'heading']]],
        );

        self::assertSame([
            'blocks.main does not accept block type "quote".',
            'blocks.main cannot use block type "quote" in region "header".',
        ], $errors);
    }

    /**
     * A container must not be a way around a slot's rules: nestedAccepts limits
     * everything inside the slot, a container's own field accepts its direct
     * children, and the global rules hold at every depth.
     */
    public function testNestedBlocksFollowNestedAcceptsFieldAcceptsAndGlobalRules(): void
    {
        $slots = [['id' => 'main', 'region' => 'main', 'accepts' => ['section'], 'nestedAccepts' => ['text', 'heading', 'columns']]];
        $page  = ['main' => $this->block('section', ['children' => [
            $this->block('text'),
            $this->block('embed', ['url' => 'https://example.com']),
            $this->block('columns', ['children' => [$this->block('quote')]]),
        ]])];

        self::assertSame([
            'blocks.main.props.children.1 does not accept block type "embed".',
            'blocks.main.props.children.2.props.children.0 does not accept block type "quote".',
        ], $this->save($page, $slots));

        $this->types->disable('text');
        self::assertContains(
            'blocks.main.props.children.0 uses block type "text", which is not available in the page builder.',
            $this->save(['main' => $this->block('section', ['children' => [$this->block('text')]])], [['id' => 'main', 'region' => 'main']]),
        );

        $this->types->add($this->containerOnlyFor(['heading']));
        self::assertSame(
            ['blocks.main.props.children.1 does not accept block type "quote".'],
            $this->save(
                ['main' => $this->block('heading-group', ['children' => [$this->block('heading'), $this->block('quote')]])],
                [['id' => 'main', 'region' => 'main']],
            ),
        );
    }

    public function testChildrenArePlacedAsMainContent(): void
    {
        self::assertSame(
            ['blocks.main.props.children.0 cannot use block type "aside" in region "main".'],
            $this->save(
                ['main' => $this->block('section', ['children' => [$this->block('aside')]])],
                [['id' => 'main', 'region' => 'aside']],
            ),
        );
    }

    public function testUniqueTypesMayAppearOnlyOnceRegardlessOfSlotOrder(): void
    {
        $slots = [['id' => 'first', 'region' => 'header'], ['id' => 'second', 'region' => 'header']];
        $two   = ['first' => $this->block('header'), 'second' => $this->block('header')];

        self::assertSame(
            ['blocks.second uses block type "header", which may only appear once per page.'],
            $this->save($two, $slots, ['first' => $this->block('header')]),
        );
        self::assertSame(
            ['blocks.first uses block type "header", which may only appear once per page.'],
            $this->save($two, $slots, ['second' => $this->block('header')]),
        );
        self::assertCount(2, $this->save($two, $slots));
        self::assertSame([], $this->save($two, $slots, $two), 'both were stored before the rule existed');
    }

    public function testTemplateKindsAndContentTypesApplyWhenSaving(): void
    {
        $this->types->add($this->blockType('article-extra', templateKinds: ['dynamic'], contentTypes: ['article']));
        $slots = [['id' => 'main', 'region' => 'main']];
        $page  = ['main' => $this->block('article-extra')];

        self::assertSame([
            'blocks.main cannot use block type "article-extra" in a "static" template.',
            'blocks.main cannot use block type "article-extra" in a template for this content type.',
        ], $this->save($page, $slots, template: ['kind' => 'static']));
        self::assertSame([], $this->save($page, $slots, template: ['kind' => 'dynamic', 'contentType' => 'article']));
    }

    public function testHeaderAndFooterAreasOfACustomLayoutTakeOnlyChromeTypes(): void
    {
        $slots = [
            ['id' => 'slot_header', 'region' => 'header'],
            ['id' => 'slot_footer', 'region' => 'footer'],
        ];

        self::assertSame([
            'blocks.slot_header cannot use block type "text" in the header area of a custom layout.',
            'blocks.slot_footer cannot use block type "cta" in the footer area of a custom layout.',
        ], $this->save(['slot_header' => $this->block('text'), 'slot_footer' => $this->block('cta')], $slots, template: ['kind' => 'custom']));

        self::assertSame([], $this->save(['slot_header' => $this->block('header'), 'slot_footer' => $this->block('text')], $slots, template: ['kind' => 'custom']));
        self::assertSame([], $this->save(['slot_footer' => $this->block('cta')], $slots), 'template slots follow regions() only');
    }

    public function testRestrictedTypesNeedThePermissionToBeAddedOrChanged(): void
    {
        $this->types->restrict('embed', Permission::THEMES_CODE_MANAGE);
        $slots    = [['id' => 'main', 'region' => 'main']];
        $embed    = $this->block('embed', ['url' => 'https://example.com/a']);
        $changed  = $this->block('embed', ['url' => 'https://example.com/b']);
        $editor   = new UserHelper(null, [Permission::PAGES_EDIT], [], true);
        $admin    = new UserHelper(null, [Permission::THEMES_CODE_MANAGE], [], true);
        $existing = ['main' => $embed];
        $denied   = ['blocks.main: you are not allowed to add or change blocks of type "embed".'];

        self::assertSame($denied, $this->save(['main' => $embed], $slots, actor: $editor));
        self::assertSame([], $this->save(['main' => $embed], $slots, $existing, actor: $editor), 'kept unchanged');
        self::assertSame($denied, $this->save(['main' => $changed], $slots, $existing, actor: $editor));
        self::assertSame([], $this->save(['main' => $changed], $slots, $existing, actor: $admin));
        self::assertSame([], $this->save([], $slots, $existing, actor: $editor), 'removing it is not changing it');
    }

    public function testEditingAroundARestrictedNestedBlockIsAllowed(): void
    {
        $this->types->restrict('embed', Permission::THEMES_CODE_MANAGE);
        $slots    = [['id' => 'main', 'region' => 'main']];
        $embed    = $this->block('embed', ['url' => 'https://example.com/a']);
        $existing = ['main' => $this->block('section', ['children' => [$this->block('text', ['text' => 'Old']), $embed]])];
        $edited   = ['main' => $this->block('section', ['children' => [$this->block('text', ['text' => 'New']), $embed]])];
        $editor   = new UserHelper(null, [Permission::PAGES_EDIT], [], true);

        self::assertSame([], $this->save($edited, $slots, $existing, actor: $editor));
    }

    public function testChangingAContainerDoesNotGrandfatherItsDisabledChildren(): void
    {
        $this->types->disable('quote');
        $slots    = [['id' => 'main', 'region' => 'main']];
        $existing = ['main' => $this->block('section', ['children' => [$this->block('quote')]])];
        $changed  = ['main' => $this->block('columns', ['children' => [$this->block('quote')]])];

        self::assertContains(
            'blocks.main.props.children.0 uses block type "quote", which is not available in the page builder.',
            $this->save($changed, $slots, $existing),
        );
        $this->types->enable('quote');
        self::assertSame([], $this->save($changed, $slots, $existing), 'moving allowed content remains possible');
    }

    public function testThePolicyDoesNotLeakStateBetweenPayloads(): void
    {
        $policy     = BlockPlacementPolicy::forTemplate(['kind' => 'static'], null, types: $this->types);
        $normalizer = $this->normalizer();
        $slots      = [['id' => 'main', 'region' => 'header']];

        $first = [];
        $normalizer->normalize(['main' => $this->block('header')], $slots, $first, $policy);
        $second = [];
        $normalizer->normalize(['main' => $this->block('header')], $slots, $second, $policy);

        self::assertSame([], $first);
        self::assertSame([], $second);
    }

    /**
     * @param array<string, mixed> $blocks
     * @param array<int, array<string, mixed>> $slots
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $template
     * @return array<int, string>
     */
    private function save(
        array $blocks,
        array $slots,
        array $existing = [],
        array $template = ['kind' => 'static'],
        ?UserHelper $actor = null,
    ): array {
        $errors = [];
        $this->normalizer()->normalize(
            $blocks,
            $slots,
            $errors,
            BlockPlacementPolicy::forTemplate($template, $actor, $existing, types: $this->types),
        );

        return $errors;
    }

    private function normalizer(): PageBuilderPayloadNormalizer
    {
        return new PageBuilderPayloadNormalizer($this->types);
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    private function block(string $type, array $props = []): array
    {
        return ['type' => $type, 'props' => $props];
    }

    /**
     * @param array<int, string> $templateKinds
     * @param array<int, string> $contentTypes
     */
    private function blockType(string $id, array $templateKinds = [], array $contentTypes = []): AbstractBlockType
    {
        return new class ($id, $templateKinds, $contentTypes) extends AbstractBlockType {
            /**
             * @param array<int, string> $kinds
             * @param array<int, string> $types
             */
            public function __construct(private readonly string $typeId, private readonly array $kinds, private readonly array $types)
            {
            }

            public function id(): string
            {
                return $this->typeId;
            }

            public function label(): string
            {
                return $this->typeId;
            }

            public function regions(): array
            {
                return ['main'];
            }

            public function templateKinds(): array
            {
                return $this->kinds;
            }

            public function contentTypes(): array
            {
                return $this->types;
            }
        };
    }

    /**
     * @param array<int, string> $accepts
     */
    private function containerOnlyFor(array $accepts): AbstractBlockType
    {
        return new class ($accepts) extends AbstractBlockType {
            /**
             * @param array<int, string> $accepts
             */
            public function __construct(private readonly array $accepts)
            {
            }

            public function id(): string
            {
                return 'heading-group';
            }

            public function label(): string
            {
                return 'Heading group';
            }

            public function regions(): array
            {
                return ['main'];
            }

            public function fields(): array
            {
                return [['name' => 'children', 'label' => 'Headings', 'type' => 'children', 'accepts' => $this->accepts]];
            }
        };
    }
}
