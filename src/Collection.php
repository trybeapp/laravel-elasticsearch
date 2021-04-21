<?php

namespace DesignMyNight\Elasticsearch;

use Illuminate\Database\Eloquent\Collection as BaseCollection;

class Collection extends BaseCollection
{
    /**
     * @var array
     */
    protected $addToIndexResult;

    /**
     * @return bool
     */
    public function addToIndex(): bool
    {
        if ($this->isEmpty()) {
            return true;
        }

        $instance = $this->first();
        $instance->setConnection($instance->getElasticsearchConnectionName());
        $query = $instance->newQueryWithoutScopes();

        $docs = $this->map(function ($model, $i) {
            return $model->onSearchConnection(
                fn ($model) => $model->toSearchableArray(),
                $model
            );
        });

        $success = $query->insert($docs->all());

        $this->addToIndexResult = $query->getQuery()->getInsertResult();

        unset($docs);

        return $success;
    }

    /**
     * @return array|null
     */
    public function getAddToIndexResult(): ?array
    {
        return $this->addToIndexResult;
    }
}
