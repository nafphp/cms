<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Api\Media;

use Naf\CMS\Handlers\Api\Media\ServeMedia;
use PHPUnit\Framework\TestCase;

final class ServeMediaTest extends TestCase
{
    public function testPublicMediaCanBeDownloaded(): void
    {
        self::assertTrue($this->canAccess(['visibility' => 'public']));
    }

    public function testAnonymousVisitorCannotDownloadPrivateMedia(): void
    {
        self::assertFalse($this->canAccess(['visibility' => 'private']));
    }

    public function testPrivateMediaIsAvailableToUploaderOrMediaAdministrator(): void
    {
        $method = new \ReflectionMethod(ServeMedia::class, 'canAccessPrivate');
        $handler = new ServeMedia();
        $media = ['visibility' => 'private', 'uploaded_by' => 17];

        self::assertTrue($method->invoke($handler, $media, 17, false));
        self::assertTrue($method->invoke($handler, $media, 42, true));
        self::assertFalse($method->invoke($handler, $media, 42, false));
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
