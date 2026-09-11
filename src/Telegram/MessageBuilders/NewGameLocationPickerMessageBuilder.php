<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Weather\Location\KnownVenues;
use BeachVolleybot\Weather\Location\Venue;
use DateTimeImmutable;

final class NewGameLocationPickerMessageBuilder extends AbstractNewGameMessageBuilder
{
    public const string SKIP_TEXT = 'Skip location';

    private const int VENUES_PER_PAGE = 5;
    private const string SKIP_LABEL_WRAP = '— %s —';

    public function build(DateTimeImmutable $date, string $time, int $page = 1): TelegramMessage
    {
        $venues = KnownVenues::all();
        $pagination = new KeyboardPagination(count($venues), self::VENUES_PER_PAGE, $page);

        return $this->buildMessage(
            $this->formText->buildLocationStep($date, $time),
            $this->buildKeyboard($venues, $pagination),
        );
    }

    /** @param list<Venue> $venues */
    private function buildKeyboard(array $venues, KeyboardPagination $pagination): array
    {
        $keyboard = [];

        $pageVenues = array_slice($venues, $pagination->getOffset(), self::VENUES_PER_PAGE);
        foreach ($pageVenues as $venue) {
            $keyboard[] = [$this->buildVenueButton($venue)];
        }

        $keyboard[] = [$this->buildSkipButton()];

        $paginationRow = $this->paginationRow(
            $pagination,
            $this->callbackData(NewGameCallbackAction::ShowVenuePage),
            $this->translator->translate(self::LABEL_PREVIOUS),
            $this->translator->translate(self::LABEL_NEXT),
        );

        if (null !== $paginationRow) {
            $keyboard[] = $paginationRow;
        }

        $keyboard[] = $this->backButtonRow(
            $this->callbackData(NewGameCallbackAction::ShowTimePage)
                ->withPage(NewGameTimePickerMessageBuilder::START_PAGE),
            $this->translator->translate(self::LABEL_BACK),
        );

        return $keyboard;
    }

    private function buildVenueButton(Venue $venue): array
    {
        return $this->buildActionButton(
            $venue->name,
            $this->callbackData(NewGameCallbackAction::PickVenue)->withVenueName($venue->name),
        );
    }

    private function buildSkipButton(): array
    {
        $label = sprintf(self::SKIP_LABEL_WRAP, $this->translator->translate(self::SKIP_TEXT));

        return $this->buildActionButton($label, $this->callbackData(NewGameCallbackAction::SkipVenue));
    }
}
