<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Docs;

use FilesystemIterator;
use Naf\CMS\Content\Page\Block\BlockTypeInterface;
use Naf\CMS\Support\Docs\IntegrityChecker;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class IntegrityCheckerTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/nafcms-integrity-' . bin2hex(random_bytes(6));
        $this->mkdir('src/Resources/pages');
        $this->mkdir('views/page/blocks');
        $this->mkdir('views/page/templates');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);
    }

    public function testCheckReportsAndSortsDriftIssues(): void
    {
        $blockTypes = [
            new class implements BlockTypeInterface {
                public function id(): string
                {
                    return 'hero';
                }

                public function label(): string
                {
                    return 'Hero';
                }

                public function description(): string
                {
                    return '';
                }

                public function regions(): array
                {
                    return ['main'];
                }

                public function previewTitle(): string
                {
                    return 'Hero';
                }

                public function defaults(): array
                {
                    return [];
                }

                public function fields(): array
                {
                    return [];
                }

                public function unique(): bool
                {
                    return false;
                }

                public function contentTypes(): array
                {
                    return [];
                }

                public function templateKinds(): array
                {
                    return [];
                }

                public function toBuilderArray(): array
                {
                    return [];
                }
            },
        ];
        $this->write('src/Resources/pages/page_templates.php', <<<'PHP'
        <?php

        return [
            [
                'id' => 'landing',
                'view' => 'page.templates.missing',
                'blocks' => [
                    [
                        'id' => 'hero_slot',
                        'accepts' => ['hero', 'ghost'],
                        'defaultContentType' => 'phantom',
                    ],
                ],
            ],
        ];
        PHP);
        $this->write('views/page/blocks/orphan.phtml', '<div>orphan</div>');
        $this->write('views/page/templates/orphan.phtml', '<main>orphan</main>');

        $issues   = (new IntegrityChecker($this->basePath, $blockTypes))->check();
        $messages = array_column($issues, 'message');

        self::assertContains('BlockType "hero" maps to missing view "page.blocks.hero" (views/page/blocks/hero.phtml).', $messages);
        self::assertContains('Block view "views/page/blocks/orphan.phtml" is not referenced by any registered BlockType.', $messages);
        self::assertContains('Page template "landing" maps to missing view "page.templates.missing" (views/page/templates/missing.phtml).', $messages);
        self::assertContains('Page template view "views/page/templates/orphan.phtml" is not registered in src/Resources/pages/page_templates.php.', $messages);
        self::assertContains('Page template "landing" block "hero_slot" accepts unknown BlockType "ghost".', $messages);
        self::assertContains('Page template "landing" block "hero_slot" defaults to unknown BlockType "phantom".', $messages);

        $sorted = $issues;
        usort($sorted, static fn(array $a, array $b): int => [$a['file'], $a['line'], $a['message']] <=> [$b['file'], $b['line'], $b['message']]);
        self::assertSame($sorted, $issues);
    }

    private function mkdir(string $path): void
    {
        $directory = $this->basePath . '/' . $path;
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    private function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        if ($directory !== '.') {
            $this->mkdir($directory);
        }

        file_put_contents($this->basePath . '/' . $path, $contents);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir((string) $file) : unlink((string) $file);
        }

        rmdir($path);
    }
}
