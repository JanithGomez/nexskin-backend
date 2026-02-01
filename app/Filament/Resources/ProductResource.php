<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['primaryImage']);
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('slug', Str::slug($state));
                    }),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true),

                Forms\Components\Textarea::make('short_description'),

                Forms\Components\RichEditor::make('description')
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\RichEditor::make('benefits')->columnSpanFull(),
                Forms\Components\RichEditor::make('how_to_use')->columnSpanFull(),

                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('stock')
                    ->numeric()
                    ->default(0),

                Forms\Components\Select::make('brand_id')
                    ->relationship('brand', 'name')
                    ->required()
                    ->searchable(),

                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable(),

                Forms\Components\Select::make('skin_type_id')
                    ->relationship('skinType', 'name'),

                Forms\Components\Select::make('product_type_id')
                    ->label('Product Type')
                    ->relationship('productType', 'name')
                    ->required(),

                Forms\Components\MultiSelect::make('ingredients')
                    ->relationship('ingredients', 'name')
                    ->preload(),

                Forms\Components\MultiSelect::make('targetGroups')
                    ->relationship('targetGroups', 'name')
                    ->preload(),

                Forms\Components\Toggle::make('is_active')
                    ->default(true),

                Forms\Components\Repeater::make('images')
                    ->relationship()
                    ->label('Product Images')
                    ->schema([
                        Forms\Components\FileUpload::make('image_url')
                            ->label('Image')
                            ->image()
                            ->disk('cloudinary')
                            ->directory('products')
                            ->visibility('public')
                            ->getUploadedFileNameForStorageUsing(function ($file) {
                                $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                                return Str::slug($name);
                            })
                            ->imagePreviewHeight('150')
                            ->required(),

                        Forms\Components\Toggle::make('is_primary')
                            ->label('Primary Image'),
                    ])
                    ->columns(2)
                    ->orderable('sort')
                    ->defaultItems(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('primaryImage')
                ->label('Image')
                ->boolean()
                ->trueIcon('heroicon-o-photo')
                ->falseIcon('heroicon-o-x-mark')
                ->state(function ($record) {
                    return (bool) $record->primaryImage;
                }),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('price')->money('LKR'),
                Tables\Columns\TextColumn::make('stock'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->date(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}