<?php

declare(strict_types=1);

use Naf\CMS\Events\CmsEvent;
use Naf\CMS\Events\ContentEvent;

use function Naf\event;
use function Naf\log;

event()->listen(CmsEvent::PAGE_PUBLISHED, static function (ContentEvent $event): void {
    log()->info('Example page published listener', ['pageId' => $event->subjectId]);
});
