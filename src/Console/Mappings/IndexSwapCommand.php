<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Class IndexSwapCommand
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class IndexSwapCommand extends Command
{
    use ConfirmableTrait;

    /** @var string $description */
    protected $description = 'Swap Elasticsearch alias';

    /** @var string $signature */
    protected $signature = 'index:swap
        {alias? : Name of alias to be updated.}
        {index? : Name of index to be updated to.}
        {--force : Force the operation to run when in production}';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (!$alias = $this->argument('alias')) {
            $alias = $this->choice(
                'Which alias would you like to update',
                $this->aliases()->pluck('alias')->toArray()
            );
        }

        if (!$index = $this->argument('index')) {
            $index = $this->choice(
                'Which index would you like the alias to point to',
                $this->indices()->pluck('index')->toArray()
            );
        }

        $this->line("Updating {$alias} to {$index}...");

        if ($this->confirmToProceed()) {
            try {
                $this->service->updateAliases($alias, $this->current($alias), $index);
            } catch (\Exception $exception) {
                $this->error("Failed to update alias: {$alias}. {$exception->getMessage()}");

                return;
            }

            $this->info("Updated {$alias} to {$index}");
        }
    }

    protected function current(string $alias): string
    {
        $aliases = $this->aliases();

        if (!$alias = $aliases->firstWhere('alias', $alias)) {
            $index = $this->choice(
                'Which index is the current index',
                $aliases->pluck('index')->toArray()
            );

            $alias = $aliases->firstWhere('index', $index);
        }

        return $alias['index'];
    }

    protected function indices(): Collection
    {
        return collect($this->service->getIndices())->sortByDesc('index');
    }

    protected function aliases(): Collection
    {
        return collect($this->service->getIndicesForAlias())->sortBy('alias');
    }
}
