<?php

namespace App\Filament\Resources;

use App\Enums\DiskDriver;
use App\Enums\DiskVisibility;
use App\Filament\Resources\DiskResource\Pages;
use App\Jobs;
use App\Models\Disk;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiskResource extends Resource
{
    protected static ?string $model = Disk::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->helperText('The user that owns the disk.')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->helperText('The name of the disk.'),
                Forms\Components\Select::make('driver')
                    ->options(DiskDriver::class)
                    ->disabled(fn ($record) => $record !== null)
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        if ($get('driver') === DiskDriver::Local->value) {
                            $set('config.url', config('filesystems.disks.local.url'));
                            $set('config.root', config('filesystems.disks.local.root'));
                            $set('config.visibility', DiskVisibility::Private->value);
                        } elseif ($get('driver') === DiskDriver::S3->value) {
                            $set('config.url', config('filesystems.disks.s3.url'));
                            $set('config.root', config('filesystems.disks.s3.root'));
                            $set('config.visibility', DiskVisibility::Public->value);
                            $set('config.key', config('filesystems.disks.s3.key'));
                            $set('config.secret', config('filesystems.disks.s3.secret'));
                            $set('config.region', config('filesystems.disks.s3.region'));
                            $set('config.bucket', config('filesystems.disks.s3.bucket'));
                            $set('config.endpoint', config('filesystems.disks.s3.endpoint'));
                            $set('config.use_path_style_endpoint', (int) config('filesystems.disks.s3.use_path_style_endpoint'));
                        }
                    })
                    ->helperText('The driver of the disk.'),
                Forms\Components\TextInput::make('config.url')
                    ->hidden(fn (Get $get) => empty($get('driver')))
                    ->required()
                    ->helperText('The URL of the disk.'),
                Forms\Components\TextInput::make('config.root')
                    ->hidden(fn (Get $get) => empty($get('driver')))
                    ->required(fn (Get $get) => $get('driver') === DiskDriver::Local->value)
                    ->helperText('The root of the disk.'),
                Forms\Components\Select::make('config.visibility')
                    ->hidden(fn (Get $get) => empty($get('driver')))
                    ->options(DiskVisibility::class)
                    ->searchable()
                    ->required()
                    ->helperText('The visibility of the disk.'),
                Forms\Components\TextInput::make('config.key')
                    ->visible(fn (Get $get) => $get('driver') === DiskDriver::S3->value)
                    ->required()
                    ->helperText('The key of the disk.'),
                Forms\Components\TextInput::make('config.secret')
                    ->visible(fn (Get $get) => $get('driver') === DiskDriver::S3->value)
                    ->required()
                    ->helperText('The secret of the disk.'),
                Forms\Components\TextInput::make('config.region')
                    ->visible(fn (Get $get) => $get('driver') === DiskDriver::S3->value)
                    ->required()
                    ->helperText('The region of the disk.'),
                Forms\Components\TextInput::make('config.bucket')
                    ->visible(fn (Get $get) => $get('driver') === DiskDriver::S3->value)
                    ->required()
                    ->helperText('The bucket of the disk.'),
                Forms\Components\TextInput::make('config.endpoint')
                    ->visible(fn (Get $get) => $get('driver') === DiskDriver::S3->value)
                    ->required()
                    ->helperText('The endpoint of the disk.'),
                Forms\Components\Select::make('config.use_path_style_endpoint')
                    ->visible(fn (Get $get) => $get('driver') === DiskDriver::S3->value)
                    ->options(['No', 'Yes'])
                    ->required()
                    ->helperText('The use path style endpoint of the disk.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('driver')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('driver')
                    ->options(DiskDriver::class),
            ])
            ->actions([
                Tables\Actions\Action::make('import')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\Tabs::make('source')
                            ->statePath('tabs')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('Computer')
                                    ->statePath('computer')
                                    ->schema([
                                        Forms\Components\FileUpload::make('path')
                                            ->label('File')
                                            ->disk('uploads')
                                            ->directory(Str::uuid7())
                                            ->previewable(false)
                                            ->required(fn (Get $get): bool => $get('activeTab') === 'computer'),
                                    ]),
                                Forms\Components\Tabs\Tab::make('Uploads')
                                    ->statePath('uploads')
                                    ->schema([
                                        Forms\Components\Select::make('path')
                                            ->label('Archive')
                                            ->options(function () {
                                                // TODO: This should be converted into a kind of Collection file
                                                $storage = Storage::disk('uploads');

                                                return collect($storage->allFiles())
                                                    ->filter(fn ($file) => $storage->mimeType($file) === 'application/zip')
                                                    ->mapWithKeys(fn ($file) => [$file => $file]);
                                            })
                                            ->searchable()
                                            ->required(fn (Get $get): bool => $get('activeTab') === 'uploads'),
                                    ]),
                            ]),
                    ])
                    ->action(function (array $data, Disk $disk) {
                        $values = [];
                        foreach (data_get($data, 'tabs', []) as $tab) {
                            $values = array_merge($values, array_filter($tab));
                        }

                        $path = Storage::disk('uploads')->path(data_get($values, 'path'));
                        Jobs\RestoreMedia::dispatch($path, $disk, false);
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDisks::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
