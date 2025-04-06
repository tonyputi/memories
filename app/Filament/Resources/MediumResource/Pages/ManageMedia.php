<?php

namespace App\Filament\Resources\MediumResource\Pages;

use App\Filament\Resources\MediumResource;
use App\Jobs;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageMedia extends ManageRecords
{
    protected static string $resource = MediumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('restore')
                ->form([
                    Forms\Components\Select::make('disk_id')
                        ->options(request()->user()->disks()->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\FileUpload::make('path')
                        ->disk('uploads')
                        ->directory(Str::uuid7())
                        ->previewable(false)
                        ->moveFiles()
                        ->required(),
                ])
                ->action(function (array $data) {
                    Jobs\RestoreMedia::dispatch($data['path'], $data['disk_id']);
                }),
        ];
    }
}
