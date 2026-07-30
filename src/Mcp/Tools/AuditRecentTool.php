<?php

declare(strict_types=1);

namespace NixPHP\CMS\Mcp\Tools;

use NixPHP\CMS\Contracts\LocalAiToolMetadataInterface;
use NixPHP\CMS\Enums\ToolRisk;

use NixPHP\CMS\Repositories\AuditLogRepository;
use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use Throwable;

final class AuditRecentTool implements ToolInterface, ScopedToolInterface, LocalAiToolMetadataInterface
{
    use DeclaresLocalAiToolRisk;

    private const ToolRisk AI_RISK = ToolRisk::READ;
    private const bool AI_DESTRUCTIVE = false;
    public function __construct(private ?AuditLogRepository $auditLog = null) {}

    public function name(): string
    {
        return 'cms_audit_recent';
    }

    public function description(): string
    {
        return 'Returns recent CMS audit log events, a chronological activity log of admin actions and system changes, without exposing old/new value payloads.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('subject_type', Schema::string()->description('Optional subject type filter.')->default(''))
            ->prop('limit', Schema::integer()->min(1)->max(50)->default(10))
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['system:read'];
    }

    public function handle(array $args): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 10)));
        $subjectType = trim((string) ($args['subject_type'] ?? ''));

        try {
            $records = $this->auditLog()
                ->latest($limit, $subjectType !== '' ? $subjectType : null);

            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => true,
                'subject_type' => $subjectType !== '' ? $subjectType : 'all',
                'total_returned' => count($records),
                'events' => array_map($this->summarizeRecord(...), $records),
            ];
        } catch (Throwable $throwable) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database_available' => false,
                'error' => $throwable->getMessage(),
                'subject_type' => $subjectType !== '' ? $subjectType : 'all',
                'total_returned' => 0,
                'events' => [],
            ];
        }
    }

    private function auditLog(): AuditLogRepository
    {
        return $this->auditLog ?? new AuditLogRepository();
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function summarizeRecord(array $record): array
    {
        return [
            'id' => (int) ($record['id'] ?? 0),
            'action' => (string) ($record['action'] ?? ''),
            'subject_type' => (string) ($record['subject_type'] ?? ''),
            'subject_key' => (string) ($record['subject_key'] ?? ''),
            'actor_label' => (string) ($record['actor_label'] ?? ''),
            'actor_user_id' => $record['actor_user_id'] ?? null,
            'created_at' => (string) ($record['created_at'] ?? ''),
            'meta' => $record['meta'] ?? [],
        ];
    }
}
