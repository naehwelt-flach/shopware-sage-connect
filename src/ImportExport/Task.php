<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class Task extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return static::class;
    }

    public static function getDefaultInterval(): int
    {
        return self::MINUTELY;
    }
}
