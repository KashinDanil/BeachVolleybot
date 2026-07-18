<?php

namespace BeachVolleybot\Telegram\MessageBuilders\Keyboard;

enum InlineButtonStyle: string
{
    case DANGER = 'danger';
    case SUCCESS = 'success';
    case PRIMARY = 'primary';
}