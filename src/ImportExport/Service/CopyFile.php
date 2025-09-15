<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Service;

use horstoeko\stringmanagement\PathUtils;
use League\Flysystem\FilesystemOperator;
use Naehwelt\Shopware\DataAbstractionLayer\Provider;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Service\AbstractFileService;

readonly class CopyFile
{
    public function __construct(
        private Provider $provider,
        private AbstractFileService $fileService,
        private FilesystemOperator $fromFs,
        private FilesystemOperator $toFs,
    ) {}

    public function __invoke(ImportExportLogEntity $log, array $params): void
    {
        $file = $this->provider->entity(ImportExportFileEntity::class, $log->getFileId());
        if (!$file) {
            return;
        }

        $name = $file->getOriginalName();
        if ($rename = $params['rename'] ?? '') {
            $name = sprintf($rename, $name);
            $this->fileService->updateFile($this->provider->defaultContext, $file->getId(), [
                'originalName' => $name,
            ]);
        }
        if ($copy = $params['copy'] ?? '') {
            $stream = $this->fromFs->readStream($file->getPath());
            PathUtils::
            $this->toFs->writeStream("$copy$name", $stream);
        }
    }
}
