<?php

declare(strict_types=1);

namespace Tests\Unit\Page;

use InvalidArgumentException;
use LogicException;
use Naf\CMS\Page\Layout;
use Naf\CMS\Page\PageTemplateRegistry;
use Naf\CMS\Page\Template;
use RuntimeException;
use Tests\Support\CmsTestCase;

use function Naf\CMS\cms;

final class PageTemplateRegistryTest extends CmsTestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function testCmsExposesTheShippedTemplates(): void
    {
        $ids = array_keys(cms()->pageTemplates()->all());

        foreach (['default', 'landing', 'editorial', 'two-column', 'feature-grid', 'article-detail', 'full-bleed', 'custom'] as $id) {
            self::assertContains($id, $ids);
        }

        self::assertSame('page.templates.two_column', cms()->pageTemplates()->require('two-column')->templateView());
    }

    public function testPluginTemplatesAreAddedAfterTheDefaults(): void
    {
        $registry = new PageTemplateRegistry($this->templateFile(['default' => 'Default']));

        $registry->add($this->layout('plugin-template', 'Plugin template'));

        self::assertSame(['default', 'plugin-template'], array_keys($registry->all()));
        self::assertSame('Plugin template', $registry->require('plugin-template')->label());
    }

    public function testDuplicateIdsNeedAnExplicitReplace(): void
    {
        $registry = new PageTemplateRegistry($this->templateFile(['default' => 'Default']));

        try {
            $registry->add($this->layout('default', 'Plugin default'));
            self::fail('Registering a duplicate template id must fail.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('replace: true', $exception->getMessage());
        }

        $registry->add($this->layout('default', 'Plugin default'), replace: true);

        self::assertSame('Plugin default', $registry->require('default')->label());
    }

    public function testHostTemplatesWinRegardlessOfPluginOrder(): void
    {
        $registry = new PageTemplateRegistry(
            $this->templateFile(['default' => 'Default']),
            $this->templateFile(['default' => 'Host default', 'host-only' => 'Host only']),
        );

        $registry->add($this->layout('host-only', 'Plugin version'));
        self::assertSame('Host only', $registry->require('host-only')->label());

        $registry->add($this->layout('default', 'Late plugin default'), replace: true);
        self::assertSame('Host default', $registry->require('default')->label());
    }

    public function testHostFileIdenticalToTheDefaultsIsNotReadTwice(): void
    {
        $file     = $this->templateFile(['default' => 'Default']);
        $registry = new PageTemplateRegistry($file, $file);

        self::assertCount(1, $registry->definitions());
    }

    public function testRemoveHidesATemplateUntilItIsRegisteredAgain(): void
    {
        $registry = new PageTemplateRegistry($this->templateFile(['default' => 'Default', 'landing' => 'Landing']));

        $registry->remove('landing');
        self::assertFalse($registry->has('landing'));
        self::assertSame(['default'], array_column($registry->definitions(), 'id'));

        $registry->add($this->layout('landing', 'Plugin landing'));
        self::assertTrue($registry->has('landing'));
    }

    public function testLoadAcceptsLayoutsAndDefinitionArrays(): void
    {
        $file = $this->writeFile(<<<'PHP'
        <?php

        use Naf\CMS\Page\Layout;
        use Naf\CMS\Page\Template;

        return [
            Layout::define('from-layout', 'From layout', Template::create('default')->setParent('page'))
                ->addBlock('main', 'page.blocks.empty', ['label' => 'Main', 'region' => 'main']),
            [
                'id'     => 'from-array',
                'label'  => 'From array',
                'layout' => 'layouts.page',
                'view'   => 'page.templates.default',
                'blocks' => [['id' => 'main', 'label' => 'Main', 'region' => 'main']],
                'routePattern' => '/things/{slug}',
            ],
        ];
        PHP);

        $registry = (new PageTemplateRegistry())->load($file);

        $definitions = array_column($registry->definitions(), null, 'id');
        self::assertSame('page.templates.default', $definitions['from-array']['view']);
        self::assertSame('layouts.page', $definitions['from-array']['layout']);
        self::assertSame('/things/{slug}', $definitions['from-array']['routePattern']);
        self::assertSame(
            [['id' => 'main', 'deletable' => false, 'label' => 'Main', 'region' => 'main']],
            $definitions['from-array']['blocks'],
        );
        self::assertSame($definitions['from-layout']['blocks'], $definitions['from-array']['blocks']);
    }

    public function testLoadRejectsAMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PageTemplateRegistry())->load(sys_get_temp_dir() . '/nafcms-missing-' . bin2hex(random_bytes(4)) . '.php');
    }

    public function testTemplatesNeedAnIdAndAView(): void
    {
        $registry = new PageTemplateRegistry();

        try {
            $registry->add(Layout::create(Template::create('default')));
            self::fail('A template without id must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('needs an id', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a view');
        $registry->add(Layout::define('viewless', 'Viewless', Template::create()));
    }

    public function testRequireFailsForUnknownTemplates(): void
    {
        $this->expectException(RuntimeException::class);

        (new PageTemplateRegistry())->require('missing');
    }

    private function layout(string $id, string $label): Layout
    {
        return Layout::define($id, $label, Template::create('default')->setParent('page'))
            ->addBlock('main', 'page.blocks.empty', ['label' => 'Main', 'region' => 'main']);
    }

    /**
     * @param array<string, string> $labels
     */
    private function templateFile(array $labels): string
    {
        $entries = '';
        foreach ($labels as $id => $label) {
            $entries .= sprintf(
                "    Layout::define(%s, %s, Template::create('default')->setParent('page')),\n",
                var_export($id, true),
                var_export($label, true),
            );
        }

        return $this->writeFile("<?php\n\nuse Naf\\CMS\\Page\\Layout;\nuse Naf\\CMS\\Page\\Template;\n\nreturn [\n" . $entries . "];\n");
    }

    private function writeFile(string $contents): string
    {
        $file = sys_get_temp_dir() . '/nafcms-page-templates-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($file, $contents);
        $this->files[] = $file;

        return $file;
    }
}
