<?php

namespace Xpier\FilamentMediaLibrary\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Xpier\FilamentMediaLibrary\Models\MediaFolder;
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;

class AdminMediaService
{
    public function storeUpload(
        UploadedFile|TemporaryUploadedFile $file,
        string $folder = 'general',
        string $type = MediaLibrary::TYPE_IMAGE,
        ?int $userId = null,
    ): MediaLibrary {
        $folder = $this->normalizeFolder($folder);
        $disk = MediaLibrary::defaultDisk();
        $visibility = (string) config('filament-media-library.visibility', 'public');
        $directory = $this->buildDirectory($folder, $type);
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $filename = Str::uuid()->toString().'.'.ltrim($extension, '.');
        $path = $file->storeAs($directory, $filename, [
            'disk' => $disk,
            'visibility' => $visibility,
        ]);

        if ($path === false) {
            throw new \RuntimeException('Failed to store uploaded file on disk ['.$disk.'].');
        }

        return MediaLibrary::query()->create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'extension' => ltrim($extension, '.'),
            'mime_type' => $file->getMimeType(),
            'size' => (int) ($file->getSize() ?: 0),
            'type' => $type,
            'source' => MediaLibrary::SOURCE_ADMIN,
            'folder' => $folder,
            'user_id' => $userId,
        ]);
    }

    public function deleteFile(MediaLibrary $media): void
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
    }

    protected function normalizeFolder(string $folder): string
    {
        $key = Str::of($folder)->trim()->lower()->replaceMatches('#/+#', '/')->trim('/')->value() ?: (string) config('filament-media-library.default_module', 'general');
        $resolved = MediaFolder::resolveStoragePath($key);
        if ($resolved !== null) {
            return $resolved;
        }

        $segment = Str::of($key)->replaceMatches('/[^a-z0-9\-\/]+/', '-')->trim('-/')->value() ?: (string) config('filament-media-library.default_module', 'general');
        $allowed = array_keys(MediaLibrary::adminFolderOptions());

        if (in_array($segment, $allowed, true)) {
            return $segment;
        }

        $default = (string) config('filament-media-library.default_module', 'general');

        return in_array($default, $allowed, true) ? $default : (array_values($allowed)[0] ?? $default);
    }

    protected function buildDirectory(string $folder, string $type): string
    {
        $root = trim((string) config('filament-media-library.directory', 'media'), '/');
        $folder = Str::of($folder)->trim()->replaceMatches('#/+#', '/')->trim('/')->value() ?: (string) config('filament-media-library.default_module', 'general');
        $type = Str::of($type)->trim()->lower()->replaceMatches('/[^a-z0-9\-]+/', '-')->trim('-')->value() ?: MediaLibrary::TYPE_FILE;

        return "{$root}/library/{$folder}/{$type}/".now()->format('Y/m');
    }
}
