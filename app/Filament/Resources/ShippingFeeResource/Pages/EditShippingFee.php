<?php

namespace App\Filament\Resources\ShippingFeeResource\Pages;

use App\Filament\Resources\ShippingFeeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShippingFee extends EditRecord
{
    protected static string $resource = ShippingFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
