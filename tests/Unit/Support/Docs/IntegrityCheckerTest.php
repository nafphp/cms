<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Docs;

use FilesystemIterator;
use Naf\CMS\Content\Page\Block\AbstractBlockType;
use Naf\CMS\Page\Layout;
use Naf\CMS\Page\Template;
use Naf\CMS\Support\Docs\IntegrityChecker;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\CmsTestCase;

use function Naf\CMS\cms;

final class IntegrityCheckerTest extends CmsTestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->basePath = sys_get_temp_dir() . '/nafcms-integrity-' . bin2hex(random_bytes(6));
        mkdir($this->basePath . '/views/page/templates', 0777, true);
        mkdir($this->basePath . '/views/page/blocks', 0777, true);
        foreach (array_keys(cms()->pageTemplates()->all()) as $id) {
            cms()->pageTemplates()->remove($id);
        }
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->basePath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir((string) $file) : unlink((string) $file);
        }
        rmdir($this->basePath);
        parent::tearDown();
    }

    public function testHostReportsMissingViewsAnchorAndSlotDriftWithoutOrphanChecks(): void
    {
        file_put_contents($this->basePath . '/views/page/templates/broken.phtml', "<main>\n<?= \$block('undeclared') ?>\n</main>");
        file_put_contents($this->basePath . '/views/page/templates/orphan.phtml', '<main>Unused host view</main>');
        cms()->pageTemplates()->add(Layout::define('broken', 'Broken', Template::create('broken'))
            ->addBlock('missing', 'page.blocks.empty', ['accepts' => ['ghost'], 'defaultContentType' => 'phantom']));
        cms()->pageTemplates()->add(Layout::define('missing-view', 'Missing', Template::create('missing_view')));
        cms()->blockTypes()->add(new class extends AbstractBlockType {
            public function id(): string
            {
                return 'missing-block-view';
            }

            public function label(): string
            {
                return 'Missing';
            }

            public function regions(): array
            {
                return ['main'];
            }
        });
        $issues   = (new IntegrityChecker($this->basePath))->check();
        $messages = array_column($issues, 'message');
        self::assertContains('Page template "broken" is missing id="content".', $messages);
        self::assertContains('Page template "broken" calls undeclared slot "undeclared".', $messages);
        self::assertContains('Page template "broken" does not render declared slot "missing".', $messages);
        self::assertContains('Page template "missing-view" maps to missing view "page.templates.missing_view".', $messages);
        self::assertContains('BlockType "missing-block-view" has no resolvable view "page.blocks.missing_block_view".', $messages);
        self::assertContains('Page template "broken" block "missing" accepts unknown BlockType "ghost".', $messages);
        self::assertContains('Page template "broken" block "missing" defaults to unknown BlockType "phantom".', $messages);
        self::assertStringNotContainsString('orphan', implode('\n', $messages));
        $call = array_values(array_filter($issues, static fn(array $issue): bool => str_contains($issue['message'], 'calls undeclared')))[0];
        self::assertSame(2, $call['line']);
        $sorted = $issues;
        usort($sorted, static fn(array $a, array $b): int => [$a['file'], $a['line'], $a['message']] <=> [$b['file'], $b['line'], $b['message']]);
        self::assertSame($sorted, $issues);
    }

    public function testHostAcceptsMatchingSlotsAndCustomTemplatesWithDynamicSlots(): void
    {
        file_put_contents($this->basePath . '/views/page/templates/good.phtml', '<main id="content" tabindex="-1"><?= $block("main") ?></main>');
        cms()->pageTemplates()->add(Layout::define('good', 'Good', Template::create('good'))->addBlock('main', 'page.blocks.empty'));
        file_put_contents($this->basePath . '/views/page/templates/dynamic.phtml', '<main id="content"><?= $block($id) ?></main>');
        cms()->pageTemplates()->add(Layout::define('dynamic', 'Dynamic', Template::create('dynamic'))->setKind('custom')->addBlock('anything', 'page.blocks.empty'));
        self::assertSame([], (new IntegrityChecker($this->basePath))->check());
    }
}
