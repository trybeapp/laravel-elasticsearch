<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings\Traits;

use Illuminate\Support\Str;

trait UpdatesAlias
{
    protected function getActiveIndex(string $alias): string
    {
        try {
            $indices = collect($this->service->getIndicesForAlias($alias));
        } catch (\Exception $exception) {
            $this->error('Failed to retrieve the current active index.');
        }

        $indices = $indices->filter(function (array $item) use ($alias):bool {
            return Str::contains($item['index'], $alias);
        })->sortByDesc('index');

        if ($indices->count() === 1) {
            return $indices->first()['index'];
        }

        $index = $this->choice('Which index is the current index?', $indices->pluck('index')->toArray(), 0);

        return $indices->firstWhere('index', $index)['index'];
    }

    /**
     * Change 2018_09_04_104700_update_pages_dev to pages_dev.
     */
    protected function getAlias(string $mapping): string
    {
        return preg_replace('/^\d{4}\_\d{2}\_\d{2}\_\d{6}\_(update_)?/', '', $mapping, 1);
    }

    protected function getIndex(string $alias): string
    {
        try {
            $indices = collect($this->service->getIndices());
        } catch (\Exception) {
            $this->error('An error occurred attempting to retrieve indices.');
        }

        $relevant = $indices->filter(function (array $item) use ($alias):bool {
            return Str::contains($item['index'], $alias);
        })->sortByDesc('index');

        return $this->choice('Which index would you like to use?', $relevant->pluck('index')->toArray(), 0);
    }

    protected function updateAlias(
        ?string $index,
        string $alias = null,
        ?string $currentIndex = null,
        bool $removeOldIndex = false
    ): void {
        $index = $index ?? $this->getIndex($alias);

        $this->line("Updating alias to index: {$index}");

        $alias = $alias ?? $this->getAlias($index);
        $currentIndex = $currentIndex ?? $this->getActiveIndex($alias);

        try {
            $this->service->updateAliases($alias, $currentIndex, $index);
        } catch (\Exception $exception) {
            $this->error("Failed to update alias: {$alias}. {$exception->getMessage()}");

            return;
        }

        $this->info("Updated alias to index: {$index}");

        if ($removeOldIndex) {
            $this->call('index:remove', [
                'index' => $currentIndex,
            ]);
        }
    }
}
