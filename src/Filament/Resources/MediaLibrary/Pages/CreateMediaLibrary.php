<?php

namespace Xpier\FilamentMediaLibrary\Filament\Resources\MediaLibrary\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource;
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;
use Xpier\FilamentMediaLibrary\Services\AdminMediaService;

class CreateMediaLibrary extends CreateRecord
{
    protected static string $resource = MediaLibraryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // upload is intentionally not dehydrated into the model.
        $state = $this->form->getRawState();
        $upload = $state['upload'] ?? null;
        $file = is_array($upload) ? ($upload[0] ?? null) : $upload;

        if (! $file instanceof TemporaryUploadedFile && ! $file instanceof UploadedFile) {
            throw new \InvalidArgumentException('Upload file is required.');
        }

        $media = app(AdminMediaService::class)->storeUpload(
            file: $file,
            folder: (string) ($state['folder'] ?? 'general'),
            type: (string) ($state['type'] ?? MediaLibrary::TYPE_IMAGE),
            userId: auth()->id(),
        );

        if (filled($state['alt_text'] ?? null)) {
            $media->update([
                'alt_text' => $state['alt_text'],
            ]);
        }

        return $media->fresh();
    }
}
