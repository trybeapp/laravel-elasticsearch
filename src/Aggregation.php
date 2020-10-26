<?php

namespace DesignMyNight\Elasticsearch;

use DesignMyNight\Elasticsearch\QueryBuilder;
use Illuminate\Support\Str;

/**
 * `Aggregation` classes may be passed to the query builder's `aggregation()` method. Extend this class and include
 * either class properties or methods to return the required aggregation key (string), type (string), arguments (as a
 * string, array or QueryBuilder) and optional sub aggregations (as a QueryBuilder).
 *
 * The above nested aggregation could be carried out with `Aggregation` classes like so:
 * ```
 * $results = Customer::search()
 *     ->where('dob_month', 11)
 *     ->aggregation((new ByCustomerAggregation)
 *         ->setSubAggregation(new TotalRevenueAggregation)
 *     );
 * ```
 *
 * If multiple sub aggregations are required, pass a `callable` to `setSubAggregation` which will be passed a
 * `QueryBuilder` instance and apply the aggregations to that class, for example:
 * ```
 * $results = Customer::search()
 *     ->aggregation((new ByCustomerAggregation)
 *         ->setSubAggregation(function ($builder) {
 *             $builder->aggregation(new TotalRevenueAggregation);
 *             $builder->aggregation('total_transactions', 'count', 'revenue');
 *         })
 *     );
 * ```
 */
abstract class Aggregation
{
    /**
     * @var string
     */
    protected $type = '';

    /**
     * @var string
     */
    protected $key;

    /**
     * @var Aggregation[]
     */
    protected $subAggregations = [];

    /**
     * @param string $key
     * @return self
     */
    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key ?: Str::snake(class_basename($this));
    }

    /**
     * @param QueryBuilder $builder
     * @return mixed
     */
    public function getArguments(QueryBuilder $builder)
    {
        return $this->arguments ?? [];
    }

    /**
     * @return self
     */
    public function setSubAggregation($subAggregation): self
    {
        $this->subAggregations = [$subAggregation];

        return $this;
    }

    /**
     * @return self
     */
    public function addSubAggregation($subAggregation): self
    {
        $this->subAggregations[] = $subAggregation;

        return $this;
    }

    /**
     * @param QueryBuilder $builder
     * @return QueryBuilder
     */
    public function applySubAggregations(QueryBuilder $builder): ?QueryBuilder
    {
        foreach ($this->subAggregations as $subAggregation) {
            if ($subAggregation instanceof Aggregation) {
                $subAggregation->apply($builder);
            } elseif (is_callable($subAggregation)) {
                call_user_func($subAggregation, $builder);
            }
        }

        return $builder;
    }

    /**
     * @param QueryBuilder $builder
     * @return QueryBuilder
     */
    public function apply(QueryBuilder $builder): QueryBuilder
    {
        return $builder->aggregation(
            $this->getKey(),
            $this->getType(),
            $this->getArguments($builder->newQuery()),
            $this->applySubAggregations($builder->newQuery())
        );
    }
}
