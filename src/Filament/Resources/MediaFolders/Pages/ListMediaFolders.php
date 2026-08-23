<?php

namespace Xpier\FilamentMediaLibrary\Filament\Resources\MediaFolders\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaFolderResource;

class ListMediaFolders extends ListRecords
{
    protected static string $resource = MediaFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
