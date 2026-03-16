<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StateResource\Pages;
use App\Models\State;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StateResource extends Resource
{
    protected static ?string $model = State::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'States';

    protected static ?string $modelLabel = 'State';

    protected static ?string $pluralModelLabel = 'States';


    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('State Details')
                ->schema([

                    Forms\Components\TextInput::make('name')
                        ->label('State Name')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->placeholder('Example: Colombo'),

                ])

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('name')
                    ->label('State')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since(),

            ])
            ->filters([
                //
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

            'index' => Pages\ListStates::route('/'),

            'create' => Pages\CreateState::route('/create'),

            'edit' => Pages\EditState::route('/{record}/edit'),

        ];
    }
}