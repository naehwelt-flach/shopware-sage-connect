<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Shopware\Core\Content\ImportExport\Processing;
use Shopware\Core\Content\ImportExport\Struct\Config;

class FileReader extends Processing\Reader\AbstractReader
{
    public function __construct(private readonly AbstractFileHandler $handler){}

    private int $offset = 0;

    public function read(Config $config, $resource, int $offset): iterable
    {
        foreach ($this->handler->serialize($config, $resource, $this->offset = $offset) as $position => $record) {
            $this->offset = $position;
            yield $record;
        }
    }

    public function getOffset(): int
    {
        return $this->offset;
    }
}
