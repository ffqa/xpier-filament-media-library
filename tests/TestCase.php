<?php

namespace Xpier\FilamentMediaLibrary\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Xpier\FilamentMediaLibrary\FilamentMediaLibraryServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected $enablesPackageDiscoveries = true;

    protected function getPackageProviders($app): array
    {
        return [
            \BladeUI\Icons\BladeIconsServiceProvider::class,
            \BladeUI\Heroicons\BladeHeroiconsServiceProvider::class,
            \Livewire\LivewireServiceProvider::class,
            \Filament\Support\SupportServiceProvider::class,
            \Filament\Schemas\SchemasServiceProvider::class,
            \Filament\Forms\FormsServiceProvider::class,
            \Filament\Actions\ActionsServiceProvider::class,
            \Filament\Notifications\NotificationsServiceProvider::class,
            \Filament\FilamentServiceProvider::class,
            FilamentMediaLibraryServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.locale', 'zh_CN');
        $app['config']->set('app.fallback_locale', 'en');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('filesystems.default', 'public');
        $app['config']->set('filament-media-library.disk', 'public');
        $app['config']->set('filament-media-library.directory', 'media');
        $app['config']->set('filament-media-library.visibility', 'public');
        $app['config']->set('filament-media-library.default_module', 'general');

        // Livewire 4's SupportValidation reads the shared error bag during render.
        $app['view']->share('errors', new \Illuminate\Support\ViewErrorBag);
    }
}
