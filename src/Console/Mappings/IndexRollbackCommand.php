<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Traits\UpdatesAlias;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class IndexRollbackCommand extends Command
{
    use UpdatesAlias;

    protected $description = 'Rollback to the previous index';

    protected $signature = 'index:rollback';

    private Collection $previousMigrations;

    public function handle()
    {
        $mappingMigrations = $this->service->getMappings()->sortBy('mapping')->orderBy('batch')->get();

        if ($mappingMigrations->isEmpty()) {
            $this->info('Nothing to rollback.');

            return;
        }

        $latestBatch = $this->service->getMappings()->take('batch');
        $mappingMigrations = $this->mapAliases($mappingMigrations);
        $latestMigrations = $mappingMigrations->where('batch', $latestBatch);
        $this->setPreviousMigrations($mappingMigrations->where('batch', $latestBatch - 1));

        foreach ($latestMigrations as $migration) {
            $this->rollback($migration);
        }

        $this->service->getMappings()->where('batch', $latestBatch)->delete();
        $this->info('Successfully rolled back.');
    }

    public function setPreviousMigrations(Collection $migrations): void
    {
        $this->previousMigrations = $migrations;
    }

    protected function appendSuffix(string $mapping): string
    {
        $suffix = config('database.connections.elasticsearch.suffix');

        if (Str::endsWith($mapping, $suffix)) {
            return $mapping;
        }

        return "{$mapping}{$suffix}";
    }

    protected function mapAliases(Collection $migrations): Collection
    {
        return $migrations->map(function (array $mapping):array {
            $mapping['alias'] = $this->appendSuffix($this->stripTimestamp($mapping['mapping']));
            $mapping['mapping'] = $this->appendSuffix($mapping['mapping']);

            return $mapping;
        });
    }

    protected function rollback(array $migration): void
    {
        if ($match = $this->previousMigrations->where('alias', $migration['alias'])->first()) {
            $this->info("Rolling back {$migration['mapping']} to {$match['mapping']}");
            $this->updateAlias($match['mapping'], null, $migration['mapping']);
            $this->info("Rolled back {$migration['mapping']}");

            return;
        }

        $this->warn("No previous migration found for {$migration['mapping']}. Skipping...");
    }

    protected function stripTimestamp(string $mapping): string
    {
        return preg_replace('/^[0-9_]+/', '', $mapping, 1);
    }
}
