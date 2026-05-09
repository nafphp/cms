<?php

declare(strict_types=1);

use NixCMS\Api\ApiEndpoint;
use NixCMS\Enums\Permission;
use NixCMS\Handlers\Api\Ai\AppendAiMemory;
use NixCMS\Handlers\Api\Ai\CallAiTool;
use NixCMS\Handlers\Api\Ai\DeleteAiMistakes;
use NixCMS\Handlers\Api\Ai\ListAiTools;
use NixCMS\Handlers\Api\Ai\ListAiPromptFiles;
use NixCMS\Handlers\Api\Ai\RecordAiMistake;
use NixCMS\Handlers\Api\Ai\ReviewAiMistakes;
use NixCMS\Handlers\Api\Ai\SaveAiPromptFile;
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

    ApiEndpoint::post('api.admin.ai.mistakes', '/api/admin/ai/mistakes')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setHandler(RecordAiMistake::class),

    ApiEndpoint::post('api.admin.ai.mistakes.delete', '/api/admin/ai/mistakes/delete')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setHandler(DeleteAiMistakes::class),

    ApiEndpoint::get('api.admin.ai.mistakes.review', '/api/admin/ai/mistakes/review')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setHandler(ReviewAiMistakes::class),

    ApiEndpoint::post('api.admin.ai.memory.append', '/api/admin/ai/memory')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setHandler(AppendAiMemory::class),

    ApiEndpoint::get('api.admin.ai.prompts', '/api/admin/ai/prompts')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setHandler(ListAiPromptFiles::class),

    ApiEndpoint::post('api.admin.ai.prompts.save', '/api/admin/ai/prompts')
        ->requirePermission(Permission::ADMIN_ACCESS)
        ->setHandler(SaveAiPromptFile::class),
];
