<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use Exception;

class AliasMakeCommand extends Command
{
    protected $description = 'Create new alias.';

    protected $signature = 'make:mapping-alias
        {name : Name of the mapping alias.}
        {index? : Name of index to point to}';

    public function handle()
    {
        try {
            $aliasName = $this->argument('name');
            $indexName = $this->getIndexName();

            $this->service->createAlias($indexName, $aliasName);
        } catch (Exception $exception) {
            $this->error($exception->getMessage());

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
