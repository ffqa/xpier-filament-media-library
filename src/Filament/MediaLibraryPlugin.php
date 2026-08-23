<?php

namespace Xpier\FilamentMediaLibrary\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaFolderResource;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource;

class MediaLibraryPlugin implements Plugin
{
    protected ?string $navigationGroup = null;

    public static function make(): static
    {
        return app(self::class);
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup
            ?? (string) (config('filament-media-library.navigation_group') ?: __('filament-media-library::media-library.navigation.group'));
    }

    public function getId(): string
    {
        return 'xpier-filament-media-library';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            MediaLibraryResource::class,
            MediaFolderResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
