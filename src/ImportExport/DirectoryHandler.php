<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use League\Flysystem\FilesystemOperator;
use Naehwelt\Shopware\DataAbstractionLayer\Provider;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Shopware\Core\Content\ImportExport\Controller\ImportExportActionController;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\MimeTypes;

readonly class DirectoryHandler
{
    public function __construct(
        private Provider $provider,
        private ImportExportActionController $controller,
        private FilesystemOperator $sourceFs,
        private string|array|Criteria $profileCriteria,
        private string $location = '',
        private ?string $expireDate = null,
        private array $config = [],
        private ?LoggerInterface $logger = null,
        private string $logLevel = LogLevel::DEBUG,
    ) {}

    public function __invoke(): void
    {
        $profile = $this->provider->entity(ImportExportProfileEntity::class, $this->profileCriteria);
        assert($profile instanceof ImportExportProfileEntity);
        $mimes = MimeTypes::getDefault();
        $type = $profile->getFileType();
        foreach ($this->sourceFs->listContents($this->location) as $file) {
            if ($file->isDir()) {
                continue;
            }
            if (!in_array($type, $mimes->getMimeTypes(pathinfo($path = $file->path(), PATHINFO_EXTENSION)), true)) {
                continue;
            }
            $uploaded = new UploadedFile(
                stream_get_meta_data($this->sourceFs->readStream($path))['uri'],
                $path,
                $type
            );
            $req = new Request(request: [
                'profileId' => $profile->getId(),
                'config' => $this->config,
                'expireDate' => $this->expireDate,
            ], files: ['file' => $uploaded]);
            $res = $this->controller->initiate($req, $this->provider->defaultContext);
            $this->logger?->log($this->logLevel, __METHOD__ . " '{profile}' ---> {req} ---> {res}", [
                'profile' => $profile->getTechnicalName(),
                'req' => $req,
                'res' => $res,
            ]);
        }
    }
}
