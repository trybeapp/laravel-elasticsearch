<?php

namespace DesignMyNight\Elasticsearch;

use DesignMyNight\Elasticsearch\Console\Mappings\MappingMakeCommand;
use DesignMyNight\Elasticsearch\Console\Mappings\MappingMigrateCommand;
use DesignMyNight\Elasticsearch\Console\Mappings\MappingRemoveCommand;
use DesignMyNight\Elasticsearch\Console\Mappings\MappingRollbackCommand;
use DesignMyNight\Elasticsearch\Console\Mappings\MappingSwapAliasCommand;
use DesignMyNight\Elasticsearch\Console\Mappings\MappingViewAllCommand;
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
        MappingMakeCommand::class,
        MappingMigrateCommand::class,
        MappingSwapAliasCommand::class,
        MappingRemoveCommand::class,
        MappingRollbackCommand::class,
        MappingViewAllCommand::class
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

        $this->loadMigrationsFrom(__DIR__ . '/Migrations');
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
            $db->extend('elasticsearch', function ($config) {
                return new Connection($config);
            });
        });

        $this->mergeConfigFrom(__DIR__ . '/Config/database.php', 'database');
    }
}
