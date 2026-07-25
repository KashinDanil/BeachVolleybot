<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Processors\Handlers\GameHandlers\GameCallbackQueryHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\ChangeTitleHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\JoinWithTimeHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\SetLiveLocationHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\SetLocationHandler;
use BeachVolleybot\Processors\Handlers\PinHandlers\DeletePinNotificationHandler;
use BeachVolleybot\Processors\Handlers\PinHandlers\PinMessageHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\AdminCallbackQueryHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\SendShareButtonHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\SettingsMenuCommandHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\UserCallbackQueryHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\UserGamesListCommandHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\UserHelpCommandHandler;

final readonly class ProcessorRegistryFactory
{
    public static function create(): ProcessorRegistry
    {
        return new ProcessorRegistry([
            new DeletePinNotificationHandler(),
            new PinMessageHandler(),
            new GameCallbackQueryHandler(),
            new AdminCallbackQueryHandler(),
            new UserCallbackQueryHandler(),
            new SetLiveLocationHandler(),
            new SetLocationHandler(),
            new JoinWithTimeHandler(),
            new ChangeTitleHandler(),
            new SendShareButtonHandler(),
            new SettingsMenuCommandHandler(),
            new UserGamesListCommandHandler(),
            new UserHelpCommandHandler(),
        ]);
    }
}
