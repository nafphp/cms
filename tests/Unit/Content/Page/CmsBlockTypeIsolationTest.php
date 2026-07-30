<?php

declare(strict_types=1);

namespace Tests\Unit\Content\Page;

use NixPHP\CMS\Content\Page\Block\AbstractBlockType;
use Tests\Support\CmsTestCase;
use function NixPHP\CMS\cms;

final class CmsBlockTypeIsolationTest extends CmsTestCase
{
    public function testPluginBlockRegistrationIsConfinedToTheTestRequest(): void
    {
        cms()->blockTypes()->add(new class extends AbstractBlockType {
            public function id(): string
            {
                return 'test_probe';
            }

            public function label(): string
            {
                return 'Test probe';
            }

            public function regions(): array
            {
                return ['main'];
            }
        });

        self::assertSame('test_probe', cms()->blockTypes()->require('test_probe')->id());
    }
}
