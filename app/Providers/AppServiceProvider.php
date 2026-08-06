<?php

namespace App\Providers;

use App\Database\CustomMigrationRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->extend('migration.repository', function ($repository, $app) {
            $resolver = $app->make('db');
            $table = $app->make('config')->get('database.migrations.table');

            return new CustomMigrationRepository($resolver, $table);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}