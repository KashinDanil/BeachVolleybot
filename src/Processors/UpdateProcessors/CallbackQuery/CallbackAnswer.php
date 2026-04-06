<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

final class CallbackAnswer
{
    public const string GAME_NOT_FOUND       = 'This game no longer exists';
    public const string JOIN_FIRST           = 'Join the game first!';
    public const string NOT_JOINED           = "You're not in this game";
    public const string JOINED               = "You're in! See you there 🏖";
    public const string LEFT                 = "You've left the game";
    public const string VOLLEYBALL_ADDED     = "🏐 +1 volleyball";
    public const string VOLLEYBALL_REMOVED   = "🏐 -1 volleyball";
    public const string NO_VOLLEYBALLS       = "You have no volleyballs to remove";
    public const string NET_ADDED            = "🕸️ +1 net";
    public const string NET_REMOVED          = "🕸️ -1 net";
    public const string NO_NETS              = "You have no nets to remove";
    public const string SOMETHING_WENT_WRONG = "Something went wrong";
}
