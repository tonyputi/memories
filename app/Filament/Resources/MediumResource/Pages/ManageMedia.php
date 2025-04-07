<?php

namespace App\Filament\Resources\MediumResource\Pages;

use App\Filament\Resources\MediumResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMedia extends ManageRecords
{
    protected static string $resource = MediumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
        ];
    }
}
