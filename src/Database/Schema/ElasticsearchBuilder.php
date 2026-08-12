<?php

namespace DesignMyNight\Elasticsearch\Database\Schema;

use Closure;
use DesignMyNight\Elasticsearch\Connection;
use DesignMyNight\Elasticsearch\Support\SchemaCompatibility;
use Illuminate\Database\Schema\Builder;

/**
 * Class Builder
 * @package DesignMyNight\Elasticsearch\Database\Schema
 * @property Connection $connection
 */
class ElasticsearchBuilder extends Builder
{
    /**
     * @param         $table
     * @param Closure $callback
     */
    public function index($table, Closure $callback)
    {
        $this->table($table, $callback);
    }

    /**
     * @param string  $table
     * @param Closure $callback
     */
    public function table($table, Closure $callback)
    {
        $this->build(tap($this->createBlueprint($table), function (Blueprint $blueprint) use ($callback) {
            $blueprint->update();

            $callback($blueprint);
        }));
    }

    #[\Override]
    public function dropAllTables()
    {
        collect($this->connection->indices()->get(['index' => '*'])->asArray())->keys()->each(function (string $index) {
            $this->connection->indices()->delete(compact('index'));
        });
    }

    /**
     * @inheritDoc
     */
    protected function createBlueprint($table, ?Closure $callback = null)
    {
        return SchemaCompatibility::blueprintExpectsConnection()
            ? new Blueprint($this->connection, $table, $callback)
            : new Blueprint($table, $callback);
    }
}
