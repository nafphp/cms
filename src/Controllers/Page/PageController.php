<?php

declare(strict_types=1);

namespace NixPHP\CMS\Controllers\Page;

use NixPHP\CMS\Page\PageDataHandler;
use NixPHP\CMS\Page\Page;
use NixPHP\CMS\Page\PageAuthorization;
use NixPHP\CMS\Page\PageBuilderRenderer;
use NixPHP\CMS\Support\HandlerInvoker;
use NixPHP\Exceptions\RouteNotFoundException;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\I18n\t;
use function NixPHP\request;
use function NixPHP\response;
use function NixPHP\route;
use function NixPHP\View\view;
use function NixPHP\CMS\cms;

final class PageController
{
    public function show(mixed ...$params): ResponseInterface
    {
        $page = $this->resolvePage();
        (new PageAuthorization())->authorize($page);

        $handler = $page->handler();
        $usesPageLayout = false;
        if ($handler !== null) {
            $result = HandlerInvoker::call($handler, $params);
        } elseif ($page->hasContent()) {
            $content = $page->content();
            $result = $content(...$params);
        } else {
            $usesPageLayout = $page->pageLayout() !== null;
            $result = (new PageBuilderRenderer())->render($page);
        }

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if ($usesPageLayout) {
            return response((string) $result);
        }

        return $this->renderPage($page, (string) $result);
    }

    public function handleData(mixed ...$params): ResponseInterface
    {
        $page = $this->resolvePage();
        $dataHandler = $this->resolveDataHandler($page);

        (new PageAuthorization())->authorize($page, $dataHandler->permission);

        $response = HandlerInvoker::call($dataHandler->handler, $params);
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        throw new \RuntimeException(sprintf(
            'Data handler for page "%s" must return a PSR response.',
            $page->id()
        ));
    }

    private function resolvePage(): Page
    {
        $routeName = route()->current();
        if (is_string($routeName) && $routeName !== '') {
            if (str_starts_with($routeName, 'db.page.')) {
                $request = request();
                $page = cms()->pages()->findByPath($request->getUri()->getPath(), $request->getMethod());
                if ($page instanceof Page) {
                    return $page;
                }
            }

            $page = cms()->pages()->get($routeName);
            if ($page instanceof Page) {
                return $page;
            }

            $page = cms()->pages()->findByDataRouteName($routeName);
            if ($page instanceof Page) {
                return $page;
            }
        }

        $request = request();
        $page = cms()->pages()->findByPath($request->getUri()->getPath(), $request->getMethod());
        if ($page instanceof Page) {
            return $page;
        }

        // A route exists but no page resolved. That is the normal outcome for a
        // draft requested by someone without PAGES_VIEW: the lookup filters it
        // out before any content is built. Answer exactly like a path that was
        // never there, so the draft's existence stays hidden.
        throw new RouteNotFoundException();
    }

    private function resolveDataHandler(Page $page): PageDataHandler
    {
        $method = request()->getMethod();
        $dataHandler = $page->dataHandler($method);
        if (!$dataHandler instanceof PageDataHandler) {
            throw new \RuntimeException(sprintf(
                'Page "%s" has no data handler for %s.',
                $page->id(),
                strtoupper($method)
            ));
        }

        return $dataHandler;
    }

    private function renderPage(Page $page, string $content): ResponseInterface
    {
        if ($page->layout() === null || $page->layout() === '') {
            return response($content);
        }

        return response(view('page.shell', [
            'title' => t($page->title()),
            'pageTitle' => t($page->title()),
            'page' => $page,
            'content' => $content,
            'layout' => $page->layout(),
        ]));
    }
}
