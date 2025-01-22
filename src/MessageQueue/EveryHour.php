<?php

namespace Naehwelt\Shopware\MessageQueue;

/**
 * sc: handle tasks which should run every hour
 */
final class EveryHour extends AbstractInterval
{
    public static function getDefaultInterval(): int
    {
        return self::HOURLY;
    }
}