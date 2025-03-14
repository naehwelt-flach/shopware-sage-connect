<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\MessageQueue;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: EveryFiveMinutes::class)]
final class EveryFiveMinutesHandler extends AbstractHandler
{
}