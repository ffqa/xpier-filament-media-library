<?php

namespace Xpier\FilamentMediaLibrary\Filament\Resources;

use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaLibrary\Pages\CreateMediaLibrary;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaLibrary\Pages\EditMediaLibrary;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaLibrary\Pages\ListMediaLibrary;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaLibrary\Pages\ViewMediaLibrary;
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;

class MediaLibraryResource extends Resource
{
    protected static ?string $model = MediaLibrary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'media-library';

    public static function getNavigationLabel(): string
    {
        return __('filament-media-library::media-library.resources.media_library.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament-media-library::media-library.resources.media_library.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-media-library::media-library.resources.media_library.plural_label');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return (string) (config('filament-media-library.navigation_group') ?: __('filament-media-library::media-library.navigation.group'));
    }

    public static function getNavigationBadge(): ?string
    {
        if (! (bool) config('filament-media-library.navigation_badge', true)) {
            return null;
        }

        return (string) MediaLibrary::query()->library()->count();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof MediaLibrary && $record->isLibrary();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-media-library::media-library.media_library.upload_section'))
                    ->description(__('filament-media-library::media-library.media_library.upload_section_description'))
                    ->schema([
                        FileUpload::make('upload')
                            ->label(__('filament-media-library::media-library.media_library.upload_file'))
                            ->required(fn (?Model $record): bool => $record === null)
                            ->disk(MediaLibrary::defaultDisk())
                            ->directory(trim((string) config('filament-media-library.directory', 'media'), '/').'/library/tmp')
                            ->visibility('public')
                            ->imagePreviewHeight('180')
                            ->openable()
                            ->downloadable()
                            ->acceptedFileTypes(['image/*', 'video/*', 'application/pdf'])
                            ->maxSize((int) ((float) config('filament-media-library.max_size', 20) * 1024))
                            ->imageEditor()
                            ->storeFiles(false)
                            ->dehydrated(false)
                            ->visible(fn (?Model $record): bool => $record === null),
                        Placeholder::make('preview')
                            ->label(__('filament-media-library::media-library.media_library.preview'))
                            ->content(function (?MediaLibrary $record): HtmlString|string {
                                if (! $record instanceof MediaLibrary || blank($record->url)) {
                                    return __('filament-media-library::media-library.media_library.no_preview');
                                }

                                if ($record->type === MediaLibrary::TYPE_IMAGE) {
                                    return new HtmlString(
                                        '<img src="'.e($record->url).'" alt="'.e((string) $record->original_name).'" class="h-44 max-w-full rounded-lg object-contain" />'
                                    );
                                }

                                return new HtmlString(
                                    '<a href="'.e($record->url).'" target="_blank" class="text-primary-600 underline">'.e((string) ($record->original_name ?: $record->path)).'</a>'
                                );
                            })
                            ->visible(fn (?Model $record): bool => $record !== null)
                            ->columnSpanFull(),
                        Select::make('folder')
                            ->label(__('filament-media-library::media-library.media_library.folder'))
                            ->options(fn (): array => MediaLibrary::adminFolderOptions())
                            ->default('general')
                            ->required(fn (?Model $record): bool => $record === null)
                            ->helperText(__('filament-media-library::media-library.media_library.folder_helper'))
                            ->visible(fn (?Model $record): bool => $record === null)
                            ->disabled(fn (?Model $record): bool => $record !== null)
                            ->dehydrated(fn (?Model $record): bool => $record === null),
                        Select::make('type')
                            ->label(__('filament-media-library::media-library.media_library.type'))
                            ->options(MediaLibrary::typeOptions())
                            ->default(MediaLibrary::TYPE_IMAGE)
                            ->required()
                            ->disabled(fn (?Model $record): bool => $record !== null)
                            ->dehydrated(fn (?Model $record): bool => $record === null),
                        TextInput::make('alt_text')
                            ->label(__('filament-media-library::media-library.media_library.alt_text'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        KeyValue::make('custom_properties')
                            ->label(__('filament-media-library::media-library.media_library.custom_properties'))
                            ->helperText(__('filament-media-library::media-library.media_library.custom_properties_helper'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->copyable(),
                ImageColumn::make('url')
                    ->label(__('filament-media-library::media-library.media_library.preview'))
                    ->square()
                    ->visibleFrom('md')
                    ->visible(fn (?MediaLibrary $record): bool => $record instanceof MediaLibrary && $record->type === MediaLibrary::TYPE_IMAGE),
                TextColumn::make('original_name')
                    ->label(__('filament-media-library::media-library.media_library.upload_file'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('folder')
                    ->label(__('filament-media-library::media-library.media_library.folder'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state, MediaLibrary $record): string => $record->folderLabel()),
                TextColumn::make('type')
                    ->label(__('filament-media-library::media-library.media_library.type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => MediaLibrary::typeOptions()[$state] ?? (string) $state),
                TextColumn::make('size')
                    ->label(__('filament-media-library::media-library.media_library.size'))
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024, 1).' KB')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament-media-library::media-library.media_library.uploaded_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('folder')
                    ->label(__('filament-media-library::media-library.media_library.folder'))
                    ->options(fn (): array => MediaLibrary::adminFolderOptions())
                    ->query(fn ($query, array $data) => $query->when(
                        filled($data['value'] ?? null),
                        fn ($q) => $q->where('folder', $data['value']),
                    )),
                SelectFilter::make('type')
                    ->label(__('filament-media-library::media-library.media_library.type'))
                    ->options(MediaLibrary::typeOptions()),
                TrashedFilter::make()
                    ->label(__('filament-media-library::media-library.media_library.trashed_filter')),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ])->dropdownTeleport(false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ])->dropdownTeleport(false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaLibrary::route('/'),
            'create' => CreateMediaLibrary::route('/create'),
            'view' => ViewMediaLibrary::route('/{record}'),
            'edit' => EditMediaLibrary::route('/{record}/edit'),
        ];
    }
}
