<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use Naf\CMS\Api\FormApi;
use Naf\CMS\Api\NavigationApi;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Events\CmsEvent;
use Naf\CMS\Events\ContentEvent;
use Naf\CMS\Handlers\Api\Article\ReviewArticle;
use Naf\CMS\Models\User;
use Naf\CMS\Page\PageRegistry;
use Naf\CMS\Repositories\FormRepository;
use Naf\CMS\Repositories\NavigationRepository;
use Naf\CMS\Support\UserHelper;
use Naf\Core\EventManager;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\DatabaseTestCase;
use Tests\Support\JsonRequest;

use function Naf\app;
use function Naf\CMS\user;
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
        $logger->expects(self::once())->method('error')->with('Event listener failed', self::callback(
            static fn(array $context): bool => $context['event'] === CmsEvent::PAGE_PUBLISHED
                && $context['exception'] === $exception
                && str_starts_with((string) $context['listener'], 'Closure@' . __FILE__),
        ));
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

    public function testSavingAPageAsPublishedReportsThePublication(): void
    {
        $this->createPagesTable();
        $actions = $this->recordActions([CmsEvent::PAGE_CREATED, CmsEvent::PAGE_UPDATED, CmsEvent::PAGE_PUBLISHED]);
        $pages   = new PageRegistry();
        $page    = ['identifier' => 'launch', 'path' => '/launch', 'title' => 'Launch', 'template_id' => 'default'];

        $draftId = $pages->save([...$page, 'status' => 'draft']);
        $pages->save([...$page, 'id' => $draftId, 'status' => 'published']);
        $pages->save([...$page, 'id' => $draftId, 'status' => 'published', 'title' => 'Launch v2']);
        $pages->save(['identifier' => 'direct', 'path' => '/direct', 'title' => 'Direct', 'template_id' => 'default', 'status' => 'published']);

        self::assertSame(
            ['page.created', 'page.updated', 'page.published', 'page.updated', 'page.created', 'page.published'],
            $actions(),
        );
    }

    public function testSavingANavigationAsPublishedReportsThePublication(): void
    {
        $this->createSchema(<<<'SQL'
            CREATE TABLE navigations (
                id INTEGER PRIMARY KEY AUTOINCREMENT, identifier TEXT NOT NULL, title TEXT NOT NULL,
                scope TEXT NOT NULL DEFAULT 'frontend', language TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'draft', items_json TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NULL,
                UNIQUE (identifier, scope, language)
            )
        SQL);
        $actions    = $this->recordActions([CmsEvent::NAVIGATION_CREATED, CmsEvent::NAVIGATION_UPDATED, CmsEvent::NAVIGATION_PUBLISHED]);
        $navigation = new NavigationApi(new NavigationRepository($this->pdo));
        $payload    = ['identifier' => 'main', 'title' => 'Main', 'scope' => 'frontend', 'language' => 'en', 'items' => []];

        $id = $navigation->save([...$payload, 'status' => 'draft']);
        $navigation->save([...$payload, 'id' => $id, 'status' => 'published']);
        $navigation->save([...$payload, 'id' => $id, 'status' => 'published']);

        self::assertSame(['navigation.created', 'navigation.updated', 'navigation.published', 'navigation.updated'], $actions());
    }

    public function testApprovingAReviewWithDirectPublishReportsThePublication(): void
    {
        $this->runMigrations();
        $this->pdo->exec("INSERT INTO users (id, username) VALUES (5, 'reviewer')");
        $this->pdo->exec('INSERT INTO articles (id, author) VALUES (9, 5)');
        $this->pdo->exec("INSERT INTO article_versions (article_id, language, version, status, slug, title, content) VALUES (9, 'de', 1, 'review', 'beitrag', 'Beitrag', '[]')");

        $received = [];
        event()->listen(CmsEvent::ARTICLE_PUBLISHED, static function (ContentEvent $event) use (&$received): void {
            $received[] = $event;
        });

        $previousUser = user();
        user(new UserHelper(new User(['id' => 5]), [Permission::ARTICLES_REVIEW, Permission::ARTICLES_PUBLISH], [], true));
        JsonRequest::bind(['action' => 'approve', 'publish_article' => true]);

        try {
            $response = (new ReviewArticle())('9', 'de');
        } finally {
            user($previousUser, reset: $previousUser === null);
        }

        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success'], (string) ($payload['reason'] ?? ''));
        self::assertCount(1, $received);
        self::assertSame('9', $received[0]->subjectId);
        self::assertSame('published', $received[0]->after['version']['status'] ?? null);
    }

    public function testIsolatedListenerFailureDoesNotStopTheOtherListeners(): void
    {
        $exception = new RuntimeException('First listener failed');
        $calls     = [];
        event()->listen(CmsEvent::PAGE_PUBLISHED, static function () use ($exception, &$calls): void {
            $calls[] = 'first';

            throw $exception;
        }, 10);
        event()->listen(CmsEvent::PAGE_PUBLISHED, [ContentEventProbeListener::class, 'handle']);
        event()->listen(CmsEvent::PAGE_PUBLISHED, static function () use (&$calls): void {
            $calls[] = 'third';
        }, -10);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Event listener failed', self::callback(
            static fn(array $context): bool => $context['event'] === CmsEvent::PAGE_PUBLISHED
                && $context['exception'] === $exception
                && str_starts_with((string) $context['listener'], 'Closure@' . __FILE__),
        ));
        app()->container()->set(LoggerInterface::class, $logger);
        ContentEventProbeListener::$received = [];

        ContentEvent::dispatch('page.published', 'page', 3);

        self::assertSame(['first', 'third'], $calls);
        self::assertSame(['3'], ContentEventProbeListener::$received);
    }

    /**
     * @param list<string> $names
     * @return callable(): list<string>
     */
    private function recordActions(array $names): callable
    {
        $actions = [];
        foreach ($names as $name) {
            event()->listen($name, static function (ContentEvent $event) use (&$actions): void {
                $actions[] = $event->action;
            });
        }

        return static function () use (&$actions): array {
            return $actions;
        };
    }

    private function createPagesTable(): void
    {
        $this->createSchema(<<<'SQL'
            CREATE TABLE pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT, identifier TEXT NOT NULL, path TEXT NOT NULL,
                title TEXT NOT NULL, language TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'draft',
                template_id TEXT NOT NULL, theme_id TEXT NULL, blocks_json TEXT NOT NULL, meta_json TEXT NOT NULL,
                layout TEXT NOT NULL DEFAULT 'layouts.page', created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NULL
            )
        SQL);
    }

    private function runMigrations(): void
    {
        $files = glob(BASE_PATH . '/src/Migrations/Migration*.php') ?: [];
        sort($files);
        foreach ($files as $file) {
            require_once $file;
            $class = 'Naf\\CMS\\Migrations\\' . basename($file, '.php');
            (new $class())->up($this->pdo);
        }
    }
}

final class ContentEventProbeListener
{
    /** @var list<string> */
    public static array $received = [];

    public function handle(ContentEvent $event): void
    {
        self::$received[] = $event->subjectId;
    }
}
