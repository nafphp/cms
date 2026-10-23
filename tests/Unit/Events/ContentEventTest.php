<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use Naf\CMS\Api\FormApi;
use Naf\CMS\Events\CmsEvent;
use Naf\CMS\Events\ContentEvent;
use Naf\CMS\Page\PageRegistry;
use Naf\CMS\Repositories\FormRepository;
use Naf\Core\EventManager;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\DatabaseTestCase;

use function Naf\app;
use function Naf\event;
use function Naf\log;

final class ContentEventTest extends DatabaseTestCase
{
    private EventManager $previousEvents;
    private LoggerInterface $previousLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousEvents = event();
        $this->previousLogger = log();
        app()->container()->set(EventManager::class, new EventManager());
    }

    protected function tearDown(): void
    {
        app()->container()->set(EventManager::class, $this->previousEvents);
        app()->container()->set(LoggerInterface::class, $this->previousLogger);
        parent::tearDown();
    }

    public function testListenerReceivesContentEvent(): void
    {
        $received = null;
        event()->listen(CmsEvent::PAGE_PUBLISHED, static function (ContentEvent $event) use (&$received): void {
            $received = $event;
        });
        ContentEvent::dispatch('page.published', 'page', 42, ['status' => 'draft'], ['status' => 'published']);
        self::assertInstanceOf(ContentEvent::class, $received);
        self::assertSame('42', $received->subjectId);
        self::assertSame('cms.page.published', $received->name());
        self::assertSame(['status' => 'draft'], $received->before);
        self::assertSame(['status' => 'published'], $received->after);
    }

    public function testListenerFailureIsLoggedWithoutEscaping(): void
    {
        $exception = new RuntimeException('Listener failed');
        event()->listen(CmsEvent::PAGE_PUBLISHED, static fn() => throw $exception);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('CMS content event listener failed', [
            'event' => CmsEvent::PAGE_PUBLISHED, 'exception' => $exception,
        ]);
        app()->container()->set(LoggerInterface::class, $logger);
        ContentEvent::dispatch('page.published', 'page', 1);
    }

    public function testPageWritesDispatchAfterPersistence(): void
    {
        $this->createSchema(<<<'SQL'
            CREATE TABLE pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT, identifier TEXT NOT NULL, path TEXT NOT NULL,
                title TEXT NOT NULL, language TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'draft',
                template_id TEXT NOT NULL, theme_id TEXT NULL, blocks_json TEXT NOT NULL, meta_json TEXT NOT NULL,
                layout TEXT NOT NULL DEFAULT 'layouts.page', created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NULL
            )
        SQL);
        $events        = [];
        $observedPages = [];
        $pages         = new PageRegistry();
        foreach ([CmsEvent::PAGE_CREATED, CmsEvent::PAGE_UPDATED, CmsEvent::PAGE_PUBLISHED, CmsEvent::PAGE_DELETED] as $name) {
            event()->listen($name, static function (ContentEvent $event) use (&$events, &$observedPages, $pages): void {
                $events[]        = $event;
                $observedPages[] = $pages->find((int) $event->subjectId);
            });
        }
        $id = $pages->save(['identifier' => 'events', 'path' => '/events', 'title' => 'Events', 'template_id' => 'default']);
        $pages->save(['id' => $id, 'identifier' => 'events', 'path' => '/events', 'title' => 'Updated', 'template_id' => 'default']);
        self::assertTrue($pages->publish($id));
        self::assertTrue($pages->delete($id));
        self::assertSame(['page.created', 'page.updated', 'page.published', 'page.deleted'], array_map(static fn(ContentEvent $event): string => $event->action, $events));
        self::assertSame('Events', $observedPages[0]['title']);
        self::assertSame('Updated', $observedPages[1]['title']);
        self::assertSame('published', $observedPages[2]['status']);
        self::assertNull($observedPages[3]);
        self::assertNull($events[0]->before);
        self::assertSame('Updated', $events[1]->after['title']);
        self::assertSame('published', $events[2]->after['status']);
        self::assertNull($events[3]->after);
    }

    public function testStoredSubmissionDispatchesItsPersistedIdAndPayload(): void
    {
        $this->createSchema('CREATE TABLE form_submissions (id INTEGER PRIMARY KEY AUTOINCREMENT, form_id INTEGER, payload_json TEXT, meta_json TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $received  = null;
        $storedRow = null;
        event()->listen(CmsEvent::FORM_SUBMITTED, function (ContentEvent $event) use (&$received, &$storedRow): void {
            $received  = $event;
            $storedRow = $this->fetchOne('SELECT id FROM form_submissions WHERE id = :id', ['id' => $event->after['submissionId']]);
        });
        $api = new FormApi(new FormRepository($this->pdo));
        $id  = $api->storeSubmission(7, ['email' => 'test@example.test'], ['source' => 'public']);
        self::assertInstanceOf(ContentEvent::class, $received);
        self::assertSame($id, $storedRow['id'] ?? null);
        self::assertSame('7', $received->subjectId);
        self::assertSame(['submissionId' => $id, 'payload' => ['email' => 'test@example.test'], 'meta' => ['source' => 'public']], $received->after);
    }
}
