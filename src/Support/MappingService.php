<?php

namespace DesignMyNight\Elasticsearch\Support;

use DesignMyNight\Elasticsearch\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class MappingService
{
    protected Connection $connection;

    public function __construct()
    {
        $this->connection = new Connection(Config::get('database.connections.elasticsearch'));
    }

    public function setConnection(Connection $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function getMappings(): Collection
    {
        return $this->mappingsTable()->get();
    }

    public function getIndices(): array
    {
        return collect($this->connection->cat()->indices(['format' => 'json'])->asArray())->sortBy('index')->toArray();
    }

    public function getIndicesForAlias(string $alias = '*'): array
    {
        return collect($this->connection->cat()->aliases(['format' => 'json'])->asArray())
            ->filter(function (array $indexDetails) use ($alias) {
                return $alias === '*' || $indexDetails['alias'] === $alias;
            })
            ->map(function (array $indexDetails) {
                return [
                    'alias' => $indexDetails['alias'],
                    'index' => $indexDetails['index'],
                ];
            })
            ->all();
    }

    public function createIndex(string $index, array $body): void
    {
        $this->connection->indices()->create([
            'index' => $index,
            'body'  => $body,
          ]);
    }

    public function deleteIndex(string $index): void
    {
        $this->connection->indices()->delete(['index' => $index]);
    }

    /**
     * Point $alias at $index, unless it is already there.
     *
     * @return bool True when the alias was created, false when it already
     *              pointed at $index and there was nothing to do.
     *
     * @throws AliasAlreadyExistsException When the name is taken by a
     *                                     different index — a real conflict,
     *                                     as opposed to work already done.
     */
    public function createAlias(string $index, string $alias): bool
    {
        if ($this->connection->indices()->existsAlias(['name' => $alias])->asBool()) {
            $indices = array_column($this->getIndicesForAlias($alias), 'index');

            // Already where we were asked to point it. Re-running a mapping
            // migration against an environment that has one is not a failure,
            // so say nothing and let the caller carry on.
            if (in_array($index, $indices, true)) {
                return false;
            }

            throw new AliasAlreadyExistsException($alias, $indices);
        }

        $this->connection->indices()->putAlias([
            'index' => $index,
            'name' => $alias
        ]);

        return true;
    }

    public function updateAliases(string $alias, string $currentIndex, string $newIndex): void
    {
        $body = [
            'actions' => [
                [
                    'remove' => [
                        'index' => $currentIndex,
                        'alias' => $alias,
                    ],
                ],
                [
                    'add' => [
                        'index' => $newIndex,
                        'alias' => $alias,
                    ],
                ],
            ],
        ];

        $this->connection->indices()->updateAliases(['body' => $body]);
    }

    public function reindex(string $from, string $to): array
    {
        $body = [
            'source' => ['index' => $from],
            'dest' => ['index' => $to],
        ];

        return $this->connection->reindex(['body' => json_encode($body)]);
    }

    public function updateMapping(string $index, array $mappings)
    {
        $this->connection->indices()
            ->putMapping([
                'index' => $index,
                'body'  => $mappings,
            ]);
    }

    public function getNextMappingBatch(): int
    {
        return $this->mappingsTable()->max('batch') + 1;
    }

    public function addMappingRecord(int $batch, string $mapping): void
    {
        $this->mappingsTable()->insert([
            'batch'   => $batch,
            'mapping' => $mapping,
        ]);
    }

    protected function mappingsTable(): \Illuminate\Database\Query\Builder
    {
        return DB::connection()->table(config('laravel-elasticsearch.mappings_migration_table', 'mappings'));
    }
}
