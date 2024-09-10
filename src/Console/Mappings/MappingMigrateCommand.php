<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Traits\UpdatesAlias;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

class MappingMigrateCommand extends Command
{
    use UpdatesAlias;

    /** @var string $description */
    protected $description = 'Run all required mapping migrations';

    /** @var string $signature */
    protected $signature = 'migrate:mappings
        {artisan-command? : Local Artisan indexing command. Defaults to config.}
        {--I|index : Index mapping on migration}
        {--S|swap : Automatically update alias.}';

    protected Filesystem $files;

    public function handle(Filesystem $files)
    {
        $this->files = $files;

        $mappings = $this->getMappingFiles();
        $mappingMigrations = $this->service->getMappings()
            ->sortBy('mapping')
            ->sortBy('batch')
            ->pluck('mapping');

        $pendingMappings = $this->pendingMappings($mappings, $mappingMigrations->toArray());

        $this->runPending($pendingMappings);
    }

    protected function createIndex(string $index, array $body):void
    {
        $this->line("Creating index $index");

        $this->service->createIndex($index, $body);

        $this->info("Created index $index");
    }

    /**
     * @return SplFileInfo[]
     */
    protected function getMappingFiles(): array
    {
        return $this->files->files(base_path('database/mappings'));
    }

    protected function getMappingName(string $mapping, bool $withSuffix = false): string
    {
        $mapping = str_replace('.json', '', $mapping);

        if ($withSuffix) {
            $mapping .= config('database.connections.elasticsearch.suffix');
        }

        return $mapping;
    }

    protected function index(string $index): void
    {
        if (!($command = $this->argument('artisan-command'))) {
            $command = config('laravel-elasticsearch.index_command');
        }

        $this->info("Indexing mapping: {$index}");

        // Begin indexing.
        $this->call($command, ['index' => $index]);

        $this->info("Indexed mapping: {$index}");
    }

    /**
     * @return SplFileInfo[]
     */
    protected function pendingMappings(array $files, array $migrations): array
    {
        return Collection::make($files)
          ->reject(function (SplFileInfo $file) use ($migrations):bool {
              return in_array($this->getMappingName($file->getFilename()), $migrations);
          })
          ->values()
          ->toArray();
    }

    protected function putMapping(SplFileInfo $mapping):void
    {
        $index = $this->getMappingName($mapping->getFileName(), true);
        $mapping = json_decode($mapping->getContents(), true);

        if (Str::contains($index, 'update')) {
            $this->updateIndex($index, $mapping);

            return;
        }

        $this->createIndex($index, $mapping);
    }

    /**
     * @param SplFileInfo[] $pending
     */
    protected function runPending(array $pending): void
    {
        if (empty($pending)) {
            $this->info('No new mappings to migrate.');

            return;
        }

        $batch = $this->service->getNextMappingBatch();

        $createdAliases = [];

        foreach ($pending as $mapping) {
            $index = $this->getMappingName($mapping->getFileName());
            $indexWithSuffix = $this->getMappingName($index, true);
            $aliasName = $this->getAlias($indexWithSuffix);

            $this->info("Migrating mapping: {$index}");

            try {
                $this->putMapping($mapping);
            } catch (\Exception $exception) {
                $this->error("Failed to put mapping: {$index} because {$exception->getMessage()}");

                return;
            }

            $this->service->addMappingRecord($batch, $index);

            try {
                $this->call('make:mapping-alias', [
                  'name'  => $aliasName,
                  'index' => $indexWithSuffix
                ]);

                $createdAliases[] = $aliasName;
            } catch (\Exception $e) {
                $this->info("Migrating mapping alias error: {$e->getMessage()}");
            }

            $this->info("Migrated mapping: {$index}");

            if (!Str::contains($index, 'update') && $this->option('index')) {
                $this->index($index);

                if (in_array($aliasName, $createdAliases) || $this->option('swap')) {
                    $this->updateAlias($this->getMappingName($index, true));
                }
            }
        }
    }

    protected function updateIndex(string $index, array $mappings): void
    {
        $index = preg_replace('/[0-9_].+update_/', '', $index);

        $this->line("Updating index mapping $index");

        $this->service->updateMapping($index, $mappings);

        $this->info("Updated index mapping $index");
    }
}
