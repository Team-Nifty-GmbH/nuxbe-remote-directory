<?php

namespace NuxbeRemoteDirectory;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use NuxbeRemoteDirectory\Console\ServeLdapDirectoryCommand;

class NuxbeRemoteDirectoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')->group(__DIR__ . '/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([ServeLdapDirectoryCommand::class]);
        }

        $this->publishes([
            __DIR__ . '/../config/remote-directory.php' => config_path('remote-directory.php'),
        ], 'nuxbe-remote-directory-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/remote-directory.php', 'remote-directory');
    }
}
