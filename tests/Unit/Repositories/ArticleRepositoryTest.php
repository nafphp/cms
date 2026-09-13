<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use Naf\CMS\Enums\ArticleStatus;
use Naf\CMS\Repositories\ArticleRepository;
use Naf\ORM\Core\EntityManager;
use Tests\Support\DatabaseTestCase;

final class ArticleRepositoryTest extends DatabaseTestCase
{
    private ArticleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firstname TEXT NULL,
                lastname TEXT NULL
            )',
            'CREATE TABLE articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                author INTEGER NULL,
                category_id INTEGER NULL,
                hide_lead_image_in_content INTEGER NOT NULL DEFAULT 1,
                createdAt TEXT NULL
            )',
            'CREATE TABLE article_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER NOT NULL,
                language TEXT NOT NULL,
                version INTEGER NOT NULL,
                status TEXT NOT NULL,
                slug TEXT NOT NULL,
                title TEXT NOT NULL,
                teaser TEXT NULL,
                content TEXT NOT NULL DEFAULT "{}",
                publishedAt TEXT NULL,
                scheduledAt TEXT NULL,
                show_teaser INTEGER NOT NULL DEFAULT 0,
                createdAt TEXT NULL
            )',
            'CREATE TABLE article_review_actions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER NOT NULL,
                article_version_id INTEGER NOT NULL,
                language TEXT NOT NULL,
                action TEXT NOT NULL,
                comment TEXT NULL,
                reviewed_by INTEGER NOT NULL,
                reviewed_at TEXT NOT NULL,
                created_at TEXT NULL
            )',
        );

        $this->repository = new ArticleRepository($this->pdo, new EntityManager($this->pdo));
    }

    public function testStatusOverviewCountsOnlyLatestVersionForAuthorAndLanguage(): void
    {
        $firstArticle = $this->insertArticle(7);
        $secondArticle = $this->insertArticle(7);
        $thirdArticle = $this->insertArticle(8);

        $this->insertVersion($firstArticle, 'en', 1, ArticleStatus::DRAFT, 'First old');
        $this->insertVersion($firstArticle, 'en', 2, ArticleStatus::PUBLISHED, 'First latest');
        $this->insertVersion($firstArticle, 'de', 1, ArticleStatus::REVIEW, 'First German');
        $this->insertVersion($secondArticle, 'en', 1, ArticleStatus::DRAFT, 'Second latest');
        $this->insertVersion($thirdArticle, 'en', 1, ArticleStatus::DECLINED, 'Other author');

        $overview = $this->repository->getArticleStatusOverview(7, 'EN');

        self::assertSame(1, $overview[ArticleStatus::DRAFT->value]);
        self::assertSame(0, $overview[ArticleStatus::REVIEW->value]);
        self::assertSame(0, $overview[ArticleStatus::DECLINED->value]);
        self::assertSame(1, $overview[ArticleStatus::PUBLISHED->value]);
        self::assertSame(0, $overview[ArticleStatus::SCHEDULED->value]);
        self::assertSame(0, $overview[ArticleStatus::ARCHIVED->value]);
    }

    public function testDraftReviewQueueReturnsOnlyLatestDraftOrDeclinedVersionsWithLatestReviewAction(): void
    {
        $authorId = $this->insertRow('users', ['firstname' => 'Ada', 'lastname' => 'Editor']);
        $reviewerId = $this->insertRow('users', ['firstname' => 'Ruth', 'lastname' => 'Reviewer']);

        $reviewArticle = $this->insertArticle($authorId);
        $declinedArticle = $this->insertArticle($authorId);
        $draftArticle = $this->insertArticle(null);

        $this->insertVersion($reviewArticle, 'en', 1, ArticleStatus::DRAFT, 'Old draft', '2026-05-01 10:00:00');
        $this->insertVersion($reviewArticle, 'en', 2, ArticleStatus::REVIEW, 'Now in review', '2026-05-01 11:00:00');

        $declinedVersionId = $this->insertVersion(
            $declinedArticle,
            'de',
            1,
            ArticleStatus::DECLINED,
            'Needs fixes',
            '2026-05-02 09:00:00',
        );
        $this->insertReviewAction($declinedArticle, $declinedVersionId, 'de', 'approve', 'Looks better', $reviewerId, '2026-05-02 10:00:00');
        $this->insertReviewAction($declinedArticle, $declinedVersionId, 'de', 'decline', 'Still missing proof', $reviewerId, '2026-05-02 10:30:00');

        $draftVersionId = $this->insertVersion(
            $draftArticle,
            'en',
            1,
            ArticleStatus::DRAFT,
            'Fresh draft',
            '2026-05-03 08:00:00',
        );

        $queue = $this->repository->getDraftReviewQueue(10);

        self::assertCount(2, $queue);
        self::assertSame($draftVersionId, $queue[0]['article_version_id']);
        self::assertSame('Fresh draft', $queue[0]['title']);
        self::assertSame('', $queue[0]['author_name']);
        self::assertNull($queue[0]['last_review_action']);

        self::assertSame($declinedVersionId, $queue[1]['article_version_id']);
        self::assertSame('Needs fixes', $queue[1]['title']);
        self::assertSame('Ada Editor', $queue[1]['author_name']);
        self::assertSame('decline', $queue[1]['last_review_action']);
        self::assertSame('Still missing proof', $queue[1]['last_review_comment']);
        self::assertSame('Ruth Reviewer', $queue[1]['last_reviewer_name']);
    }

    public function testDeleteLanguageVersionsRemovesArticleOnlyAfterLastLocaleIsDeleted(): void
    {
        $articleId = $this->insertArticle(12);
        $this->insertVersion($articleId, 'en', 1, ArticleStatus::DRAFT, 'English');
        $this->insertVersion($articleId, 'de', 1, ArticleStatus::DRAFT, 'German');

        self::assertTrue($this->repository->deleteLanguageVersions($articleId, 'de'));
        self::assertSame(1, (int) $this->fetchOne('SELECT COUNT(*) AS total FROM articles WHERE id = :id', ['id' => $articleId])['total']);
        self::assertSame(1, (int) $this->fetchOne('SELECT COUNT(*) AS total FROM article_versions WHERE article_id = :id', ['id' => $articleId])['total']);

        self::assertTrue($this->repository->deleteLanguageVersions($articleId, 'en'));
        self::assertSame(0, (int) $this->fetchOne('SELECT COUNT(*) AS total FROM articles WHERE id = :id', ['id' => $articleId])['total']);
    }

    public function testSlugServesTheActiveLanguageOfTheSameArticle(): void
    {
        $articleId = $this->insertArticle(3);
        $this->insertVersion($articleId, 'de', 1, ArticleStatus::PUBLISHED, 'Demo Geschichte');
        $this->insertVersion($articleId, 'en', 1, ArticleStatus::PUBLISHED, 'Demo Story EN');

        // The German slug is the canonical URL; an English visitor must get the
        // English translation under that same URL.
        $article = $this->repository->findPublishedVisibleBySlug('demo-geschichte', 'en');

        self::assertNotNull($article);
        self::assertSame('Demo Story EN', $article->getVersion()?->getTitle());
    }

    public function testSlugKeepsServingItsOwnLanguageWhenThatIsActive(): void
    {
        $articleId = $this->insertArticle(3);
        $this->insertVersion($articleId, 'de', 1, ArticleStatus::PUBLISHED, 'Demo Geschichte');
        $this->insertVersion($articleId, 'en', 1, ArticleStatus::PUBLISHED, 'Demo Story EN');

        $article = $this->repository->findPublishedVisibleBySlug('demo-geschichte', 'de');

        self::assertNotNull($article);
        self::assertSame('Demo Geschichte', $article->getVersion()?->getTitle());
    }

    public function testSlugFallsBackToTheFallbackLanguageWhenTheActiveOneIsMissing(): void
    {
        $articleId = $this->insertArticle(3);
        $this->insertVersion($articleId, 'de', 1, ArticleStatus::PUBLISHED, 'Demo Geschichte');
        $this->insertVersion($articleId, 'en', 1, ArticleStatus::PUBLISHED, 'Demo Story EN');

        // No French translation exists, so the configured fallback (en) wins
        // over the translation that happens to own the slug.
        $article = $this->repository->findPublishedVisibleBySlug('demo-geschichte', 'fr');

        self::assertNotNull($article);
        self::assertSame('Demo Story EN', $article->getVersion()?->getTitle());
    }

    public function testSlugFallsBackToItsOwnLanguageWhenNoFallbackTranslationExists(): void
    {
        $articleId = $this->insertArticle(3);
        $this->insertVersion($articleId, 'de', 1, ArticleStatus::PUBLISHED, 'Nur Deutsch');

        $article = $this->repository->findPublishedVisibleBySlug('nur-deutsch', 'fr');

        self::assertNotNull($article);
        self::assertSame('Nur Deutsch', $article->getVersion()?->getTitle());
    }

    public function testUnpublishedTranslationDoesNotHideThePublishedOne(): void
    {
        $articleId = $this->insertArticle(3);
        $this->insertVersion($articleId, 'de', 1, ArticleStatus::PUBLISHED, 'Demo Geschichte');
        $this->insertVersion($articleId, 'en', 1, ArticleStatus::DRAFT, 'Draft Story EN');

        // The English draft must not be served, and must not turn the German
        // article into a 404 either.
        $article = $this->repository->findPublishedVisibleBySlug('demo-geschichte', 'en');

        self::assertNotNull($article);
        self::assertSame('Demo Geschichte', $article->getVersion()?->getTitle());
    }

    public function testListingServesTheRequestedLanguage(): void
    {
        $articleId = $this->insertArticle(3);
        $this->insertVersion($articleId, 'de', 1, ArticleStatus::PUBLISHED, 'Demo Geschichte');
        $this->insertVersion($articleId, 'en', 1, ArticleStatus::PUBLISHED, 'Demo Story EN');

        $articles = $this->repository->findPublishedWithLatestVersionByCategoryAndLanguage(null, 10, 'en');

        self::assertCount(1, $articles);
        self::assertSame('Demo Story EN', $articles[0]->getVersion()?->getTitle());
    }

    public function testListingKeepsArticlesThatLackTheRequestedTranslation(): void
    {
        $translated = $this->insertArticle(3);
        $this->insertVersion($translated, 'de', 1, ArticleStatus::PUBLISHED, 'Zweisprachig');
        $this->insertVersion($translated, 'en', 1, ArticleStatus::PUBLISHED, 'Bilingual');

        $germanOnly = $this->insertArticle(3);
        $this->insertVersion($germanOnly, 'de', 1, ArticleStatus::PUBLISHED, 'Nur Deutsch');

        // A monolingual site must not go blank just because the visitor asked
        // for a language nothing is translated into.
        $articles = $this->repository->findPublishedWithLatestVersionByCategoryAndLanguage(null, 10, 'fr');

        $titles = array_map(static fn($article): string => (string) $article->getVersion()?->getTitle(), $articles);
        sort($titles);

        self::assertSame(['Bilingual', 'Nur Deutsch'], $titles);
    }

    public function testListingIgnoresUnpublishedTranslations(): void
    {
        $articleId = $this->insertArticle(3);
        $this->insertVersion($articleId, 'de', 1, ArticleStatus::PUBLISHED, 'Veroeffentlicht');
        $this->insertVersion($articleId, 'en', 1, ArticleStatus::DRAFT, 'Draft EN');

        $articles = $this->repository->findPublishedWithLatestVersionByCategoryAndLanguage(null, 10, 'en');

        self::assertCount(1, $articles);
        self::assertSame('Veroeffentlicht', $articles[0]->getVersion()?->getTitle());
    }

    public function testUnknownSlugStaysUnresolved(): void
    {
        $articleId = $this->insertArticle(3);
        $this->insertVersion($articleId, 'de', 1, ArticleStatus::PUBLISHED, 'Demo Geschichte');

        self::assertNull($this->repository->findPublishedVisibleBySlug('gibt-es-nicht', 'de'));
        self::assertNull($this->repository->findPublishedVisibleBySlug('', 'de'));
    }

    private function insertArticle(?int $authorId): int
    {
        return $this->insertRow('articles', [
            'author' => $authorId,
            'category_id' => null,
            'hide_lead_image_in_content' => 1,
            'createdAt' => '2026-05-01 09:00:00',
        ]);
    }

    private function insertVersion(
        int $articleId,
        string $language,
        int $version,
        ArticleStatus $status,
        string $title,
        string $createdAt = '2026-05-01 10:00:00',
    ): int {
        return $this->insertRow('article_versions', [
            'article_id' => $articleId,
            'language' => $language,
            'version' => $version,
            'status' => $status->value,
            'slug' => strtolower(str_replace(' ', '-', $title)),
            'title' => $title,
            'teaser' => '',
            'content' => '{}',
            'publishedAt' => $status === ArticleStatus::PUBLISHED ? $createdAt : null,
            'scheduledAt' => null,
            'show_teaser' => 0,
            'createdAt' => $createdAt,
        ]);
    }

    private function insertReviewAction(
        int $articleId,
        int $articleVersionId,
        string $language,
        string $action,
        string $comment,
        int $reviewerId,
        string $reviewedAt,
    ): void {
        $this->insertRow('article_review_actions', [
            'article_id' => $articleId,
            'article_version_id' => $articleVersionId,
            'language' => $language,
            'action' => $action,
            'comment' => $comment,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => $reviewedAt,
            'created_at' => $reviewedAt,
        ]);
    }
}
