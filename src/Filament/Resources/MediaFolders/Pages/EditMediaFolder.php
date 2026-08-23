<?php

namespace Xpier\FilamentMediaLibrary\Filament\Resources\MediaFolders\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaFolderResource;

class EditMediaFolder extends EditRecord
{
    protected static string $resource = MediaFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
