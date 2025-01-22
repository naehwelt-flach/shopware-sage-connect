<?php

namespace Naehwelt\Shopware\MessageQueue;

/**
 * sc: handle tasks which should run every 5 minutes
 */
final class EveryFiveMinutes extends AbstractInterval
{
    public static function getDefaultInterval(): int
    {
        return self::MINUTELY * 5;
    }
}