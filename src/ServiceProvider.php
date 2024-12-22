<?php

namespace Mabdulmonem\CrudMaker;

use Illuminate\Support\ServiceProvider;

class CrudMakerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        // Register commands
        // if (!$this->app->runningInConsole()) {
            $this->commands([
                Commands\MakeCrudCommand::class,
            ]);
        // }

        // Merge configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/crud-maker.php', 'crud-maker');
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/crud-maker.php' => config_path('crud-maker.php'),
        ], 'config');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'crud-maker');
    }
}
