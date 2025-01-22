<?php

namespace Naehwelt\Shopware\MessageQueue;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: EveryHour::class)]
final class EveryHourHandler extends AbstractHandler
{
}