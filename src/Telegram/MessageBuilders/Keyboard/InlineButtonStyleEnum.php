<?php

namespace BeachVolleybot\Telegram\MessageBuilders\Keyboard;

enum InlineButtonStyleEnum: string
{
    case DANGER = 'danger';
    case SUCCESS = 'success';
    case PRIMARY = 'primary';
}