<?php

declare(strict_types=1);

namespace Naf\CMS\Controllers\Api;

use Naf\CMS\Api\ApiAuthorization;
use Naf\CMS\Api\ApiEndpoint;
use Naf\CMS\Support\HandlerInvoker;
use Psr\Http\Message\ResponseInterface;
use function Naf\request;
use function Naf\route;
use function Naf\CMS\cms;

final class ApiController
{
    public function __construct(
        private readonly ApiAuthorization $authorization = new ApiAuthorization(),
    ) {}

    public function handle(mixed ...$params): ResponseInterface
    {
        $endpoint = $this->resolveEndpoint();
        $this->authorization->authorize($endpoint);

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
