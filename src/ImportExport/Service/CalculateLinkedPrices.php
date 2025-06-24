<?php

declare(strict_types=1);

namespace Naehwelt\Shopware\ImportExport\Service;

use Naehwelt\Shopware\DataAbstractionLayer\Provider;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Symfony\Contracts\Service\ResetInterface;

class CalculateLinkedPrices implements ResetInterface
{
    public const MAPPING = [
        ProductDefinition::class => ['tax' => ['price']]
    ];
    private array $cache = [];

    public function __construct(
        readonly private Provider $provider,
        readonly private NetPriceCalculator $netCalculator,
        readonly private GrossPriceCalculator $grossCalculator,
        readonly private array $mapping = self::MAPPING,
    ) {
    }

    public function __invoke(ImportExportBeforeImportRecordEvent $event): void
    {
        $record = $event->getRecord();
        $sourceEntity = $event->getConfig()->get('sourceEntity');
        $calc = $this->calculator($event->getContext());
        foreach ($this->taxRulesWithPrices($sourceEntity, $record, $event->getContext()) as $taxRules => $field) {
            $property = $field->getPropertyName();
            $prices = $field->getSerializer()->decode($field, $record[$property] ?? null);
            foreach ($prices ?? [] as $id => $price) {
                $price = $calc($taxRules, $price);
                $record[$property][$id] = $price->getVars();
            }
        }
        $event->setRecord($record);
    }

    /**
     * @return callable(): Price
     */
    public function calculator(Context $context): callable
    {
        $rounding = $context->getRounding();
        return function (TaxRuleCollection $taxRules, Price $price) use ($rounding) {
            foreach ([$price, $price->getListPrice(), $price->getRegulationPrice()] as $item) {
                if (!$item instanceof Price || !$price->getLinked()) {
                    continue;
                }
                if (!$item->getNet()) {
                    $qpd = new QuantityPriceDefinition($item->getGross(), $taxRules);
                    $calc = $this->grossCalculator->calculate($qpd, $rounding);
                    $taxes = $calc->getCalculatedTaxes()->getAmount();
                    $item->setNet($item->getGross() - $taxes);

                } elseif (!$item->getGross()) {
                    $qpd = new QuantityPriceDefinition($item->getNet(), $taxRules);
                    $calc = $this->netCalculator->calculate($qpd, $rounding);
                    $taxes = $calc->getCalculatedTaxes()->getAmount();
                    $item->setGross($price->getNet() + $taxes);
                }
            }
            return $price;
        };
    }

    /**
     * @return iterable<TaxRuleCollection, PriceField>
     */
    private function taxRulesWithPrices(string $sourceEntity, array $record, Context $context): iterable
    {
        foreach ($this->fields($sourceEntity) as $fkField => $priceField) {
            yield $this->tax($fkField, $record, $context) => $priceField;
        }
    }

    /**
     * @return iterable<FkField|ManyToOneAssociationField, PriceField>
     */
    private function fields(string $sourceEntity): iterable
    {
        $def = $this->provider->definition($sourceEntity);
        foreach ($this->mapping[$def->getClass()] ?? [] as $taxFieldName => $priceFieldsNames) {
            $taxField = $def->getField($taxFieldName);
            foreach ($priceFieldsNames as $priceFieldName) {
                yield $taxField => $def->getField($priceFieldName);
            }
        }
    }

    private function tax(FkField|ManyToOneAssociationField $field, array $record, Context $context): TaxRuleCollection
    {
        $entityName = $field->getReferenceDefinition()->getEntityName();
        $id = $record[$field->getPropertyName()][$field->getReferenceField()] ?? null;
        if (!$id) {
            // todo fix it
            return $this->cache[$entityName][$id] ??= new TaxRuleCollection([]);
        }
        /** @noinspection NullPointerExceptionInspection */
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        return $this->cache[$entityName][$id] ??= new TaxRuleCollection([
            new TaxRule($this->provider->entity($this->provider->cl($entityName), $id, $context)->getTaxRate())
        ]);
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
