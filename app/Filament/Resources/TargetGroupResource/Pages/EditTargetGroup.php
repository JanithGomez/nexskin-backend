<?php

namespace App\Filament\Resources\TargetGroupResource\Pages;

use App\Filament\Resources\TargetGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTargetGroup extends EditRecord
{
    protected static string $resource = TargetGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
