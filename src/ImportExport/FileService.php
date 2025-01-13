<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Processing;
use Symfony\Component\Mime\MimeTypes;

class FileService extends \Shopware\Core\Content\ImportExport\Service\FileService
{
    public function generateFilename(ImportExportProfileEntity $profile): string
    {
        $extension = MimeTypes::getDefault()->getExtensions($profile->getFileType())[0] ?? 'csv';
        $timestamp = date('Ymd-His');
        $label = $profile->getTranslation('label');
        \assert(\is_string($label));
        return \sprintf('%s_%s.%s', $label, $timestamp, $extension);
    }
}
