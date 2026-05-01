<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Support;

use function NixPHP\request;

trait ReadsJsonBody
{
    /**
     * @return array<string, mixed>
     */
    private function jsonBody(): array
    {
        $data = json_decode((string) request()->getBody(), true);

        return is_array($data) ? $data : [];
    }
}
