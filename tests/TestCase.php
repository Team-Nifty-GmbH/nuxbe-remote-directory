<?php

namespace NuxbeRemoteDirectory\Tests;

use Barryvdh\DomPDF\ServiceProvider as DomPdfServiceProvider;
use FluxErp\FluxServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Laravel\Scout\ScoutServiceProvider;
use Livewire\LivewireServiceProvider;
use NotificationChannels\WebPush\WebPushServiceProvider;
use NuxbeRemoteDirectory\NuxbeRemoteDirectoryServiceProvider;
use Orchestra\Testbench\Concerns\CreatesApplication;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\QueryBuilder\QueryBuilderServiceProvider;
use Spatie\Tags\TagsServiceProvider;
use Spatie\Translatable\TranslatableServiceProvider;
use TallStackUi\Facades\TallStackUi;
use TallStackUi\TallStackUiServiceProvider;
use TeamNiftyGmbH\DataTable\DataTableServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected $loadEnvironmentVariables = true;

    public function getPackageProviders($app): array
    {
        return [
            LaravelSettingsServiceProvider::class,
            TranslatableServiceProvider::class,
            LivewireServiceProvider::class,
            TallStackUiServiceProvider::class,
            PermissionServiceProvider::class,
            TagsServiceProvider::class,
            ScoutServiceProvider::class,
            MediaLibraryServiceProvider::class,
            QueryBuilderServiceProvider::class,
            DataTableServiceProvider::class,
            ActivitylogServiceProvider::class,
            FluxServiceProvider::class,
            WebPushServiceProvider::class,
            DomPdfServiceProvider::class,
            NuxbeRemoteDirectoryServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        File::ensureDirectoryExists(database_path('settings'));

        // Flux core carries MySQL only migrations, so the package tests run
        // against the same MySQL the flux-core test suite uses.
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', array_merge(
            $app['config']->get('database.connections.mysql', []),
            [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3309'),
                'database' => env('DB_DATABASE', 'remote_directory_testing'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
            ]
        ));
        $app['config']->set('flux.install_done', true);
        $app['config']->set('auth.defaults.guard', 'sanctum');
        $app['config']->set('cache.default', 'array');

        $app['config']->set('remote-directory.token', 'test-token');
        $app['config']->set('remote-directory.limit', 50);
        $app['config']->set('remote-directory.max_limit', 200);
    }

    protected function getPackageAliases($app): array
    {
        return ['TallStackUi' => TallStackUi::class];
    }
}
