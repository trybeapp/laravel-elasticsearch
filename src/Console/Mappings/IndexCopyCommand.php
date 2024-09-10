<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

class IndexCopyCommand extends Command
{
    protected $description = 'Populate an index with all documents from another index';

    protected $signature = 'index:copy {from?} {to?}';

    public function handle()
    {
        $from = $this->from();
        $to = $this->to();

        if ($this->confirm("Would you like to copy {$from} to {$to}?")) {
            try {
                $this->report(
                    $this->service->reindex($from, $to)
                );
            } catch (\Exception $exception) {
                $this->output->error($exception->getMessage());
            }
        }
    }

    protected function from(): string
    {
        if ($from = $this->argument('from')) {
            return $from;
        }

        return $this->choice(
            'Which index would you like to copy from?',
            collect($this->service->getIndices())->pluck('index')->toArray()
        );
    }

    /**
     * @return string
     */
    protected function to(): string
    {
        if ($to = $this->argument('to')) {
            return $to;
        }

        return $this->choice(
            'Which index would you like to copy to?',
            collect($this->service->getIndices())->pluck('index')->toArray()
        );
    }

    /**
     * @param array $result
     */
    private function report(array $result): void
    {
        // report any failures
        if ($result['failures']) {
            $this->output->warning('Failures');
            $this->output->table(array_keys($result['failures'][0]), $result['failures']);
        }

        // format results in strings
        $result['timed_out'] = $result['timed_out'] ? 'true' : 'false';
        $result['failures'] = count($result['failures']);

        unset($result['retries']);

        // report success
        $this->output->success('Copy complete, see results below');
        $this->output->table(array_keys($result), [$result]);
    }
}
