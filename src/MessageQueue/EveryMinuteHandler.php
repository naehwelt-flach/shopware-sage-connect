<?php

namespace Naehwelt\Shopware\MessageQueue;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: EveryMinute::class)]
final class EveryMinuteHandler extends AbstractHandler
{
}