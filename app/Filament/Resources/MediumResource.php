<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediumResource\Pages;
use App\Models\Disk;
use App\Models\Medium;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Number;

class MediumResource extends Resource
{
    protected static ?string $model = Medium::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('disk_id')
                    ->relationship('disk', 'name')
                    ->required()
                    ->live(),
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('type')
                    ->disabled(),
                Forms\Components\TextInput::make('size')
                    ->formatStateUsing(fn ($state) => Number::fileSize($state, 2))
                    ->disabled(),
                Forms\Components\FileUpload::make('path')
                    ->label('File')
                    ->required()
                    ->disk(fn (Get $get) => Disk::find($get('disk_id'))->getKey())
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('meta')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('url')
                    ->label('Thumbnail')
                    ->circular(),
                Tables\Columns\TextColumn::make('disk.name')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('path')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('hash')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('size')
                    ->formatStateUsing(fn ($state) => Number::fileSize($state, 2))
                    ->sortable(),
                Tables\Columns\TextColumn::make('latitude')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('longitude')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('disk.name')
                    ->attribute('disk_id')
                    ->options(Disk::all()->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('type')
                    ->attribute('type')
                    ->options(Medium::all()->pluck('type', 'type')->unique())
                    ->searchable(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
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
            'index' => Pages\ManageMedia::route('/'),
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
