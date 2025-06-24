<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Service;

use Naehwelt\Shopware\DataAbstractionLayer\Provider;
use Naehwelt\Shopware\ImportExport\Serializer\PrimaryKeyResolver;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductSerializer;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Processing;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
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

    public function __invoke(ImportExportBeforeImportRecordEvent $event, int $resolved, array $params): void
    {
        if ($resolved !== PrimaryKeyResolver::RESOLVED_NONE) {
            return;
        }
        $record = $event->getRecord();
        foreach ($this->cache[$event->getConfig()] ??= $this->map($event->getConfig(), $params) as $field => $values) {
            isset($record[$field]) || $record[$field] = $values;
        }
        $event->setRecord($record);
    }

    private function map(Config $config, array $params): iterable
    {
        static $types;
        $types ??= array_flip(ProductSerializer::VISIBILITY_MAPPING);
        static $defaultType = ProductSerializer::VISIBILITY_MAPPING[ProductVisibilityDefinition::VISIBILITY_ALL];

        foreach ($config->getMapping() as $mapping) {
            $parts = explode('.', $mapping->getKey());
            if ($parts[0] !== 'visibilities') {
                continue;
            }
            $criteria = $params[$type = $parts[1] ?? $defaultType] ?? ['active' => true];
            $visibilities = $this->provider->search(SalesChannelEntity::class, $criteria)->map(
                fn(SalesChannelEntity $item) => ['visibility' => $types[$type], 'salesChannelId' => $item->getId()]
            );
            $visibilities && yield 'visibilities' => $visibilities;
        }
    }
}
