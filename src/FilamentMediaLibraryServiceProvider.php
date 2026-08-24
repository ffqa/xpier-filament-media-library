<?php

namespace Xpier\FilamentMediaLibrary;

use Illuminate\Support\ServiceProvider;
use Xpier\FilamentMediaLibrary\Support\MediaUrlResolver;
use Xpier\FilamentMediaLibrary\Support\Providers\LocalThumbnailProvider;
use Xpier\FilamentMediaLibrary\Support\Providers\MultiDiskUrlResolver;
use Xpier\FilamentMediaLibrary\Support\ThumbnailProvider;

class FilamentMediaLibraryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-media-library');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'filament-media-library');

        $this->publishes([
            __DIR__.'/../config/media-library.php' => config_path('media-library.php'),
        ], 'filament-media-library-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/filament-media-library'),
        ], 'filament-media-library-views');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/filament-media-library'),
        ], 'filament-media-library-lang');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'filament-media-library-migrations');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/media-library.php', 'filament-media-library');

        $this->app->singleton(ThumbnailProvider::class, function ($app) {
            $provider = (string) config('filament-media-library.thumbnail_provider', LocalThumbnailProvider::class);

            return $app->make($provider);
        });

        $this->app->singleton(MediaUrlResolver::class, function ($app) {
            $resolver = (string) config('filament-media-library.url_resolver');

            if ($resolver !== '') {
                return $app->make($resolver);
            }

            return new MultiDiskUrlResolver(
                publicUrls: (array) config('filament-media-library.public_urls', []),
                defaultPublicUrl: (string) config('filament-media-library.public_url') ?: null,
            );
        });
    }
}
