<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

abstract class CmsTestCase extends TestCase
{
    private \NixPHP\CMS\Support\Cms $previousCms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCms = \NixPHP\CMS\cms();
        \NixPHP\CMS\cms(override: new \NixPHP\CMS\Support\Cms());
        \NixPHP\CMS\user(reset: true);
        \NixPHP\CMS\audit(reset: true);
    }

    protected function tearDown(): void
    {
        \NixPHP\CMS\cms(override: $this->previousCms);
        \NixPHP\CMS\user(reset: true);
        \NixPHP\CMS\audit(reset: true);
        parent::tearDown();
    }
}
