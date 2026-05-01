<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use NixCMS\Support\Cms;
use PHPUnit\Framework\TestCase;

final class CmsTest extends TestCase
{
    public function testCmsThrowsForUnsupportedContentType(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('No content type registered for "stdClass". Register one through cms()->contentTypes()->add(...).');

        $cms = new Cms();
        $cms->content(\stdClass::class);
    }
}
