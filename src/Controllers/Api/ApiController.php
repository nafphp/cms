<?php

declare(strict_types=1);

namespace NixPHP\CMS\Controllers\Api;

use NixPHP\CMS\Api\ApiAuthorization;
use NixPHP\CMS\Api\ApiEndpoint;
use NixPHP\CMS\Support\HandlerInvoker;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\request;
use function NixPHP\route;
use function NixPHP\CMS\cms;

final class ApiController
{
    public function handle(mixed ...$params): ResponseInterface
    {
        $endpoint = $this->resolveEndpoint();
        (new ApiAuthorization())->authorize($endpoint);

        $response = HandlerInvoker::call($endpoint->handler(), $params);
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        throw new \RuntimeException(sprintf(
            'API handler for endpoint "%s" must return a PSR response.',
            $endpoint->id()
        ));
    }

    private function resolveEndpoint(): ApiEndpoint
    {
        $routeName = route()->current();
        if (is_string($routeName) && $routeName !== '') {
            $endpoint = cms()->api()->get($routeName);
            if ($endpoint instanceof ApiEndpoint) {
                return $endpoint;
            }
        }

        $request = request();
        $endpoint = cms()->api()->findByPath($request->getUri()->getPath(), $request->getMethod());
        if ($endpoint instanceof ApiEndpoint) {
            return $endpoint;
        }

        throw new \RuntimeException('No registered API endpoint matches the current request.');
    }

}
