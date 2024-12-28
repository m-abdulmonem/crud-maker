<?php

namespace Mabdulmonem\CrudMaker;

use Illuminate\Support\ServiceProvider;
use Mabdulmonem\CrudMaker\Commands\CrudMaker;

class CrudMakerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {


        // Merge configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/crud-maker.php', 'crud-maker');
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CrudMaker::class,
            ]);
        }
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/crud-maker.php' => config_path('crud-maker.php'),
        ], 'config');

        $this->publishes( [
            __DIR__ . '/../Stubs' => base_path('stubs/vendor/mabdulmonem/crud-maker'), // Destination path
        ], 'stubs');
    }
}
