<?php

declare(strict_types=1);

use NixCMS\Content\Article\ArticleContentType;
use NixCMS\Page\Page;
use NixCMS\Page\PageBuilderRenderer;
use NixCMS\Page\Template;
use NixCMS\Repositories\ArticleRepository;
use function NixPHP\I18n\lang;
use function NixPHP\ORM\repo;
use function NixPHP\response;

$publicTemplate = Template::create('default')
    ->setLayout('page')
    ->addStylesheet('/css/nixcms.css')
    ->addBodyClass('grid min-h-screen place-items-center bg-slate-50 p-8 text-slate-950 dark:bg-slate-950 dark:text-slate-50')
    ->addBlock('welcome', 'page.blocks.welcome', [
        'title' => cms()->settings()->name(),
    ]);

$articleTemplate = Template::create('article_detail')
    ->setLayout('page')
    ->addStylesheet('/css/nixcms.css')
    ->addBodyClass('min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50')
    ->addBlock('site_header', 'page.blocks.placeholder', [
        'label' => 'Site header',
        'size' => 'small',
        'preview' => 'navigation',
    ])
    ->addBlock('header', 'page.blocks.article_header')
    ->addBlock('body', 'page.blocks.article_body')
    ->addBlock('aside', 'page.blocks.placeholder', [
        'label' => 'Reading aside',
        'size' => 'aside',
    ])
    ->addBlock('related', 'page.blocks.placeholder', [
        'label' => 'Related content',
        'size' => 'small',
    ])
    ->addBlock('site_footer', 'page.blocks.placeholder', [
        'label' => 'Site footer',
        'size' => 'small',
        'preview' => 'footer',
    ]);

$articleDetailPage = Page::create('frontend.article.detail')
    ->setPath('/articles/{slug}')
    ->setTitle('Article')
    ->setTemplate($articleTemplate);

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
    Page::create('home')
        ->setPath('/')
        ->setTitle(cms()->settings()->name())
        ->setTemplate($publicTemplate),
    $articleDetailPage,
];
