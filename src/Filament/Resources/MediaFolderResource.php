<?php

namespace Xpier\FilamentMediaLibrary\Filament\Resources;

use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaFolders\Pages\CreateMediaFolder;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaFolders\Pages\EditMediaFolder;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaFolders\Pages\ListMediaFolders;
use Xpier\FilamentMediaLibrary\Models\MediaFolder;

class MediaFolderResource extends Resource
{
    protected static ?string $model = MediaFolder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'media-folders';

    public static function getNavigationLabel(): string
    {
        return __('filament-media-library::media-library.resources.media_folder.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament-media-library::media-library.resources.media_folder.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-media-library::media-library.resources.media_folder.plural_label');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return (string) (config('filament-media-library.navigation_group') ?: __('filament-media-library::media-library.navigation.group'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('parent_id')
                ->label(__('filament-media-library::media-library.media_folder.parent_folder'))
                ->helperText(__('filament-media-library::media-library.media_folder.parent_helper'))
                ->options(fn (): array => MediaFolder::query()
                    ->whereNull('parent_id')
                    ->orderBy('sort')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->nullable(),
            TextInput::make('name')
                ->label(__('filament-media-library::media-library.media_folder.name'))
                ->required()
                ->maxLength(100)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                    if (filled($get('code')) || blank($state)) {
                        return;
                    }
                    $set('code', Str::slug($state) ?: 'folder');
                }),
            TextInput::make('code')
                ->label(__('filament-media-library::media-library.media_folder.code'))
                ->helperText(__('filament-media-library::media-library.media_folder.code_helper'))
                ->required()
                ->maxLength(64)
                ->unique(ignoreRecord: true)
                ->disabled(fn (?MediaFolder $record): bool => $record !== null)
                ->dehydrated(),
            TextInput::make('sort')
                ->label(__('filament-media-library::media-library.media_folder.sort'))
                ->numeric()
                ->default(0)
                ->required(),
            Toggle::make('is_active')
                ->label(__('filament-media-library::media-library.media_folder.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->copyable(),
                TextColumn::make('name')->label(__('filament-media-library::media-library.media_folder.name'))->searchable(),
                TextColumn::make('code')->label(__('filament-media-library::media-library.media_folder.code'))->copyable(),
                TextColumn::make('parent.name')->label(__('filament-media-library::media-library.media_folder.parent_folder'))->placeholder('-'),
                TextColumn::make('storage_path')->label(__('filament-media-library::media-library.media_folder.storage_path'))->copyable(),
                TextColumn::make('files_count')
                    ->label(__('filament-media-library::media-library.media_folder.files_count'))
                    ->getStateUsing(fn (MediaFolder $record): int => $record->mediaFilesQuery()->count()),
                TextColumn::make('sort')->label(__('filament-media-library::media-library.media_folder.sort'))->sortable(),
                ToggleColumn::make('is_active')->label(__('filament-media-library::media-library.media_folder.is_active')),
                TextColumn::make('updated_at')->label(__('filament-media-library::media-library.media_folder.updated_at'))->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('sort')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ])->dropdownTeleport(false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ])->dropdownTeleport(false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaFolders::route('/'),
            'create' => CreateMediaFolder::route('/create'),
            'edit' => EditMediaFolder::route('/{record}/edit'),
        ];
    }
}
