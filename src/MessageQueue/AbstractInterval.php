<?php

namespace Naehwelt\Shopware\MessageQueue;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

abstract class AbstractInterval extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return static::class;
    }
}