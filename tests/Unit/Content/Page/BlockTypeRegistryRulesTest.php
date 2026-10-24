<?php

declare(strict_types=1);

namespace Tests\Unit\Content\Page;

use Naf\CMS\Content\Page\Block\AbstractBlockType;
use Naf\CMS\Content\Page\Block\BlockTypeRegistry;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Support\UserHelper;
use Naf\Core\Config;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;
use Tests\Support\CoreBlockTypes;

use function Naf\app;
use function Naf\log;

final class BlockTypeRegistryRulesTest extends TestCase
{
    private Config $previousConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousConfig = app()->container()->get(Config::class);
    }

    protected function tearDown(): void
    {
        app()->container()->set(Config::class, $this->previousConfig);
        parent::tearDown();
    }

    public function testEveryRegisteredTypeIsAvailableWithoutRules(): void
    {
        $registry = $this->registry();

        foreach (array_keys($registry->all()) as $id) {
            self::assertTrue($registry->isAvailable($id), $id);
            self::assertTrue($registry->isInsertable($id, null), $id);
        }
        self::assertFalse($registry->isAvailable('not-registered'));
    }

    public function testOnlyAllowsTheListedTypesIncludingLaterRegistrations(): void
    {
        $registry = $this->registry()->only('Text', ' heading ', 'plugin-block');

        self::assertTrue($registry->isAvailable('text'));
        self::assertTrue($registry->isAvailable('heading'));
        self::assertFalse($registry->isAvailable('embed'));
        self::assertFalse($registry->isAvailable('plugin-block'), 'listed but not registered');

        $registry->add($this->blockType('plugin-block'));
        $registry->add($this->blockType('other-plugin'));
        self::assertTrue($registry->isAvailable('plugin-block'));
        self::assertFalse($registry->isAvailable('other-plugin'));

        // A disabled type stays registered and renderable.
        self::assertNotNull($registry->get('embed'));
    }

    public function testDisableAndEnable(): void
    {
        $registry = $this->registry()->disable('embed', 'MAP');

        self::assertFalse($registry->isAvailable('embed'));
        self::assertFalse($registry->isAvailable('map'));
        self::assertTrue($registry->isAvailable('text'));

        $registry->enable('map');
        self::assertTrue($registry->isAvailable('map'));

        // An allowlist still wins over enable().
        $registry->only('text')->enable('embed');
        self::assertFalse($registry->isAvailable('embed'));
    }

    public function testConfigurationAllowsDisablesAndRestricts(): void
    {
        $this->configure([
            'allowed_block_types'  => ['text', 'heading', 'embed'],
            'disabled_block_types' => ['heading'],
            'block_permissions'    => ['embed' => 'themes.code.manage'],
        ]);
        $registry = $this->registry();

        self::assertTrue($registry->isAvailable('text'));
        self::assertFalse($registry->isAvailable('heading'), 'disabled by configuration');
        self::assertFalse($registry->isAvailable('image'), 'not in the configured allowlist');
        self::assertSame(Permission::THEMES_CODE_MANAGE, $registry->requiredPermission('embed'));
        self::assertFalse($registry->isInsertable('embed', $this->actor()));
        self::assertTrue($registry->isInsertable('embed', $this->actor(Permission::THEMES_CODE_MANAGE)));

        // Code overrides the configuration in both directions.
        $registry->enable('heading')->only('image')->restrict('embed', null);
        self::assertFalse($registry->isAvailable('text'));
        self::assertTrue($registry->isAvailable('image'));
        self::assertNull($registry->requiredPermission('embed'));
    }

    public function testAnEmptyConfiguredAllowlistMeansEveryType(): void
    {
        $this->configure(['allowed_block_types' => []]);

        self::assertTrue($this->registry()->isAvailable('embed'));
    }

    public function testPermissionsComeFromTheTypeAndCanBeOverridden(): void
    {
        $registry = $this->registry();
        $registry->add(new class extends AbstractBlockType {
            public function id(): string
            {
                return 'restricted-plugin';
            }

            public function label(): string
            {
                return 'Restricted';
            }

            public function regions(): array
            {
                return ['main'];
            }

            public function requiredPermission(): Permission
            {
                return Permission::PAGES_DELETE;
            }
        });

        self::assertSame(Permission::PAGES_DELETE, $registry->requiredPermission('restricted-plugin'));
        self::assertFalse($registry->isPermitted('restricted-plugin', null));
        self::assertFalse($registry->isPermitted('restricted-plugin', $this->actor(Permission::PAGES_EDIT)));
        self::assertTrue($registry->isPermitted('restricted-plugin', $this->actor(Permission::PAGES_DELETE)));
        self::assertTrue($registry->isPermitted('text', null), 'no requirement');

        $registry->restrict('restricted-plugin', null);
        self::assertTrue($registry->isPermitted('restricted-plugin', null));

        $registry->restrict('text', Permission::PAGES_EDIT);
        self::assertFalse($registry->isPermitted('text', null));
        self::assertTrue($registry->isPermitted('text', $this->actor(Permission::PAGES_EDIT)));
    }

    public function testAnUnknownConfiguredPermissionClosesTheTypeAndWarnsOnce(): void
    {
        $this->configure(['block_permissions' => ['embed' => 'pages.typo']]);
        $previous = log();
        $logger   = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Unknown permission configured for page block type; nobody may use it',
            ['type' => 'embed', 'permission' => 'pages.typo'],
        );
        app()->container()->set(LoggerInterface::class, $logger);

        try {
            $registry = $this->registry();
            self::assertFalse($registry->isPermitted('embed', $this->actor(...Permission::cases())));
            self::assertFalse($registry->isInsertable('embed', null));
            self::assertNull($registry->requiredPermission('embed'));
        } finally {
            app()->container()->set(LoggerInterface::class, $previous);
        }
    }

    public function testMalformedPermissionValuesDenyUseWithoutBreakingTheBuilder(): void
    {
        $this->configure(['block_permissions' => ['embed' => ['pages.edit'], 'map' => new stdClass()]]);
        $previous = log();
        $logger   = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning');
        app()->container()->set(LoggerInterface::class, $logger);

        try {
            $registry = $this->registry();
            foreach (['embed', 'map'] as $id) {
                self::assertFalse($registry->isPermitted($id, $this->actor(...Permission::cases())));
                self::assertFalse($registry->isPermitted($id, null));
            }
        } finally {
            app()->container()->set(LoggerInterface::class, $previous);
        }
    }

    public function testContainersAndChromeRegionsAreDerivedFromTheTypes(): void
    {
        $registry = $this->registry();

        foreach (['section', 'stack', 'columns', 'grid'] as $container) {
            self::assertTrue($registry->isContainer($container), $container);
        }
        self::assertFalse($registry->isContainer('text'));
        self::assertFalse($registry->isContainer('unknown'));

        self::assertSame(['header'], $registry->chromeRegions('header'));
        self::assertSame(['footer'], $registry->chromeRegions('footer'));
        self::assertSame(['footer'], $registry->chromeRegions('navigation'));
        self::assertSame(['footer'], $registry->chromeRegions('text'));
        self::assertSame([], $registry->chromeRegions('hero'));
    }

    public function testBuilderTypesCarryTheRulesForTheActor(): void
    {
        $registry = $this->registry()->disable('map')->restrict('embed', Permission::THEMES_CODE_MANAGE);

        $types = array_column($registry->builderTypes($this->actor(Permission::PAGES_EDIT)), null, 'type');

        self::assertCount(count($registry->all()), $types, 'every registered type stays known to the builder');
        self::assertTrue($types['text']['insertable']);
        self::assertTrue($types['text']['editable']);
        self::assertFalse($types['map']['insertable']);
        self::assertTrue($types['map']['editable'], 'disabled, but existing map blocks stay editable');
        self::assertFalse($types['embed']['insertable']);
        self::assertFalse($types['embed']['editable']);
        self::assertTrue($types['section']['container']);
        self::assertFalse($types['text']['container']);
        self::assertSame(['header'], $types['header']['chromeRegions']);

        $admin = array_column($registry->builderTypes($this->actor(Permission::THEMES_CODE_MANAGE)), null, 'type');
        self::assertTrue($admin['embed']['insertable']);
    }

    private function registry(): BlockTypeRegistry
    {
        return (new BlockTypeRegistry())->addMany(CoreBlockTypes::all());
    }

    private function actor(Permission ...$permissions): UserHelper
    {
        return new UserHelper(null, $permissions, [], true);
    }

    /**
     * @param array<string, mixed> $rules
     */
    private function configure(array $rules): void
    {
        $values                        = $this->previousConfig->all();
        $values['cms']['page_builder'] = [...($values['cms']['page_builder'] ?? []), ...$rules];
        app()->container()->set(Config::class, new Config($values));
    }

    private function blockType(string $id): AbstractBlockType
    {
        return new class ($id) extends AbstractBlockType {
            public function __construct(private readonly string $typeId)
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
        };
    }
}
