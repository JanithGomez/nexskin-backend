<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Shipping Address')
                    ->schema([
                        TextEntry::make('address.full_address')
                            ->label('Address'),

                        TextEntry::make('address.city'),

                        TextEntry::make('address.country'),

                        TextEntry::make('address.phone'),
                    ])
                    ->columns(2),
            ]);
    }
}
