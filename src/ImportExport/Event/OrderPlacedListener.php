<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Event;

use Naehwelt\Shopware\ImportExport\ProcessFactory;
use Naehwelt\Shopware\ImportExport\Service\EnrichCriteria;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;

#[AsEventListener]
class OrderPlacedListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(readonly private ProcessFactory $processFactory)
    {
    }

    /**
     * @see \Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute::order
     */
    public function __invoke(CheckoutOrderPlacedEvent $event): void
    {
        try {
            $this->processFactory->sendMessage(params: EnrichCriteria::params([
                ['orderId' => $event->getOrder()->getId()]
            ]));
        } catch (Throwable $error) {
            $this->logger->error($error->getMessage(), ['e' => $error]);
        }
    }
}
