<?php

namespace Xpier\FilamentMediaLibrary\Filament\Resources\MediaLibrary\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource;

class ViewMediaLibrary extends ViewRecord
{
    protected static string $resource = MediaLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('编辑')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url(fn () => static::getResource()::getUrl('edit', ['record' => $this->getRecord()])),
            DeleteAction::make(),
        ];
    }
}
