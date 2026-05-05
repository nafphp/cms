<?php

declare(strict_types=1);

use NixCMS\Api\ApiEndpoint;
use NixCMS\Enums\Permission;
use NixCMS\Handlers\Api\Ai\CallAiTool;
use NixCMS\Handlers\Api\Ai\ListAiTools;
use NixCMS\Handlers\Api\Ai\SelectAiTools;

return [
    ApiEndpoint::get('api.admin.ai.tools', '/api/admin/ai/tools')
        ->requirePermission(Permission::MCP_TOOLS_CALL)
        ->setHandler(ListAiTools::class),

    ApiEndpoint::post('api.admin.ai.tools.select', '/api/admin/ai/tools/select')
        ->requirePermission(Permission::MCP_TOOLS_CALL)
        ->setHandler(SelectAiTools::class),

    ApiEndpoint::post('api.admin.ai.tools.call', '/api/admin/ai/tools/call')
        ->requirePermission(Permission::MCP_TOOLS_CALL)
        ->setHandler(CallAiTool::class),
];
