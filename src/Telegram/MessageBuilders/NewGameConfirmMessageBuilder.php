<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;

final class NewGameConfirmMessageBuilder extends AbstractMessageBuilder
{
    public const string LABEL_POST = 'Post';

    private readonly NewGameFormText $formText;

    public function __construct(
        private readonly Translator $translator,
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($formatter);
        $this->formText = new NewGameFormText($translator, $this->formatter);
    }

    public function build(DateTimeImmutable $date, string $time, ?string $venueName): TelegramMessage
    {
        return $this->buildMessage(
            $this->formText->buildConfirmStep($date, $time, $venueName),
            $this->buildKeyboard($venueName),
        );
    }

    private function buildKeyboard(?string $venueName): array
    {
        return [
            [$this->buildActionButton(
                $this->translator->translate(self::LABEL_POST),
                $this->sendCallbackData($venueName),
                InlineButtonStyle::SUCCESS,
            )],
            $this->backButtonRow(
                NewGameCallbackData::create(NewGameCallbackAction::ShowVenuePage),
                $this->translator->translate(self::LABEL_BACK),
            ),
        ];
    }

    private function sendCallbackData(?string $venueName): NewGameCallbackData
    {
        $callbackData = NewGameCallbackData::create(NewGameCallbackAction::Send);
        if (null !== $venueName) {
            return $callbackData->withVenueName($venueName);
        }

        return $callbackData;
    }
}
