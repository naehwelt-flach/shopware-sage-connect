<?php declare(strict_types=1);

namespace Naehwelt\Shopware\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\HttpFoundation\Request;

/**
 * @template TEntity of Entity
 */
readonly class Provider
{
    public function __construct(
        private DefinitionInstanceRegistry $registry,
        private RequestCriteriaBuilder $criteriaBuilder,
        public Context $defaultContext
    ) {}

    /**
     * @noinspection PhpDocSignatureInspection
     * @param class-string<TEntity> $class
     * @return ?TEntity
     */
    public function entity(
        string $class,
        string|array|Criteria $criteria,
        ?Context $context = null
    ): Entity|null {
        return $this->search($class, $criteria, $context)->first();
    }

    /**
     * @noinspection PhpDocSignatureInspection
     * @param class-string<TEntity> $class
     * @return EntitySearchResult<EntityCollection<TEntity>>|iterable<TEntity>
     */
    public function search(
        string $class,
        string|array|Criteria $criteria,
        ?Context $context = null
    ): EntitySearchResult {
        $def = $this->definition($class);
        $repo = $this->registry->getRepository($def->getEntityName());
        $context = $context ?? $this->defaultContext;
        return $repo->search(self::criteria($criteria), $context);
    }

    /**
     * @param class-string<TEntity> $class
     */
    public function payloadCriteria(string $class, Request|array $payload, ?Context $context = null): Criteria
    {
        $args = [$payload, new Criteria(), $this->definition($class), $context ?? $this->defaultContext];
        return match (true) {
            is_array($payload) => $this->criteriaBuilder->fromArray(...$args),
            default => $this->criteriaBuilder->handleRequest(...$args),
        };
    }

    /** @noinspection PhpInternalEntityUsedInspection */
    public static function criteria(Criteria|string|array $input, Criteria $criteria = null): Criteria
    {
        if ($input instanceof Criteria) {
            return $input;
        }
        $criteria ??= new Criteria();
        if (is_string($input)) {
            return $criteria->setIds(explode(',', $input));
        }
        foreach ($input as $field => $value) {
            if (is_object($value)) {
                match (true) {
                    $value instanceof ScoreQuery => $criteria->addQuery($value),
                    $value instanceof FieldSorting => $criteria->addSorting($value),
                    $value instanceof Aggregation => $criteria->addAggregation($value),
                    $value instanceof FieldGrouping => $criteria->addGroupField($value),
                    default => $criteria->addFilter($value),
                };
            } elseif (is_string($field)) {
                $criteria->addFilter(match(true) {
                    !is_array($value) => new EqualsFilter($field, $value),
                    is_string(array_key_first($value)) => new RangeFilter($field, $value),
                    default => new EqualsAnyFilter($field, $value)
                });
            } else {
                is_array($value) ? $criteria->addAssociations($value) : $criteria->addFields([$value]);
            }
        }
        return $criteria;
    }

    /**
     * @param class-string<TEntity>|string $classOrEntityName
     */
    public function definition(string $classOrEntityName): EntityDefinition
    {
        try {
            return $this->registry->getByClassOrEntityName($classOrEntityName);
        } catch(DefinitionNotFoundException $e) {
            return $this->registry->getByEntityClass(new $classOrEntityName);
        }
    }

    /**
     * @return class-string<TEntity>
     */
    public function cl(string $classOrEntityName): string
    {
        return $this->definition($classOrEntityName)->getEntityClass();
    }
}
