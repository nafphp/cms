<?php

declare(strict_types=1);

namespace src\Handlers\Api\Admin;

use src\Services\Admin\AdminDashboardService;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;

final class GetSummary
{
    public function __invoke(): ResponseInterface
    {
        return json((new AdminDashboardService())->summary());
    }
}
