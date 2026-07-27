<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use NixPHP\CMS\Storage\Criteria;
use PHPUnit\Framework\TestCase;

final class CriteriaTest extends TestCase
{
    public function testPageSetsLimitAndOffset(): void
    {
        $criteria = Criteria::create()->page(2, 10);

        self::assertSame(10, $criteria->limitValue());
        self::assertSame(10, $criteria->offsetValue());
    }

    public function testPageUsesDefaultPerPage(): void
    {
        $criteria = Criteria::create()->page(3);

        self::assertSame(20, $criteria->limitValue());
        self::assertSame(40, $criteria->offsetValue());
    }
}
