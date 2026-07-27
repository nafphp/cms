<?php

declare(strict_types=1);

use NixPHP\CMS\Api\ApiEndpoint;
use NixPHP\CMS\Enums\Permission;
use NixPHP\CMS\Handlers\Api\Ai\AppendAiMemory;
use NixPHP\CMS\Handlers\Api\Ai\CallAiTool;
use NixPHP\CMS\Handlers\Api\Ai\DeleteAiMistakes;
use NixPHP\CMS\Handlers\Api\Ai\ListAiTools;
use NixPHP\CMS\Handlers\Api\Ai\ListAiPromptFiles;
use NixPHP\CMS\Handlers\Api\Ai\RecordAiMistake;
use NixPHP\CMS\Handlers\Api\Ai\ReviewAiMistakes;
use NixPHP\CMS\Handlers\Api\Ai\SaveAiPromptFile;
use NixPHP\CMS\Handlers\Api\Ai\SelectAiTools;

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
        ->requirePermission(Permission::AI_INSTRUCTIONS_MANAGE)
        ->setHandler(RecordAiMistake::class),

    ApiEndpoint::post('api.admin.ai.mistakes.delete', '/api/admin/ai/mistakes/delete')
        ->requirePermission(Permission::AI_INSTRUCTIONS_MANAGE)
        ->setHandler(DeleteAiMistakes::class),

    ApiEndpoint::get('api.admin.ai.mistakes.review', '/api/admin/ai/mistakes/review')
        ->requirePermission(Permission::AI_INSTRUCTIONS_MANAGE)
        ->setHandler(ReviewAiMistakes::class),

    ApiEndpoint::post('api.admin.ai.memory.append', '/api/admin/ai/memory')
        ->requirePermission(Permission::AI_INSTRUCTIONS_MANAGE)
        ->setHandler(AppendAiMemory::class),

    ApiEndpoint::get('api.admin.ai.prompts', '/api/admin/ai/prompts')
        ->requirePermission(Permission::AI_INSTRUCTIONS_MANAGE)
        ->setHandler(ListAiPromptFiles::class),

    ApiEndpoint::post('api.admin.ai.prompts.save', '/api/admin/ai/prompts')
        ->requirePermission(Permission::AI_INSTRUCTIONS_MANAGE)
        ->setHandler(SaveAiPromptFile::class),
];
