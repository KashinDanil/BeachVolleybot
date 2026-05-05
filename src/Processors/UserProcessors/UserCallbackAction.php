<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

enum UserCallbackAction: string
{
    case GamesList  = 'ugl';
    case GameDetail = 'ugd';
}
