<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Naehwelt\Shopware\DataAbstractionLayer\Provider;
use Naehwelt\Shopware\Filesystem\MountManager;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Controller\ImportExportActionController;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

readonly class DirectoryHandler
{
    public function __construct(
        public MountManager $mountManager,
        private Provider $provider,
        private ImportExportActionController $controller,
        private string|array|Criteria $profileCriteria,
        private string $location = '',
        private bool $deleteAfterUpload = false,
        private ?string $expireDate = null,
        private array $config = [],
        private ?LoggerInterface $logger = null,
        private string $logLevel = LogLevel::DEBUG,
    ) {
    }

    public function with(
        null|string|array|Criteria $profileCriteria = null,
        null|string $location = null,
        null|bool $deleteAfterUpload = null,
        null|string $expireDate = null,
        null|array $config = null,
        null|MountManager $mountManager = null,
    ): static {
        $args = array_filter([
            'location' => $location,
            'profileCriteria' => $profileCriteria,
            'deleteAfterUpload' => $deleteAfterUpload,
            'expireDate' => $expireDate,
            'config' => $config,
            'mountManager' => $mountManager,
        ], fn($v) => $v !== null);
        return new static(...$args + get_object_vars($this));
    }

    public function __invoke(Context $context = null): void
    {
        $context ??= $this->provider->defaultContext;
        foreach ($this->logEntities() as $logId => $logEntity) {
            $this->controller->process(new Request(request: ['logId' => $logId]), $context);
            $this->logger?->log($this->logLevel, __METHOD__ . " ---> {logEntity}", [
                'logEntity' => $logEntity,
            ]);
        }
    }

    /**
     * @return iterable<string, ImportExportLogEntity>
     */
    protected function logEntities(): iterable
    {
        foreach ($this->logResponses() as $req => $logResponse) {
            $log = json_decode((string)$logResponse->getContent(), true)['log'] ?? [];
            yield $log['id'] => $this->provider->entity(ImportExportLogEntity::class, $log['id']);
        }
    }

    /**
     * @return iterable<Request, JsonResponse>
     */
    protected function logResponses(): iterable
    {
        $profile = $this->provider->entity(ImportExportProfileEntity::class, $this->profileCriteria);
        assert($profile instanceof ImportExportProfileEntity);
        $type = $profile->getFileType();
        foreach ($this->mountManager->files($type, $this->location, copy: !$this->deleteAfterUpload) as $args => $_) {
            $req = new Request(request: [
                'profileId' => $profile->getId(),
                'config' => $this->config,
                'expireDate' => $this->expireDate,
            ], files: ['file' => new UploadedFile(...array_values($args))]);
            yield $req => $this->controller->initiate($req, $this->provider->defaultContext);
        }
    }
}
