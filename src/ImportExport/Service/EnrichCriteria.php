<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Service;

use Naehwelt\Shopware\DataAbstractionLayer\Provider;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;

readonly class EnrichCriteria
{
    public function __invoke(EnrichExportCriteriaEvent $event, array $params): void
    {
        foreach ($params as $param) {
            Provider::criteria($param, $event->getCriteria());
        }
    }
}
