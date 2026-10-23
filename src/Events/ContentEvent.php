<?php

declare(strict_types=1);

namespace Naf\CMS\Events;

use Throwable;

use function Naf\event;
use function Naf\log;

/** @api */
final readonly class ContentEvent
{
    public function __construct(
        public string $action,
        public string $subjectType,
        public string $subjectId,
        public mixed $before = null,
        public mixed $after = null,
    ) {
    }

    public function name(): string
    {
        return 'cms.' . $this->action;
    }

    public static function dispatch(string $action, string $subjectType, string|int $subjectId, mixed $before = null, mixed $after = null): void
    {
        $contentEvent = new self($action, $subjectType, (string) $subjectId, $before, $after);

        try {
            event()->dispatch($contentEvent->name(), $contentEvent);
        } catch (Throwable $exception) {
            log()->error('CMS content event listener failed', ['event' => $contentEvent->name(), 'exception' => $exception]);
        }
    }
}
