<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Shopware\Core\Content\ImportExport\Processing;
use Shopware\Core\Content\ImportExport\Struct\Config;

abstract class AbstractFileHandler
{
    abstract public function deserialize(Config $config, $resource, array $record, int $index): void;

    abstract public function serialize(Config $config, $resource, int $offset): iterable;
}
