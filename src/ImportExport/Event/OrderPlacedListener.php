<?php declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Event;

use Naehwelt\Shopware\ImportExport\ProcessFactory;
use Naehwelt\Shopware\ImportExport\Service\EnrichCriteria;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class OrderPlacedListener
{
    public function __construct(private ProcessFactory $processFactory) {}

    public function __invoke(CheckoutOrderPlacedEvent $event): void
    {
        $this->processFactory->sendMessage(params: EnrichCriteria::params($event->getOrder()->getId()));
    }
}
