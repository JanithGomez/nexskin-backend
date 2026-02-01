<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SkinTypeResource\Pages;
use App\Models\SkinType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class SkinTypeResource extends Resource
{
    protected static ?string $model = SkinType::class;

    protected static ?string $navigationIcon = 'heroicon-o-face-smile';

    protected static ?string $navigationGroup = 'Catalog';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSkinTypes::route('/'),
            'create' => Pages\CreateSkinType::route('/create'),
            'edit' => Pages\EditSkinType::route('/{record}/edit'),
        ];
    }
}
