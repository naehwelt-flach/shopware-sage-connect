<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Service;

use Naehwelt\Shopware\DataAbstractionLayer\Provider;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRowEvent;
use Shopware\Core\Content\ImportExport\Processing;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Contracts\Service\ResetInterface;
use WeakMap;

class DefaultProductVisibilities implements ResetInterface
{
    private WeakMap $cache;

    public function __construct(readonly private Provider $provider)
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->cache = new WeakMap();
    }

    public function __invoke(ImportExportBeforeImportRowEvent $event, array $params): void
    {
        $row = $event->getRow();
        foreach ($this->cache[$event->getConfig()] ??= $this->map($event->getConfig(), $params) as $mapping => $ids) {
            $row[$mapping->getMappedKey()] ??= implode('|', $ids);
        }
        $event->setRow($row);
    }

    /**
     * @return iterable<Processing\Mapping\Mapping, string[]>
     */
    private function map(Config $config, array $params): iterable
    {
        $map = new WeakMap();
        foreach ($config->getMapping() as $mapping) {
            $parts = explode('.', $mapping->getKey());
            if ($parts[0] !== 'visibilities') {
                continue;
            }
            $criteria = $params[$parts[1] ?? 'all'] ?? ['active' => true];
            $map[$mapping] = $this->provider->search(SalesChannelEntity::class, $criteria)->getIds();
        }
        return $map;
    }
}
