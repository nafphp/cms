<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

abstract class CmsTestCase extends TestCase
{
    private \Naf\CMS\Support\Cms $previousCms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCms = \Naf\CMS\cms();
        \Naf\CMS\cms(override: new \Naf\CMS\Support\Cms());
        \Naf\CMS\user(reset: true);
        \Naf\CMS\audit(reset: true);
    }

    protected function tearDown(): void
    {
        \Naf\CMS\cms(override: $this->previousCms);
        \Naf\CMS\user(reset: true);
        \Naf\CMS\audit(reset: true);
        parent::tearDown();
    }
}
