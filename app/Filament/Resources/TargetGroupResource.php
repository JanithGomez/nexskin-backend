<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TargetGroupResource\Pages;
use App\Models\TargetGroup;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class TargetGroupResource extends Resource
{
    protected static ?string $model = TargetGroup::class;

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?string $navigationIcon = 'heroicon-o-users';

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
            'index' => Pages\ListTargetGroups::route('/'),
            'create' => Pages\CreateTargetGroup::route('/create'),
            'edit' => Pages\EditTargetGroup::route('/{record}/edit'),
        ];
    }
}
