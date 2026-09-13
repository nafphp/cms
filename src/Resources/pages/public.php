<?php

declare(strict_types=1);

use Naf\CMS\Content\Article\ArticleContentType;
use Naf\CMS\Page\Layout;
use Naf\CMS\Page\Page;
use Naf\CMS\Page\PageBuilderRenderer;
use Naf\CMS\Page\Template;
use Naf\CMS\Repositories\ArticleRepository;
use function Naf\I18n\lang;
use function Naf\ORM\repo;
use function Naf\response;
use function Naf\CMS\cms;

$publicLayout = Layout::create(Template::create('default')
    ->setParent('page')
    ->addStylesheet('/css/nafcms.css')
    ->addBodyClass('grid min-h-screen place-items-center bg-slate-50 p-8 text-slate-950 dark:bg-slate-950 dark:text-slate-50'))
    ->addBlock('site_header', 'page.blocks.empty', ['label' => 'Site header', 'region' => 'header', 'size' => 'small'])
    ->addBlock('main', 'page.blocks.welcome', [
        'title' => cms()->settings()->name(),
    ])
    ->addBlock('site_footer', 'page.blocks.empty', ['label' => 'Site footer', 'region' => 'footer', 'size' => 'small']);

$articleLayout = Layout::create(Template::create('article_detail')
    ->setParent('page')
    ->addStylesheet('/css/nafcms.css')
    ->addBodyClass('min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50'))
    ->addBlock('site_header', 'page.blocks.placeholder', [
        'label' => 'Site header',
        'size' => 'small',
        'preview' => 'navigation',
    ])
    ->addBlock('header', 'page.blocks.article_title')
    ->addBlock('body', 'page.blocks.article_content')
    ->addBlock('aside', 'page.blocks.article_meta')
    ->addBlock('related', 'page.blocks.article_related')
    ->addBlock('site_footer', 'page.blocks.placeholder', [
        'label' => 'Site footer',
        'size' => 'small',
        'preview' => 'footer',
    ]);

$articleDetailPage = Page::create('frontend.article.detail')
    ->setPath('/articles/{slug}')
    ->setTitle('Article')
    ->setLayout($articleLayout);

$articleDetailPage->setContent(static function (string $slug) use ($articleDetailPage): string|\Psr\Http\Message\ResponseInterface {
    $article = repo(ArticleRepository::class)->findPublishedVisibleBySlug($slug, lang());
    if ($article === null) {
        return response('Article not found')->withStatus(404);
    }

    $articleType = cms()->contentTypes()->require('article');
    if (!$articleType instanceof ArticleContentType) {
        throw new \RuntimeException('Registered article content type must be an ArticleContentType.');
    }

    return (new PageBuilderRenderer())->render($articleDetailPage, [
        'article' => $articleType->renderer($article->jsonSerialize()),
    ]);
});

return [
    // Placeholder so "/" is never empty. An editor's published start page
    // replaces it.
    Page::create('home')
        ->setPath('/')
        ->setTitle(cms()->settings()->name())
        ->setLayout($publicLayout)
        ->allowStoredOverride(),
    $articleDetailPage,
];
