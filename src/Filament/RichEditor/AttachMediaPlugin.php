<?php

namespace Xpier\FilamentMediaLibrary\Filament\RichEditor;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Icons\Heroicon;
use Tiptap\Core\Extension;
use Xpier\FilamentMediaLibrary\Components\MediaPicker;
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;

/**
 * RichEditor integration built on Filament's native plugin mechanism
 * (RichContentPlugin). Adds an "insert media" toolbar button that opens a
 * modal with a MediaPicker and inserts the picked image at the cursor.
 *
 * Usage:
 *   RichEditor::make('content')->plugins([AttachMediaPlugin::make()])
 */
class AttachMediaPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('attachMedia')
                ->label(__('filament-media-library::media-library.rich_editor.attach_media'))
                ->action()
                ->activeKey('image')
                ->icon(Heroicon::OutlinedPhoto),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            Action::make('attachMedia')
                ->label(__('filament-media-library::media-library.rich_editor.attach_media'))
                ->modalHeading(__('filament-media-library::media-library.rich_editor.attach_media_heading'))
                ->modalSubmitActionLabel(__('filament-media-library::media-library.rich_editor.insert'))
                ->modalWidth('7xl')
                ->schema([
                    MediaPicker::make('media_id')
                        ->label(__('filament-media-library::media-library.rich_editor.select_media'))
                        ->mediaType(MediaLibrary::TYPE_IMAGE),
                ])
                ->action(function (array $arguments, array $data, RichEditor $component): void {
                    $mediaId = $data['media_id'] ?? null;

                    if (blank($mediaId) || ! is_numeric($mediaId)) {
                        return;
                    }

                    $media = MediaLibrary::query()->find((int) $mediaId);

                    if (! $media instanceof MediaLibrary || blank($media->url)) {
                        return;
                    }

                    $component->runCommands(
                        [
                            EditorCommand::make('insertContent', arguments: [[
                                'type' => 'image',
                                'attrs' => [
                                    'alt' => (string) ($media->alt_text ?? ''),
                                    'src' => (string) $media->url,
                                ],
                            ]]),
                        ],
                        editorSelection: $arguments['editorSelection'] ?? null,
                    );
                }),
        ];
    }
}
