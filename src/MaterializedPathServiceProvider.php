<?php namespace Langaner\MaterializedPath;

use Illuminate\Support\ServiceProvider;

class MaterializedPathServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../config/materialized_path.php';
        $this->publishes([$configPath => config_path('materialized_path.php')]);
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $configPath = __DIR__ . '/../config/materialized_path.php';
        $this->mergeConfigFrom($configPath, 'materialized_path');
    }
}