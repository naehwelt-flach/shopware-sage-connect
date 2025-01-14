<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExport\Processing;
use Shopware\Core\Content\ImportExport\Struct\Config;

class FileWriter extends Processing\Writer\AbstractFileWriter
{
    public function __construct(
        FilesystemOperator $filesystem,
        private readonly AbstractFileHandler $handler,
    ){
        parent::__construct($filesystem);
    }

    public function append(Config $config, array $data, int $index): void
    {
        $this->handler->deserialize($config, $this->buffer, $data, $index);
    }
}
