<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Service;

use Naehwelt\Shopware\DataAbstractionLayer\Provider;
use Naehwelt\Shopware\ImportExport\Event\BeforeImportRecordEvent;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Contracts\Service\ResetInterface;

class ProductVisibilityByStock implements ResetInterface
{
    private array $targetCategories = [];

    public function __construct(
        readonly LoggerInterface $logger,
        readonly EntityRepository $productRepository,
        readonly private Provider $provider
    ) {
    }

    public function __invoke(BeforeImportRecordEvent $event, array $params): void
    {
        $this->logger->notice('before import update... started');
        if (empty($params['category_urls']) || !is_array($params['category_urls'])) {
            return;
        }
        $this->setTargetCategories($params['category_urls']);
        if (empty($this->targetCategories)) {
            return;
        }

        $record = $event->getRecord();
        if (!isset($record['productNumber'])) {
            return;
        }
        $product = $this->provider->entity(ProductEntity::class,
            ['productNumber' => $record['productNumber']]);
        if (!$product) {
            return;
        }

        $productCategoryTree = $product->getCategoryTree() ?? [];
        $filterCategories = array_filter($this->targetCategories,
            function ($targetCategory) use ($productCategoryTree) {
                return in_array($targetCategory['id'], $productCategoryTree, true);
            });

        if (empty($filterCategories)) {
            return;
        }

        $lastCategory = array_pop($filterCategories);
        if (isset($record['stock']) && $record['stock'] < $lastCategory['minStock']) {
            $record['stock'] = 0;
            $event->setRecord($record);
            $this->logger->notice('updated record: ' . $record['productNumber']);
        }
    }

    private function setTargetCategories(array $categoryUrls): void
    {
        if (!empty($this->targetCategories)) {
            return;
        }

        $seoUrls = $this->provider->search(SeoUrlEntity::class,
            ['seoPathInfo' => array_keys($categoryUrls)])->getElements();
        foreach ($seoUrls as $seoUrl) {
            if (isset($categoryUrls[$seoUrl->getSeoPathInfo()])) {
                $this->targetCategories[] = [
                    'id' => $seoUrl->getForeignKey(),
                    'minStock' => $categoryUrls[$seoUrl->getSeoPathInfo()],
                ];
            }
        }
    }


    public function reset(): void
    {
        $this->targetCategories = [];
    }
}