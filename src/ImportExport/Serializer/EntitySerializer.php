<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Serializer;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\EntitySerializer as BaseEntitySerializer;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\ArrayNormalizer;

class EntitySerializer extends BaseEntitySerializer
{
    public function serialize(Config $config, EntityDefinition $definition, $entity): iterable
    {
        $expanded = ArrayNormalizer::expand(array_fill_keys(array_keys($config->getMapping()->getElements()), true));
        yield from $this->expand(parent::serialize($config, $definition, $entity), $expanded, $config, $definition);
    }

    private function expand(iterable|Struct $struct, array $expanded, Config $config, EntityDefinition $definition): iterable
    {
        if ($struct instanceof Entity) {
            $struct = $this->serializerRegistry->getEntity($definition->getEntityName())->serialize($config, $definition, $struct);
        } elseif ($struct instanceof Struct) {
            $struct = $struct->jsonSerialize();
        }
        foreach ($struct as $key => $value) {
            if (is_array($keys = $expanded[$key] ?? null) && (is_iterable($value) || $value instanceof Struct)) {
                $value = [...$this->expand($value, $keys, $config, $definition)];
            }
            yield $key => $value;
        }
    }

    public function reset(): void
    {
    }
}
