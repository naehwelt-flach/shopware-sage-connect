<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Service;

use Naehwelt\Shopware\DataAbstractionLayer\Provider;
use Naehwelt\Shopware\ImportExport\EventSubscriber;
use Naehwelt\Shopware\ImportExport\ProcessFactory;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopware\Core\Content\ImportExport\Service\AbstractFileService;

readonly class EnrichCriteria
{
    public function __construct(
        private ProcessFactory $processFactory,
        private AbstractFileService $fileService,
    ) {
    }

    public function sendMessage(OrderEntity $order): void
    {
        $params = EventSubscriber::params(
            EventSubscriber::ON_ENRICH_EXPORT_CRITERIA,
            self::class,
            [['orderId' => $order->getId()]]
        );
        $log = $this->processFactory->sendMessage(params: $params);
        $file = $this->processFactory->provider->entity(ImportExportFileEntity::class, $log->getFileId());
        if ($file) {
            $this->fileService->updateFile($this->processFactory->provider->defaultContext, $file->getId(), [
                'originalName' => $this->getFileName($file->getOriginalName(), $order),
            ]);
        }
    }

    protected function getFileName(string $originalName, OrderEntity $order): string
    {
        return "{$order->getOrderNumber()}-$originalName";
    }

    public function __invoke(EnrichExportCriteriaEvent $event, array $params): void
    {
        foreach ($params as $param) {
            Provider::criteria($param, $event->getCriteria());
        }
    }
}
