<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Outgoing;

use TelegramBot\Api\Types\Inline\QueryResult\Article;

interface ArticleBuilderInterface
{
    public function build(): Article;
}
