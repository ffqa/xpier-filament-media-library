@php
    $selectedMedia = $getSelectedMedia();
    $isDisabled = $isDisabled();
    $isMultiple = $isMultiple();
    $pickerAction = $isDisabled ? null : $getAction($getPickerActionName());
    $stateKey = is_array($getState()) ? implode(',', $getState()) : ($getState() ?? 'empty');
@endphp

<div
    wire:key="media-picker-{{ $getName() }}-{{ $stateKey }}"
    x-data="{
        mediaId: @js($isMultiple ? null : $getState()),
        mediaUrl: @js($isMultiple ? null : ($selectedMedia['thumb'] ?? $selectedMedia['url'] ?? null)),
        mediaName: @js($isMultiple ? null : ($selectedMedia['name'] ?? null)),
        mediaItems: @js($isMultiple ? ($selectedMedia ?? []) : []),
        isDisabled: @js($isDisabled),
        removeMedia(id) {
            if (this.isDisabled) {
                return
            }
            if (this.mediaItems.length) {
                this.mediaItems = this.mediaItems.filter(item => item.id != id)
                $wire.set(@js($getStatePath()), this.mediaItems.map(item => item.id))
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

    @if ($isMultiple)
        <div x-show="mediaItems.length" x-cloak class="rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-800">
            <div class="grid grid-cols-4 gap-2">
                <template x-for="item in mediaItems" :key="item.id">
                    <div class="relative rounded-md border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-900">
                        <img :src="item.thumb" :alt="item.name" class="h-20 w-full rounded object-contain" />
                        <div class="mt-1 truncate text-center text-[10px] leading-tight text-gray-500" x-text="item.name"></div>
                        <button
                            type="button"
                            x-show="!isDisabled"
                            @click="removeMedia(item.id)"
                            class="absolute -right-1.5 -top-1.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-danger-600 text-xs text-white shadow hover:bg-danger-700"
                            aria-label="remove"
                        >×</button>
                    </div>
                </template>
            </div>
        </div>
    @else
        <div x-show="mediaUrl" x-cloak class="relative rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-800">
            <img :src="mediaUrl" :alt="mediaName" class="mx-auto h-32 max-w-full rounded-md object-contain" />
            <div class="mt-1.5 flex items-center justify-between">
                <span class="truncate text-xs text-gray-500" x-text="mediaName"></span>
                <button
                    type="button"
                    x-show="!isDisabled"
                    @click="removeMedia()"
                    class="inline-flex items-center gap-1 text-xs font-medium text-danger-600 hover:text-danger-700"
                >
                    {{ __('filament-media-library::media-library.picker.remove') }}
                </button>
            </div>
        </div>
    @endif

    @unless ($isDisabled)
        @if ($pickerAction)
            <div class="flex gap-2">
                {{ $pickerAction }}
            </div>
        @endif
        <p class="text-xs text-gray-400">{{ __('filament-media-library::media-library.picker.hint') }}</p>
    @else
        <div
            x-show="{{ $isMultiple ? '!mediaItems.length' : '!mediaUrl' }}"
            x-cloak
            class="text-xs text-gray-400"
        >{{ __('filament-media-library::media-library.picker.no_cover') }}</div>
    @endunless
</div>
