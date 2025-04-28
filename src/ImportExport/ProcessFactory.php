<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport;

use Naehwelt\Shopware\DataAbstractionLayer\Provider;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Controller\ImportExportActionController;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * @psalm-type UploadFileOrArgs UploadedFile|array{path: string, originalName: string, mimeType: string}|null
 */
class ProcessFactory
{
    private ImportExportProfileEntity $profile;

    public function __construct(
        readonly public Provider $provider,
        readonly private ImportExportActionController $controller,
        readonly private string|array|Criteria $profileCriteria,
        readonly private ?string $expireDate = null,
        readonly private array $config = []
    ) {
    }

    public function with(
        null|string|array|Criteria $profileCriteria = null,
        null|string $expireDate = null,
        null|array $config = null,
    ): static {
        return new static(
            $this->provider,
            $this->controller,
            $profileCriteria ?? $this->profileCriteria,
            $expireDate ?? $this->expireDate,
            $config ?? $this->config,
        );
    }

    /**
     * @param UploadFileOrArgs $import
     */
    public function sendMessage(
        UploadedFile|array $import = null,
        array $params = [],
        Context $context = null,
        bool $cancel = false
    ): ImportExportLogEntity {
        $logEntity = $this->prepare($import, $params, $context);
        return $this->process($logEntity, $cancel, $context);
    }

    /**
     * @param UploadFileOrArgs $import
     */
    protected function prepare(
        UploadedFile|array $import = null,
        array $params = [],
        Context $context = null
    ): ImportExportLogEntity {
        $req = new Request(request: [
            'profileId' => $this->profile()->getId(),
            'config' => array_replace_recursive($this->config, ['parameters' => $params]),
            'expireDate' => $this->expireDate,
        ], files: $import ? ['file' => is_array($import) ? new UploadedFile(...$import) : $import] : []);
        $res = $this->controller->initiate($req, $context ?? $this->provider->defaultContext);
        $logId = json_decode((string)$res->getContent(), true)['log']['id'];
        return $this->provider->entity(ImportExportLogEntity::class, $logId);
    }

    public function profile(): ImportExportProfileEntity
    {
        $this->profile ??= $this->provider->entity(ImportExportProfileEntity::class, $this->profileCriteria);
        return $this->profile;
    }

    protected function process(
        string|ImportExportLogEntity $logEntity,
        bool $cancel = false,
        Context $context = null
    ): ImportExportLogEntity {
        $context ??= $this->provider->defaultContext;
        if (is_string($logEntity)) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $logEntity = $this->provider->entity(ImportExportLogEntity::class, $logEntity, $context);
        }
        $req = new Request(request: ['logId' => $logEntity->getId()]);
        $cancel ? $this->controller->cancel($req, $context) : $this->controller->process($req, $context);
        return $logEntity;
    }
}
