<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Processing;

class ReaderFactory extends Processing\Reader\AbstractReaderFactory
{
    readonly private object $creator;
    public function __construct(
        readonly private string|iterable $types,
        callable|Processing\Reader\AbstractReader $creator,
    ) {
        $this->creator = $creator;
    }

    public function create(ImportExportLogEntity $logEntity): Processing\Reader\AbstractReader
    {
        return is_callable($this->creator) ? ($this->creator)() : $this->creator;
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        is_string($types = $this->types) && $types = (array)$types;
        return isset(array_flip([...$types])[$logEntity->getProfile()?->getFileType()]);
    }
}
