<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block;

use Naf\CMS\Enums\Permission;

/**
 * A block type that only some editors may place or change
 *
 * Optional: a block type without it has no own permission requirement. A host
 * can set or override the requirement for any type with
 * cms()->blockTypes()->restrict() or `cms:page_builder:block_permissions`.
 *
 * @api
 */
interface RestrictedBlockTypeInterface
{
    /**
     * The permission an editor needs to add this block or change its content
     */
    public function requiredPermission(): ?Permission;
}
