<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingFeeResource\Pages;
use App\Models\ShippingFee;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingFeeResource extends Resource
{
    protected static ?string $model = ShippingFee::class;

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Shipping Fees';

    protected static ?string $modelLabel = 'Shipping Fee';

    protected static ?string $pluralModelLabel = 'Shipping Fees';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([

            Forms\Components\Section::make()
                ->schema([

                    Forms\Components\Select::make('state_id')
                        ->relationship('state','name')
                        ->searchable()
                        ->required(),

                    Forms\Components\TextInput::make('price')
                        ->label('Shipping Price')
                        ->numeric()
                        ->prefix('LKR')
                        ->required(),

                ])

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('state.name')
                    ->label('State')
                    ->searchable(),

                Tables\Columns\TextColumn::make('price')
                    ->money('LKR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->label('Created'),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [

            'index' => Pages\ListShippingFees::route('/'),
            'create' => Pages\CreateShippingFee::route('/create'),
            'edit' => Pages\EditShippingFee::route('/{record}/edit'),

        ];
    }
}