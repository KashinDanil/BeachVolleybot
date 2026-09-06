<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\GameLabel;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;

final class GamesListMessageBuilder extends AbstractAdminMessageBuilder
{
    public const string  HEADER_MESSAGE = 'Games';
    private const string NO_GAMES_FOUND = 'No games found';

    public function __construct(
        private readonly Translator $translator = new Translator(),
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($formatter);
    }

    public function buildGamesList(array $games, KeyboardPagination $pagination): TelegramMessage
    {
        return $this->buildMessage(
            $this->buildGamesListText($games, $pagination),
            $this->buildGamesListKeyboard($games, $pagination),
        );
    }

    private function buildGamesListText(array $games, KeyboardPagination $pagination): string
    {
        $header = $this->formatHeader(self::HEADER_MESSAGE);

        if (empty($games)) {
            return $header . $this->formatter->newLine() . $this->formatter->escape(self::NO_GAMES_FOUND);
        }

        return $header . $this->formatter->newLine() . $this->formatter->escape("Page {$pagination->getPage()} of {$pagination->getTotalPages()}");
    }

    private function buildGamesListKeyboard(array $games, KeyboardPagination $pagination): array
    {
        $keyboard = [];

        foreach ($games as $game) {
            $keyboard[] = [$this->buildGameButton((int)$game['game_id'], new DateTimeImmutable((string)$game['kickoff_at']))];
        }

        $paginationRow = $this->paginationRow($pagination, AdminCallbackData::create(AdminCallbackAction::GamesList));
        if (null !== $paginationRow) {
            $keyboard[] = $paginationRow;
        }

        $keyboard[] = $this->backButtonRow(AdminCallbackData::create(AdminCallbackAction::Settings));

        return $keyboard;
    }

    private function buildGameButton(int $gameId, DateTimeImmutable $kickoffAt): array
    {
        return $this->buildActionButton(
            GameLabel::format($gameId, $kickoffAt, $this->translator),
            AdminCallbackData::create(AdminCallbackAction::GameDetail)->withGameId($gameId),
        );
    }
}
