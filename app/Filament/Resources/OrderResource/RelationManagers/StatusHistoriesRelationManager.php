<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'Status Timeline';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'order_status',
                        'warning' => 'payment_status',
                    ])
                    ->formatStateUsing(fn (string $state) =>
                        $state === 'order_status' ? 'Order Status' : 'Payment Status'
                    ),

                Tables\Columns\TextColumn::make('from_status')
                    ->label('From')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('to_status')
                    ->label('To')
                    ->badge(),

                Tables\Columns\TextColumn::make('changer.name')
                    ->label('Changed By')
                    ->placeholder('System'),

                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Changed At')
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}