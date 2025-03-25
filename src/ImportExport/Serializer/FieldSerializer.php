<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Serializer;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer as BaseFieldSerializer;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;

class FieldSerializer extends BaseFieldSerializer
{
    public function serialize(Config $config, Field $field, $value): iterable
    {
        if ($field->getFlag(Computed::class)) {
            yield $field->getPropertyName() => $value;
        } else {
            yield from parent::serialize($config, $field, $value);
        }
    }
}
