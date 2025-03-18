<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Event;

use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;

class BeforeImportRecordEvent extends ImportExportBeforeImportRecordEvent
{
    public function __construct(
        readonly public bool $isInserted,
        readonly public bool $isMapped,
        array $record,
        array $row,
        Config $config,
        Context $context,
    ) {
        parent::__construct($record, $row, $config, $context);
    }
}
