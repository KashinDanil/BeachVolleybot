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
    public const string VOLLEYBALL_ADDED     = "🏐 Volleyball added! Thanks for bringing one";
    public const string VOLLEYBALL_REMOVED   = "🏐 Volleyball removed";
    public const string NO_VOLLEYBALLS       = "You don't have any volleyballs to remove";
    public const string NET_ADDED            = "🕸️ Net added! Thanks for bringing one";
    public const string NET_REMOVED          = "🕸️ Net removed";
    public const string NO_NETS              = "You don't have any nets to remove";
    public const string SOMETHING_WENT_WRONG = "Something went wrong";
}
