<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

final class CallbackAnswer
{
    public const string GAME_NOT_FOUND     = 'Game not found';
    public const string SIGN_UP_FIRST      = 'You need to sign up first';
    public const string NOT_SIGNED_UP      = 'You are not signed up';
    public const string SIGNED_UP          = 'You have signed up';
    public const string SIGNED_OUT         = 'You have signed out';
    public const string VOLLEYBALL_ADDED   = 'A volleyball added';
    public const string VOLLEYBALL_REMOVED = 'A volleyball removed';
    public const string NO_VOLLEYBALLS     = 'You don\'t have any volleyballs';
    public const string NET_ADDED          = 'A net added';
    public const string NET_REMOVED        = 'A net removed';
    public const string NO_NETS            = 'You don\'t have any nets';
}
