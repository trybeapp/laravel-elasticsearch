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
     * @var Aggregation
     */
    protected $subAggregation;

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
        $this->subAggregation = $subAggregation;

        return $this;
    }

    /**
     * @param QueryBuilder $builder
     * @return QueryBuilder|null
     */
    public function applySubAggregations(QueryBuilder $builder): ?QueryBuilder
    {
        if (isset($this->subAggregation)) {
            if ($this->subAggregation instanceof Aggregation) {
                $this->subAggregation->apply($builder);
            } elseif (is_callable($this->subAggregation)) {
                call_user_func($this->subAggregation, $builder);
            }

            return $builder;
        }

        return null;
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
            $this->applySubAggregation($builder->newQuery())
        );
    }
}
