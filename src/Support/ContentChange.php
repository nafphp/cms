<?php

declare(strict_types=1);

namespace Naf\CMS\Support;

use Naf\CMS\Events\ContentEvent;

/**
 * One place for a completed content change: the audit entry and the matching
 * content event are written together, from the same snapshots, so the two can
 * no longer drift apart or disagree about what happened.
 *
 * @internal
 */
final class ContentChange
{
    /**
     * @param array<string, mixed> $meta Audit-only context
     */
    public static function record(
        string $action,
        string $subjectType,
        string|int $subjectId,
        mixed $before = null,
        mixed $after = null,
        array $meta = [],
    ): void {
        AuditEntry::create($action, $subjectType, (string) $subjectId, $before, $after, $meta)->record();
        ContentEvent::dispatch($action, $subjectType, $subjectId, $before, $after);
    }

    /**
     * Whether a save moved the subject into the published state
     *
     * Saving with `status = published` is how the page builder and the
     * navigation form publish, so a save has to be able to count as a
     * publication and not only as an update.
     */
    public static function becamePublished(mixed $before, mixed $after, string $field = 'status'): bool
    {
        $now = is_array($after) ? (string) ($after[$field] ?? '') : '';
        $was = is_array($before) ? (string) ($before[$field] ?? '') : '';

        return $now === 'published' && $was !== 'published';
    }
}
