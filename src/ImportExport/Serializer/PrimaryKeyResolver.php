<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Serializer;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver as BasePrimaryKeyResolver;
use Shopware\Core\Content\ImportExport\Processing;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\Struct\ArrayStruct;

class PrimaryKeyResolver extends BasePrimaryKeyResolver
{
    public const RESOLVED_NONE = 0; // inserted
    public const RESOLVED_MAPPED = 1;
    public const RESOLVED_PRIMARY = 2;

    public function resolvePrimaryKeyFromUpdatedBy(
        Config $config,
        ?EntityDefinition $definition,
        iterable $record
    ): iterable {
        if (!$definition) {
            return $record;
        }
        if ($config->get('sourceEntity') !== $definition?->getEntityName()) {
            return parent::resolvePrimaryKeyFromUpdatedBy($config, $definition, $record);
        }
        $struct = $this->resolvedStruct($config);
        if (!($primaryKey = $struct[static::class] ??= $this->primaryKey($definition))) {
            return $record;
        }
        $before = [...$record];
        $after = [...parent::resolvePrimaryKeyFromUpdatedBy($config, $definition, $before)];
        if (($key = $after[$primaryKey] ?? null) && !($struct = $this->resolvedStruct($config))->has($key)) {
            /** @noinspection ProperNullCoalescingOperatorUsageInspection */
            $struct->set($key, '' === ($before[$primaryKey] ?? '') ? self::RESOLVED_MAPPED : self::RESOLVED_PRIMARY);
        }
        return $after;
    }

    private function resolvedStruct(Config $config): ArrayStruct
    {
        $mapping = $config->getMapping();
        $mapping->hasExtension(static::class) || $mapping->addArrayExtension(static::class, []);
        return $mapping->getExtensionOfType(static::class, ArrayStruct::class);
    }

    private function primaryKey(EntityDefinition $definition): string
    {
        $fields = $definition->getPrimaryKeys()->filter(fn(Field $field) => $field instanceof IdField);
        return $fields->count() === 1 ? $fields->first()->getPropertyName() : '';
    }

    public function resolved(Config $config, iterable $record): int
    {
        $struct = $this->resolvedStruct($config);
        $primaryKey = $struct->get(static::class);
        $record = [...$record];
        $key = $record[$primaryKey] ?? null;
        return $struct[$key] ?? self::RESOLVED_NONE;
    }
}
