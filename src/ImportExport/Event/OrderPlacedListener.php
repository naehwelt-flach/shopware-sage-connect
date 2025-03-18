<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Event;

use Naehwelt\Shopware\ImportExport\ProcessFactory;
use Naehwelt\Shopware\ImportExport\Service\EnrichCriteria;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class OrderPlacedListener
{
    public function __construct(private ProcessFactory $processFactory)
    {
    }

    /**
     * @see \Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute::order
     */
    public function __invoke(CheckoutOrderPlacedEvent $event): void
    {
        $this->processFactory->sendMessage(params: EnrichCriteria::params([[
            'orderId' => $event->getOrder()->getId(),
            'type' => 'product'
        ]]));
    }
}
