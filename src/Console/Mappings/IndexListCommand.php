<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

/**
 * Class IndexListCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class IndexListCommand extends Command
{
    /** @var string $description */
    protected $description = 'View all Elasticsearch indices';

    /** @var string $signature */
    protected $signature = 'index:list {--A|alias= : Name of alias indexes belong to.}';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $indices = $this->service->getIndicesForAlias($this->option('alias') ?? '*');

        if (empty($indices)) {
            $this->line('No aliases found.');

            return;
        }

        $this->table(array_keys($indices[0]), $indices);

        return;

        if ($indices = $this->indices()) {
            $this->table(array_keys($indices[0]), $indices);

            return;
        }

        $this->line('No indices found.');
    }
}
