<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Shopware\Core\Content\ImportExport\Processing;
use Shopware\Core\Content\ImportExport\Struct\Config;

class YamlReader extends Processing\Reader\AbstractReader
{
    public function read(Config $config, $resource, int $offset): iterable
    {
        throw new \RuntimeException('todo: implement');
    }

    public function getOffset(): int
    {
        throw new \RuntimeException('todo: implement');
    }
}
