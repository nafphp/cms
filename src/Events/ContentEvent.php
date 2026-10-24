<?php

declare(strict_types=1);

namespace Naf\CMS\Events;

use function Naf\event;

/**
 * A completed CMS content change
 *
 * Listen with event()->listen() and a CmsEvent constant. The event is
 * dispatched isolated: an exception in one listener is logged by the event
 * manager, does not undo the CMS change and does not stop the other listeners.
 *
 * @api
 */
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

        // The change has already been saved; its listeners only react to it.
        event()->isolated()->dispatch($contentEvent->name(), $contentEvent);
    }
}
