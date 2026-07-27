<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Api\Media;

use NixPHP\CMS\Handlers\Api\Media\ServeMedia;
use PHPUnit\Framework\TestCase;

final class ServeMediaTest extends TestCase
{
    public function testPublicMediaCanBeDownloaded(): void
    {
        self::assertTrue($this->canAccess(['visibility' => 'public']));
    }

    public function testPrivateMediaCannotBeDownloaded(): void
    {
        self::assertFalse($this->canAccess(['visibility' => 'private']));
    }

    /**
     * @param array<string, mixed> $media
     */
    private function canAccess(array $media): bool
    {
        $method = new \ReflectionMethod(ServeMedia::class, 'canAccess');
        return (bool) $method->invoke(new ServeMedia(), $media);
    }
}
