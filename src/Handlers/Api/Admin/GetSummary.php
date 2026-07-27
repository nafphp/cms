<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Admin;

use NixPHP\CMS\Services\Admin\AdminDashboardService;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;

final class GetSummary
{
    public function __invoke(): ResponseInterface
    {
        return json((new AdminDashboardService())->summary());
    }
}
