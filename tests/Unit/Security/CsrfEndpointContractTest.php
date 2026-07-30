<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use function NixPHP\CMS\cms;

final class CsrfEndpointContractTest extends TestCase
{
    public function testNoPatchEndpointsUntilFrameworkProtectsThem(): void
    {
        $checked = 0;
        foreach (cms()->api()->all() as $id => $endpoint) {
            $checked++;
            self::assertNotSame('PATCH', $endpoint->method(), sprintf(
                'Endpoint "%s" uses PATCH, which the current nixphp/form CSRF listener does not protect.',
                $id,
            ));
        }
        self::assertGreaterThan(0, $checked, 'No API endpoints were registered for the CSRF contract check.');
    }
}
