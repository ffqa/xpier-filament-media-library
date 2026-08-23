@php
    $selectedMedia = $getSelectedMedia();
    $isDisabled = $isDisabled();
    $pickerAction = $isDisabled ? null : $getAction($getPickerActionName());
@endphp

<div
    wire:key="media-picker-{{ $getName() }}-{{ $getState() ?? 'empty' }}"
    x-data="{
        mediaId: @js($getState()),
        mediaUrl: @js($selectedMedia['thumb'] ?? $selectedMedia['url'] ?? null),
        mediaName: @js($selectedMedia['name'] ?? null),
        isDisabled: @js($isDisabled),
        removeMedia() {
            if (this.isDisabled) {
                return
            }
            this.mediaId = null
            this.mediaUrl = null
            this.mediaName = null
            $wire.set(@js($getStatePath()), null)
        }
    }"
    class="space-y-3"
>
    @if (filled($getLabel()))
        <div class="text-sm font-medium text-gray-950 dark:text-white">
            {{ $getLabel() }}
        </div>
    @endif

    <div x-show="mediaUrl" x-cloak class="relative rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-800">
        <img :src="mediaUrl" :alt="mediaName" class="mx-auto h-32 max-w-full rounded-md object-contain" />
        <div class="mt-1.5 flex items-center justify-between">
            <span class="text-xs text-gray-500 truncate" x-text="mediaName"></span>
            <button
                type="button"
                x-show="!isDisabled"
                @click="removeMedia()"
                class="inline-flex items-center gap-1 text-xs text-danger-600 hover:text-danger-700 font-medium"
            >
                {{ __('filament-media-library::media-library.picker.remove') }}
            </button>
        </div>
    </div>

    @unless ($isDisabled)
        @if ($pickerAction)
            <div class="flex gap-2">
                {{ $pickerAction }}
            </div>
        @endif
        <p class="text-xs text-gray-400">{{ __('filament-media-library::media-library.picker.hint') }}</p>
    @else
        <div x-show="!mediaUrl" x-cloak class="text-xs text-gray-400">{{ __('filament-media-library::media-library.picker.no_cover') }}</div>
    @endunless
</div>
