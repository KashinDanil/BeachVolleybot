<?php

namespace BeachVolleybot\Common\Extractors;

interface ExtractorInterface
{
    public static function extract(string $text): ?string;

    public static function pattern(): string;
}