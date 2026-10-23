<?php

declare(strict_types=1);

namespace Tests\Examples;

use Naf\CMS\Content\ContentTypeInterface;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Events\ContentEvent;
use Naf\CMS\Examples\BlockType\ExampleQuoteBlockType;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function Naf\CMS\cms;
use function Naf\MCP\tool;

final class ExamplesSmokeTest extends TestCase
{
    public function testEveryExampleDirectoryBoots(): void
    {
        $directories = $this->exampleDirectories();
        self::assertNotEmpty($directories);

        foreach ($directories as $directory) {
            $this->requireExample($directory);
        }

    }

    public function testPageWithPermissionExampleRegistersExpectedPage(): void
    {
        $this->requireExample(BASE_PATH . '/examples/01-page-with-permission');

        $matches = array_values(array_filter(
            cms()->pages()->all(),
            static fn($page): bool => $page->path() === '/admin/examples/reports',
        ));

        self::assertCount(1, $matches);
        self::assertSame('example.reports', $matches[0]->id());
        self::assertSame([Permission::ARTICLES_VIEW], $matches[0]->requiredPermissions());
    }

    public function testCustomContentTypeExampleRegistersExpectedContentType(): void
    {
        $this->requireExample(BASE_PATH . '/examples/02-custom-content-type');

        $type = cms()->contentTypes()->get('example_product');

        self::assertInstanceOf(ContentTypeInterface::class, $type);
        self::assertSame('Example product', $type->label());
        self::assertSame('Naf\\CMS\\Examples\\CustomContentType\\ExampleProduct', $type->modelClass());
    }

    public function testBlockTypeExampleRegistersExpectedType(): void
    {
        $this->requireExample(BASE_PATH . '/examples/03-block-type');

        $type = cms()->blockTypes()->get('example_quote');

        self::assertInstanceOf(ExampleQuoteBlockType::class, $type);
        self::assertSame('Example quote', $type->label());
    }

    public function testPageTemplateExampleRegistersExpectedTemplate(): void
    {
        $this->requireExample(BASE_PATH . '/examples/07-page-template');

        $template = cms()->pageTemplates()->require('example-split');

        self::assertSame('Example split', $template->label());
        self::assertSame('page.templates.example_split', $template->templateView());
        self::assertSame(['site_header', 'intro', 'content', 'aside', 'site_footer'], array_keys($template->blocks()));
    }

    public function testMcpToolExampleRegistersExpectedTool(): void
    {
        $this->requireExample(BASE_PATH . '/examples/04-mcp-tool');

        $definitions = tool()->definitions();
        $matches     = array_values(array_filter(
            $definitions,
            static fn(array $definition): bool => ($definition['name'] ?? null) === 'cms_example_ping',
        ));

        self::assertCount(1, $matches);
        self::assertSame('Returns a trivial pong response for testing MCP registration.', $matches[0]['description']);
    }

    public function testContentEventExampleLogsPublishedPage(): void
    {
        $this->requireExample(BASE_PATH . '/examples/08-content-events');
        $previous = \Naf\log();
        $logger   = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('Example page published listener', ['pageId' => 'example-42']);
        \Naf\app()->container()->set(LoggerInterface::class, $logger);

        try {
            ContentEvent::dispatch('page.published', 'page', 'example-42');
        } finally {
            \Naf\app()->container()->set(LoggerInterface::class, $previous);
        }
    }

    /**
     * @return array<int, string>
     */
    private function exampleDirectories(): array
    {
        $directories = glob(BASE_PATH . '/examples/*', GLOB_ONLYDIR) ?: [];
        sort($directories);

        return $directories;
    }

    private function requireExample(string $directory): void
    {
        $files    = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        foreach ($files as $file) {
            require_once $file;
        }
    }
}
