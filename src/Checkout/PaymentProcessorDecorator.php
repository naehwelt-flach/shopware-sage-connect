<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\Checkout;

use Naehwelt\Shopware\ImportExport\ProcessFactory;
use Naehwelt\Shopware\ImportExport\Service\EnrichCriteria;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Event\FinalizePaymentOrderTransactionCriteriaEvent;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class PaymentProcessorDecorator extends PaymentProcessor
{
    /**
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(
        readonly private PaymentProcessor $inner,
        readonly private ProcessFactory $processFactory,
        readonly private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener]
    public function onFinalize(FinalizePaymentOrderTransactionCriteriaEvent $event): void
    {
        // export after finalization, for async payments (PayPal)
        $this->exportMessage('payment_finalize', $event->getOrderTransactionId());
    }

    /** @noinspection NullPointerExceptionInspection */
    private function exportMessage(string $log, string|bool $transactionId = null, string $orderId = null): void
    {
        try {
            $context = [
                'transactionId' => $transactionId,
                'orderId' => $orderId,
            ];
            if (is_string($transactionId)) {
                $transaction = $this->processFactory->provider->entity(OrderTransactionEntity::class, $transactionId);
                $orderId = $transaction->getOrderId();
                $context += [
                    'transactionUpdated' => $transaction->getUpdatedAt(),
                    'transactionCustomFields' => $transaction->getCustomFields()
                ];
            }
            if ($orderId) {
                $order = $this->processFactory->provider->entity(OrderEntity::class, $orderId);
                $context += [
                    'orderNumber' => $order->getOrderNumber(),
                    'orderDateTime' => $order->getOrderDateTime(),
                    'orderCustomer' => $order->getOrderCustomer(),
                ];
            }
            $this->logger->info($log, $context);
            if ($transactionId && $orderId) {
                $this->processFactory->sendMessage(params: EnrichCriteria::params([['orderId' => $orderId]]));
            }
        } catch (Throwable $error) {
            $this->logger->error($error->getMessage(), ['e' => $error]);
        }
    }

    public function pay(
        string $orderId,
        Request $request,
        SalesChannelContext $salesChannelContext,
        ?string $finishUrl = null,
        ?string $errorUrl = null,
    ): ?RedirectResponse {
        $res = [$this->inner, __FUNCTION__](...func_get_args());
        // export directly, only for immediate payments (cash in advance)
        $this->exportMessage('payment_pay', !$res, $orderId);
        return $res;
    }

    public function finalize(TokenStruct $token, Request $request, SalesChannelContext $context): TokenStruct
    {
        return [$this->inner, __FUNCTION__](...func_get_args());
    }

    public function validate(Cart $cart, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): ?Struct
    {
        return [$this->inner, __FUNCTION__](...func_get_args());
    }
}
