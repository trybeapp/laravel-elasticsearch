<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MappingMakeCommand extends Command
{
    protected $description = 'Create new mapping.';

    protected $signature = 'make:mapping
        {name : Name of the mapping.}
        {--T|template= : Optional name of existing mapping as template.}
        {--U|update : Update existing index}';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    public function handle()
    {
        try {
            $this->resolveMappingsDirectory();

            $mapping = $this->getPath();
            $template = $this->files->get($this->getTemplate());

            $this->files->put($mapping, $template);
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());

            return;
        }

        $this->info("Mapping {$mapping} created successfully.");
    }

    protected function getPath():string
    {
        return base_path("database/mappings/{$this->getStub()}.json");
    }

    protected function getStub():string
    {
        $name = $this->argument('name');
        $timestamp = Carbon::now()->format('Y_m_d_His');

        if ($this->option('update')) {
            $timestamp .= '_update';
        }

        return "{$timestamp}_{$name}";
    }

    protected function getTemplate():string
    {
        if ($template = $this->option('template')) {
            if (!Str::contains($template, '.json')) {
                $template .= '.json';
            }

            return base_path("database/mappings/{$template}");
        }

        if ($this->option('update')) {
            return base_path(
                'vendor/designmynight/laravel-elasticsearch/src/Console/Mappings/stubs/update-mapping.stub'
            );
        }

        return base_path('vendor/designmynight/laravel-elasticsearch/src/Console/Mappings/stubs/mapping.stub');
    }

    protected function resolveMappingsDirectory():void
    {
        $path = base_path('database/mappings');

        if ($this->files->exists($path)) {
            return;
        }

        $this->files->makeDirectory($path, 0755, true);
    }
}
