<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Serializer;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer as BaseFieldSerializer;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;

class FieldSerializer extends BaseFieldSerializer
{
    public function serialize(Config $config, Field $field, $value): iterable
    {
        if (self::direct($field, $value)) {
            yield $field->getPropertyName() => $value;
        } else {
            yield from parent::serialize(...func_get_args());
        }
    }

    private static function direct(Field $field, $value): bool
    {
        if ($field instanceof JsonField && is_array($value)) {
            return true;
        }
        if ($field->getFlag(Computed::class)) {
            return true;
        }
        return false;
    }
}
