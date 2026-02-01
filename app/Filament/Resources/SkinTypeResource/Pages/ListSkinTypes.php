<?php

namespace App\Filament\Resources\SkinTypeResource\Pages;

use App\Filament\Resources\SkinTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSkinTypes extends ListRecords
{
    protected static string $resource = SkinTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
