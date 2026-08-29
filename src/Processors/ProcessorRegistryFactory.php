<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Processors\Handlers\GameHandlers\ChangeTitleHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\CreateGameFromMessageHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\GameCallbackQueryHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\JoinWithTimeHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\SetLiveLocationHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\SetLocationHandler;
use BeachVolleybot\Processors\Handlers\InlineHandlers\CreateGameHandler;
use BeachVolleybot\Processors\Handlers\InlineHandlers\ForwardGameHandler;
use BeachVolleybot\Processors\Handlers\InlineHandlers\InlineQueryHandler;
use BeachVolleybot\Processors\Handlers\NewGameHandlers\GroupNewGameCommandHandler;
use BeachVolleybot\Processors\Handlers\NewGameHandlers\NewGameCallbackQueryHandler;
use BeachVolleybot\Processors\Handlers\NewGameHandlers\UserNewGameCommandHandler;
use BeachVolleybot\Processors\Handlers\PinHandlers\DeletePinNotificationHandler;
use BeachVolleybot\Processors\Handlers\PinHandlers\PinMessageHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\AdminCallbackQueryHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\GroupHelpCommandHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\SendShareButtonHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\SettingsMenuCommandHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\UserCallbackQueryHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\UserGamesListCommandHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\UserHelpCommandHandler;

/**
 * Owns the routing table. Handler match patterns must be mutually exclusive across both
 * lists — HandlerExclusivityTest enforces that over the fixtures it knows about.
 */
final readonly class ProcessorRegistryFactory
{
    /**
     * Updates Telegram expects an answer to within the webhook request.
     */
    public static function createImmediate(): ProcessorRegistry
    {
        return new ProcessorRegistry(self::immediateHandlers());
    }

    /**
     * Updates that are enqueued at routing time and processed by a worker.
     */
    public static function createQueued(): ProcessorRegistry
    {
        return new ProcessorRegistry(self::queuedHandlers());
    }

    /**
     * @return AbstractProcessorHandler[]
     */
    public static function immediateHandlers(): array
    {
        return [
            new InlineQueryHandler(),
            new ForwardGameHandler(),
            new CreateGameHandler(),
            new GroupHelpCommandHandler(),
            new GroupNewGameCommandHandler(),
        ];
    }

    /**
     * @return AbstractQueuedProcessorHandler[]
     */
    public static function queuedHandlers(): array
    {
        return [
            new DeletePinNotificationHandler(),
            new PinMessageHandler(),
            new GameCallbackQueryHandler(),
            new AdminCallbackQueryHandler(),
            new UserCallbackQueryHandler(),
            new NewGameCallbackQueryHandler(),
            new SetLiveLocationHandler(),
            new SetLocationHandler(),
            new ChangeTitleHandler(), //Must be before JoinWithTimeHandler
            new JoinWithTimeHandler(),
            new CreateGameFromMessageHandler(),
            new SendShareButtonHandler(),
            new SettingsMenuCommandHandler(),
            new UserGamesListCommandHandler(),
            new UserNewGameCommandHandler(),
            new UserHelpCommandHandler(),
        ];
    }
}
