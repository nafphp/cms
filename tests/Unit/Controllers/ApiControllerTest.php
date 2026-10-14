<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use Naf\CMS\Api\ApiEndpoint;
use Naf\CMS\Controllers\Api\ApiController;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

use function Naf\app;
use function Naf\CMS\cms;
use function Naf\route;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ApiControllerTest extends TestCase
{
    public function testResolvesCurrentNamedEndpoint(): void
    {
        $this->setCurrentRoute('api.admin.translations.show');

        $endpoint = $this->resolve();

        self::assertSame('api.admin.translations.show', $endpoint->id());
    }

    public function testFallsBackToRequestPathAndMethod(): void
    {
        $this->setCurrentRoute(null);
        $this->setRequest(new ServerRequest('GET', '/api/admin/summary'));

        self::assertSame('api.admin.summary', $this->resolve()->id());
    }

    public function testThrowsWhenNoEndpointMatches(): void
    {
        $this->setCurrentRoute(null);
        $this->setRequest(new ServerRequest('GET', '/api/does-not-exist'));

        $this->expectException(RuntimeException::class);
        $this->resolve();
    }

    private function resolve(): ApiEndpoint
    {
        self::assertNotNull(cms()->api()->get('api.admin.translations.show'));
        $method = new ReflectionMethod(ApiController::class, 'resolveEndpoint');

        return $method->invoke(new ApiController());
    }

    private function setCurrentRoute(?string $name): void
    {
        $property = new ReflectionProperty(route(), 'currentName');
        $property->setValue(route(), $name);
    }

    private function setRequest(ServerRequest $request): void
    {
        app()->container()->set(RequestInterface::class, $request);
        app()->container()->set(ServerRequestInterface::class, $request);
    }
}
