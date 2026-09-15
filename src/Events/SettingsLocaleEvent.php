<?php

declare(strict_types=1);

namespace Naf\CMS\Events;

use Naf\I18n\Core\Translator;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function Naf\app;
use function Naf\CMS\cms;

final class SettingsLocaleEvent
{
    public function handle(ServerRequestInterface $request): void
    {
        try {
            $settings   = cms()->settings();
            $translator = app()->container()->get(Translator::class);
            $language   = strtolower(trim((string) ($translator->getLanguage() ?? '')));

            if (($request->getQueryParams()['lang'] ?? null) || ($request->getCookieParams()['lang'] ?? null)) {
                if (!$settings->translationFileExists($language)) {
                    $translator->setLanguage($settings->fallbackLanguage());
                }

                return;
            }

            $translator->setLanguage($settings->language());
        } catch (Throwable) {
        }
    }
}
