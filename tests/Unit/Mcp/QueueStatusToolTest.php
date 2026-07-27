<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use src\Mcp\Tools\QueueStatusTool;
use PHPUnit\Framework\TestCase;

final class QueueStatusToolTest extends TestCase
{
    private string $queuePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queuePath = sys_get_temp_dir() . '/nixcms-queue-tool-' . bin2hex(random_bytes(8));
        mkdir($this->queuePath . '/default', 0775, true);
        mkdir($this->queuePath . '/mail', 0775, true);
        mkdir($this->queuePath . '/deadletter/default', 0775, true);

        touch($this->queuePath . '/default/one.job');
        touch($this->queuePath . '/default/two.job');
        touch($this->queuePath . '/default/worker.job.lock');
        touch($this->queuePath . '/mail/mail.job');
        touch($this->queuePath . '/deadletter/default/dead.job');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->queuePath);

        parent::tearDown();
    }

    public function testReturnsCountsForOneChannel(): void
    {
        $result = (new QueueStatusTool($this->queuePath))->handle(['channel' => 'default']);

        self::assertSame('default', $result['channel']);
        self::assertSame(['default', 'mail'], $result['available_channels']);
        self::assertSame(2, $result['pending_jobs']);
        self::assertSame(1, $result['active_workers']);
        self::assertSame(1, $result['deadletter_jobs']);
    }

    public function testReturnsCountsForAllChannels(): void
    {
        $result = (new QueueStatusTool($this->queuePath))->handle(['channel' => 'all']);

        self::assertSame('all', $result['channel']);
        self::assertSame(3, $result['pending_jobs']);
        self::assertSame(1, $result['active_workers']);
        self::assertSame(1, $result['deadletter_jobs']);
    }

    public function testRejectsUnsafeChannelNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new QueueStatusTool($this->queuePath))->handle(['channel' => '../storage']);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }

            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($path);
    }
}
