<?php

namespace DesignMyNight\Elasticsearch;

use DesignMyNight\Elasticsearch\Console\Mappings;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;

/**
 * Class ElasticsearchServiceProvider
 *
 * @package DesignMyNight\Elasticsearch
 */
class ElasticsearchServiceProvider extends ServiceProvider
{
    /** @var array $commands */
    private $commands = [
        Mappings\AliasMakeCommand::class,
        Mappings\IndexCopyCommand::class,
        Mappings\IndexListCommand::class,
        Mappings\IndexRemoveCommand::class,
        Mappings\IndexRollbackCommand::class,
        Mappings\IndexSwapCommand::class,
        Mappings\IndexSwapCommand::class,
        Mappings\MappingMakeCommand::class,
        Mappings\MappingMigrateCommand::class,
    ];

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }

        $this->publishes([
            __DIR__ . '/Config/laravel-elasticsearch.php' => config_path('laravel-elasticsearch.php')
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Add database driver.
        $this->app->resolving('db', function (DatabaseManager $db) {
            $db->extend('elasticsearch', function ($config, $name) {
                $config['name'] = $name;
                return new Connection($config);
            });
        });

        $this->mergeConfigFrom(__DIR__ . '/Config/database.php', 'database');
    }
}
