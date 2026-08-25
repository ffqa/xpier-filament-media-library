<?php

namespace Xpier\FilamentMediaLibrary\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        ?string $disk = null,
        ?string $visibility = null,
    ): MediaLibrary {
        $extension = $this->detectExtension($file);

        $this->assertValidUpload($file, $type, $extension);

        $folder = $this->normalizeFolder($folder);
        $disk = $disk ?: MediaLibrary::defaultDisk();
        $visibility = $visibility ?: (string) config('filament-media-library.visibility', 'public');
        $directory = $this->buildDirectory($folder, $type);

        // The extension comes from the MIME-detected value (never from the
        // client-provided original filename), so a file named "evil.php" with
        // image content cannot land on the disk as PHP.
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

    /**
     * Server-side upload validation. Filament's acceptedFileTypes() is
     * client-side only (FilePond), so this is the real security boundary.
     * Throws a ValidationException so Livewire renders a form error instead
     * of a 500 page.
     */
    protected function assertValidUpload(UploadedFile|TemporaryUploadedFile $file, string $type, string $extension): void
    {
        $maxBytes = (int) round((float) config('filament-media-library.max_size', 20) * 1024 * 1024);

        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'upload' => __('filament-media-library::media-library.upload.too_large', ['size' => (int) config('filament-media-library.max_size', 20)]),
            ]);
        }

        if (! in_array($extension, $this->allowedExtensions($type), true)) {
            throw ValidationException::withMessages([
                'upload' => __('filament-media-library::media-library.upload.type_not_allowed', ['type' => $extension]),
            ]);
        }
    }

    /**
     * Single source of truth for the stored extension: MIME-detected first,
     * client-provided extension as a last resort (both whitelisted above).
     */
    protected function detectExtension(UploadedFile|TemporaryUploadedFile $file): string
    {
        return strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin'));
    }

    /**
     * @return list<string>
     */
    protected function allowedExtensions(string $type): array
    {
        return match ($type) {
            MediaLibrary::TYPE_IMAGE => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'avif'],
            MediaLibrary::TYPE_VIDEO => ['mp4', 'webm', 'mov', 'avi', 'mkv'],
            default => ['pdf', 'zip', 'rar', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'],
        };
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
