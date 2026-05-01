<?php

declare(strict_types=1);

namespace NixCMS\Controllers\Page;

use NixCMS\Page\PageDataHandler;
use NixCMS\Page\Page;
use NixCMS\Page\PageAuthorization;
use NixCMS\Support\HandlerInvoker;
use Psr\Http\Message\ResponseInterface;
use function cms;
use function NixPHP\request;
use function NixPHP\response;
use function NixPHP\route;
use function NixPHP\View\view;

final class PageController
{
    public function show(mixed ...$params): ResponseInterface
    {
        $page = $this->resolvePage();
        (new PageAuthorization())->authorize($page);

        $handler = $page->handler();
        if ($handler !== null) {
            $result = HandlerInvoker::call($handler, $params);
        } else {
            $content = $page->content();
            $result = $content(...$params);
        }

        if ($result instanceof ResponseInterface) {
            return $result;
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

        throw new \RuntimeException('No registered page matches the current request.');
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
            'title' => $page->title(),
            'pageTitle' => $page->title(),
            'page' => $page,
            'content' => $content,
            'layout' => $page->layout(),
        ]));
    }
}
