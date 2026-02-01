<?php

namespace App\Filament\Resources\SkinTypeResource\Pages;

use App\Filament\Resources\SkinTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSkinType extends EditRecord
{
    protected static string $resource = SkinTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
