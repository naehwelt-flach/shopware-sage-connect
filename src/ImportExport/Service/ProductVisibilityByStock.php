<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Service;

use Naehwelt\Shopware\ImportExport\Event\BeforeImportRecordEvent;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Contracts\Service\ResetInterface;

class ProductVisibilityByStock implements ResetInterface
{
    public function __construct(readonly LoggerInterface $logger, readonly EntityRepository $productRepository)
    {
    }

    public function __invoke(BeforeImportRecordEvent $event, array $params): void
    {
        $this->logger->notice('before import update... started');
        $record = $event->getRecord();
        $context = $event->getContext();

        if (!isset($record['productNumber']) || !isset($params['minStock']) || !isset($params['targetCategories'])) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $record['productNumber']));
        $criteria->addFilter(
            new EqualsAnyFilter(
                'product.categoryTree',
                is_array($params['targetCategories']) ? $params['targetCategories'] : [$params['targetCategories']])
        );

        $product = $this->productRepository->search($criteria, $context)->first();
        if (!$product) {
            return;
        }

        if (isset($record['stock']) && $record['stock'] < $params['minStock']) {
            $record['stock'] = 0;
            $event->setRecord($record);
            $this->logger->notice('updated record: ' . $record['productNumber']);
        }
    }

    public function reset(): void
    {
    }
}