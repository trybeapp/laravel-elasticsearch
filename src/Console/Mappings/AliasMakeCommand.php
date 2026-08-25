<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Support\AliasAlreadyExistsException;
use Exception;

class AliasMakeCommand extends Command
{
    protected $description = 'Create new alias.';

    protected $signature = 'make:mapping-alias
        {name : Name of the mapping alias.}
        {index? : Name of index to point to}';

    public function handle()
    {
        $aliasName = $this->argument('name');

        try {
            $indexName = $this->getIndexName();

            $created = $this->service->createAlias($indexName, $aliasName);
        } catch (AliasAlreadyExistsException $exception) {
            // A warning, not an error: the alias exists, which is what we were
            // asked to bring about. Moving one that is already in use is what
            // index:swap and migrate:mappings --swap are for, so do not fail
            // a re-run that simply has nothing left to do.
            $this->warn($exception->getMessage());

            return;
        } catch (Exception $exception) {
            $this->error($exception->getMessage());

            return;
        }

        if (!$created) {
            $this->info("Alias $aliasName already points at $indexName.");

            return;
        }

        $this->info("Alias $aliasName created successfully.");
    }

    protected function getIndexName():string
    {
        if (!$indexName = $this->argument('index')) {
            $indices = collect($this->service->getIndices())
              ->sortBy('index')
              ->pluck('index')
              ->toArray();

            $indexName = $this->choice('Which index do you want to create an alias for?', $indices);
        }

        return $indexName;
    }
}
