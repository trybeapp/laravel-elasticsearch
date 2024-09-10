<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use Illuminate\Support\Str;

class IndexRemoveCommand extends Command
{
    protected $description = 'Remove index from Elasticsearch';

    protected $signature = 'index:remove {index? : Name of the index to remove.}';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (!$index = $this->argument('index')) {
            $indices = collect($this->service->getIndices())->pluck('index')->toArray();
            $index = $this->choice('Which index would you like to delete?', $indices);
        }

        if (!$this->confirm("Are you sure you wish to remove the index {$index}?")) {
            return;
        }

        try {
            $this->service->deleteIndex($index);
        } catch (\Exception $exception) {
            $message = json_decode(Str::after($exception->getMessage(), ': '), true);
            $this->error("Failed to remove index: {$index}. Reason: {$message['error']['root_cause'][0]['reason']}");

            return false;
        }

        $this->info("Removed index: {$index}");

        return true;
    }
}
