<?php

namespace Xpier\FilamentMediaLibrary\Filament\Resources\MediaLibrary\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaFolderResource;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource;

class ListMediaLibrary extends ListRecords
{
    protected static string $resource = MediaLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageFolders')
                ->label(__('filament-media-library::media-library.media_library.manage_folders'))
                ->icon('heroicon-o-folder')
                ->color('gray')
                ->url(MediaFolderResource::getUrl('index')),
            CreateAction::make()
                ->label(__('filament-media-library::media-library.media_library.upload_to_platform')),
        ];
    }
}
