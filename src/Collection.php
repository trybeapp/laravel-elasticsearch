<?php

namespace DesignMyNight\Elasticsearch;

use Illuminate\Database\Eloquent\Collection as BaseCollection;

class Collection extends BaseCollection
{
    public function addToIndex()
    {
        if ($this->isEmpty()) {
            return;
        }

        $query = $this->first()->newQueryWithoutScopes();

        $docs = $this->map(function ($model, $i) {
            return $model->onSearchConnection(
                fn ($model) => $model->toSearchableArray(),
                $model
            );
        });

        $success = $query->insert($docs->all());

        unset($docs);

        return $success;
    }
}
