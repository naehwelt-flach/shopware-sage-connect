<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Service;

use Naehwelt\Shopware\DataAbstractionLayer\Provider;
use Naehwelt\Shopware\ImportExport\EventSubscriber;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;

readonly class EnrichCriteria
{
    public function __invoke(EnrichExportCriteriaEvent $event, array $params): void
    {
        foreach ($params as $param) {
            Provider::criteria($param, $event->getCriteria());
        }
    }

    public static function params($criteria): array
    {
        return EventSubscriber::params(self::class, [self::class => $criteria]);
    }
}
