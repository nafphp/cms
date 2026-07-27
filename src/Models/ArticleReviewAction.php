<?php

declare(strict_types=1);

namespace src\Models;

use NixPHP\ORM\Model\AbstractModel;

final class ArticleReviewAction extends AbstractModel
{
    protected ?int $article_id = null;
    protected ?int $article_version_id = null;
    protected ?string $language = null;
    protected ?string $action = null;
    protected ?string $comment = null;
    protected ?int $reviewed_by = null;
    protected ?string $reviewed_at = null;
    protected ?string $created_at = null;

    public string $table = 'article_review_actions';
}
