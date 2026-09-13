<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Support;

use function Naf\request;

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
