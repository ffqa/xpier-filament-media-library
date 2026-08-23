@php
    $statePath = $getStatePath();
    $selected = $getState();
    $browser = $browser ?? [
        'current' => \Xpier\FilamentMediaLibrary\Components\MediaPicker::FOLDER_ROOT,
        'breadcrumbs' => [],
        'can_upload' => true,
        'upload_folder' => 'general',
        'entries' => [],
        'is_search' => false,
    ];
    $folderStatePath = $folderStatePath ?? 'picker_folder';
    $searchStatePath = $searchStatePath ?? 'search_query';
    $foldersUrl = $foldersUrl ?? '#';
    $entries = $browser['entries'] ?? [];
    $mediaCount = collect($entries)->where('kind', 'media')->count();
    $isSearch = (bool) ($browser['is_search'] ?? false);
@endphp

<style>
    /* ===== Modal-level overrides (merged from theme.css) ===== */
    .fi-media-picker-modal .fi-media-picker-upload-hidden {
        margin-bottom: 0;
    }
    .fi-media-picker-modal .fi-media-picker-upload-hidden .fi-fo-file-upload-wrp {
        margin-bottom: 0;
    }
    .fi-media-picker-modal .fi-media-picker-file-upload-slim .filepond--panel-root {
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
    }
    .fi-media-picker-modal .fi-media-picker-file-upload-slim .filepond--item {
        margin-top: 0;
        margin-bottom: 0;
    }
    .fi-media-picker-modal .fi-fo-media-picker-grid {
        margin-top: 0.5rem;
    }

    /* ===== Grid browser ===== */
    .fi-fo-media-picker-grid { width: 100%; }
    .fi-fo-media-picker-grid .fi-mpg-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.35rem;
        margin-top: 0;
    }
    .fi-fo-media-picker-grid .fi-mpg-crumbs {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.25rem;
        flex: 1;
        min-width: 12rem;
    }
    .fi-fo-media-picker-grid .fi-mpg-crumb {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border: 1px solid #e5e7eb;
        background: #fff;
        border-radius: 0.375rem;
        padding: 0.25rem 0.55rem;
        font-size: 0.75rem;
        line-height: 1.2;
        color: #374151;
        cursor: pointer;
    }
    .fi-fo-media-picker-grid .fi-mpg-crumb.is-active {
        border-color: #f59e0b;
        background: #fffbeb;
        color: #92400e;
        font-weight: 600;
        cursor: default;
    }
    .fi-fo-media-picker-grid .fi-mpg-crumb-sep {
        color: #9ca3af;
        font-size: 0.75rem;
    }
    .fi-fo-media-picker-grid .fi-mpg-search {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        border: 1px solid #d1d5db;
        background: #fff;
        border-radius: 0.375rem;
        padding: 0.25rem 0.55rem;
        font-size: 0.75rem;
        min-width: 10rem;
    }
    .fi-fo-media-picker-grid .fi-mpg-search input {
        border: none;
        outline: none;
        background: transparent;
        font-size: 0.75rem;
        width: 100%;
        min-width: 0;
    }
    .fi-fo-media-picker-grid .fi-mpg-search svg {
        flex-shrink: 0;
        color: #9ca3af;
    }
    .fi-fo-media-picker-grid .fi-mpg-upload-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border: 1px solid #d1d5db;
        background: #fff;
        border-radius: 0.375rem;
        padding: 0.3rem 0.65rem;
        font-size: 0.75rem;
        color: #374151;
        cursor: pointer;
    }
    .fi-fo-media-picker-grid .fi-mpg-upload-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }
    .fi-fo-media-picker-grid .fi-mpg-meta {
        margin: 0 0 0.4rem;
        font-size: 0.75rem;
        color: #6b7280;
    }
    .fi-fo-media-picker-grid .fi-mpg-scroll {
        height: 28.75rem;
        overflow-y: auto;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        background: #f9fafb;
        padding: 0.4rem;
        box-sizing: border-box;
    }
    .fi-fo-media-picker-grid .fi-mpg-tiles {
        display: grid !important;
        grid-template-columns: repeat(10, minmax(0, 1fr)) !important;
        gap: 0.35rem;
        width: 100%;
    }
    .fi-fo-media-picker-grid .fi-mpg-tile {
        position: relative;
        display: flex;
        flex-direction: column;
        aspect-ratio: 1 / 1;
        min-width: 0;
        overflow: hidden;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        background: #fff;
        box-sizing: border-box;
    }
    .fi-fo-media-picker-grid .fi-mpg-tile.is-selected {
        border: 2px solid #f59e0b;
    }
    .fi-fo-media-picker-grid .fi-mpg-tile.is-folder,
    .fi-fo-media-picker-grid .fi-mpg-tile.is-up {
        cursor: pointer;
        background: #fff7ed;
        border-color: #fdba74;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        padding: 0.35rem;
        text-align: center;
    }
    .fi-fo-media-picker-grid .fi-mpg-tile.is-folder:hover,
    .fi-fo-media-picker-grid .fi-mpg-tile.is-up:hover {
        background: #ffedd5;
    }
    .fi-fo-media-picker-grid .fi-mpg-folder-icon {
        font-size: 1.4rem;
        line-height: 1;
    }
    .fi-fo-media-picker-grid .fi-mpg-folder-label {
        font-size: 0.68rem;
        line-height: 1.2;
        color: #9a3412;
        word-break: break-all;
        max-height: 2.4em;
        overflow: hidden;
    }
    .fi-fo-media-picker-grid .fi-mpg-folder-count {
        font-size: 0.62rem;
        color: #c2410c;
    }
    .fi-fo-media-picker-grid .fi-mpg-tile label.fi-mpg-media-label {
        flex: 1;
        min-height: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 3px;
        margin: 0;
        width: 100%;
        height: 100%;
        box-sizing: border-box;
    }
    .fi-fo-media-picker-grid .fi-mpg-tile input[type="radio"] {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        border: 0 !important;
    }
    .fi-fo-media-picker-grid .fi-mpg-tile img {
        max-width: 100% !important;
        max-height: 100% !important;
        width: auto !important;
        height: auto !important;
        object-fit: contain !important;
        display: block !important;
    }
    .fi-fo-media-picker-grid .fi-mpg-check {
        position: absolute;
        top: 3px;
        left: 3px;
        width: 1rem;
        height: 1rem;
        border-radius: 9999px;
        background: #f59e0b;
        color: #fff;
        font-size: 0.65rem;
        line-height: 1rem;
        text-align: center;
        pointer-events: none;
        z-index: 2;
    }
    .fi-fo-media-picker-grid .fi-mpg-empty {
        margin: 0;
        padding: 2rem 1rem;
        text-align: center;
        font-size: 0.875rem;
        color: #6b7280;
        border: 1px dashed #d1d5db;
        border-radius: 0.5rem;
    }
    .fi-fo-media-picker-grid .fi-mpg-tip {
        position: absolute;
        left: 50%;
        bottom: 0.3rem;
        transform: translateX(-50%);
        z-index: 5;
        display: none;
        width: max-content;
        max-width: 12rem;
        padding: 0.35rem 0.45rem;
        border-radius: 0.3rem;
        background: rgba(17, 24, 39, 0.92);
        color: #fff;
        font-size: 0.65rem;
        line-height: 1.3;
        text-align: left;
        pointer-events: none;
        white-space: normal;
        word-break: break-word;
    }
    .fi-fo-media-picker-grid .fi-mpg-tile:hover .fi-mpg-tip {
        display: block;
    }
    @media (max-width: 1024px) {
        .fi-fo-media-picker-grid .fi-mpg-tiles {
            grid-template-columns: repeat(8, minmax(0, 1fr)) !important;
        }
    }
    @media (max-width: 640px) {
        .fi-fo-media-picker-grid .fi-mpg-tiles {
            grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
        }
    }
</style>

<div
    class="fi-fo-media-picker-grid"
    x-data="{ previewUrl: null, previewName: '' }"
>
    <div class="fi-mpg-toolbar">
        <div class="fi-mpg-crumbs">
            @foreach ($browser['breadcrumbs'] as $index => $crumb)
                @if ($index > 0)
                    <span class="fi-mpg-crumb-sep">/</span>
                @endif
                @if (($crumb['key'] ?? '') === ($browser['current'] ?? ''))
                    <span class="fi-mpg-crumb is-active">{{ $crumb['label'] }}</span>
                @else
                    <button
                        type="button"
                        class="fi-mpg-crumb"
                        x-on:click="$wire.set(@js($folderStatePath), @js($crumb['key']))"
                    >{{ $crumb['label'] }}</button>
                @endif
            @endforeach
        </div>

        <div class="fi-mpg-search">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input
                type="text"
                placeholder="{{ __('filament-media-library::media-library.picker.search_placeholder') }}"
                x-on:input.debounce.300ms="$wire.set(@js($searchStatePath), $event.target.value)"
            />
        </div>

        <button
            type="button"
            class="fi-mpg-upload-btn"
            @disabled(! ($browser['can_upload'] ?? false))
            title="{{ ($browser['can_upload'] ?? false) ? __('filament-media-library::media-library.picker.upload_to_current') : __('filament-media-library::media-library.picker.upload_disabled_search') }}"
            x-on:click="
                const modal = $el.closest('.fi-modal-window') ?? $el.closest('[role=dialog]') ?? document;
                const input = modal.querySelector('.fi-media-picker-file-upload input[type=file]');
                if (input) input.click();
            "
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            {{ __('filament-media-library::media-library.picker.upload_to_current') }}
        </button>
        <a href="{{ $foldersUrl }}" target="_blank" class="fi-mpg-upload-btn" style="text-decoration:none;">{{ __('filament-media-library::media-library.picker.manage_folders') }}</a>
    </div>

    <p class="fi-mpg-meta">
        @if ($isSearch)
            {{ __('filament-media-library::media-library.picker.search_result_count', ['count' => $mediaCount]) }}
        @else
            {{ __('filament-media-library::media-library.picker.media_count', ['count' => $mediaCount]) }}
        @endif
    </p>

    @if ($entries === [])
        <p class="fi-mpg-empty">{{ __('filament-media-library::media-library.picker.empty') }}</p>
    @else
        <div class="fi-mpg-scroll">
            <div class="fi-mpg-tiles" style="display:grid !important;grid-template-columns:repeat(10, minmax(0, 1fr)) !important;gap:0.35rem;width:100%;">
                @foreach ($entries as $entry)
                    @php $kind = $entry['kind'] ?? 'media'; @endphp

                    @if ($kind === 'up' || $kind === 'folder')
                        <button
                            type="button"
                            class="fi-mpg-tile {{ $kind === 'up' ? 'is-up' : 'is-folder' }}"
                            title="{{ $entry['label'] ?? '' }}"
                            x-on:click="$wire.set(@js($folderStatePath), @js($entry['key'] ?? ''))"
                        >
                            <span class="fi-mpg-folder-icon">{{ $kind === 'up' ? '⬆' : '📁' }}</span>
                            <span class="fi-mpg-folder-label">{{ $entry['label'] ?? '' }}</span>
                            @if ($kind === 'folder')
                                <span class="fi-mpg-folder-count">{{ __('filament-media-library::media-library.picker.items_count', ['count' => (int) ($entry['count'] ?? 0)]) }}</span>
                            @endif
                        </button>
                    @else
                        @php
                            $id = (string) ($entry['id'] ?? '');
                            $isSelected = filled($selected) && (string) $selected === $id;
                            $name = (string) ($entry['name'] ?? '');
                            $note = trim((string) ($entry['note'] ?? ''));
                            $tip = $note !== '' ? ($name."\n".$note) : $name;
                        @endphp
                        <div class="fi-mpg-tile{{ $isSelected ? ' is-selected' : '' }}">
                            <label class="fi-mpg-media-label" title="{{ $tip }}">
                                <input
                                    type="radio"
                                    value="{{ $id }}"
                                    @checked($isSelected)
                                    {{ $applyStateBindingModifiers('wire:model') }}="{{ $statePath }}"
                                />
                                <img
                                    src="{{ $entry['thumb'] }}"
                                    alt="{{ $name }}"
                                    loading="lazy"
                                />
                            </label>
                            @if ($isSelected)
                                <span class="fi-mpg-check" aria-hidden="true">✓</span>
                            @endif
                            <div class="fi-mpg-tip">
                                <div>{{ $name }}</div>
                                @if ($note !== '')
                                    <div style="opacity:0.85;margin-top:0.15rem;">{{ $note }}</div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
