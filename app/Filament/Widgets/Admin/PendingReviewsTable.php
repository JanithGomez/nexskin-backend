<?php

namespace App\Filament\Widgets\Admin;

use App\Filament\Resources\ReviewResource;
use App\Models\Review;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PendingReviewsTable extends TableWidget
{
    protected static ?string $heading = 'Reviews awaiting approval';
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 6;

    private const REVIEW_TITLE_FIELD = 'review_title';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Review::query()
                    ->with(['product:id,name'])
                    ->where('is_approved', false)
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->limit(28)
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\TextColumn::make(self::REVIEW_TITLE_FIELD)
                    ->label('Title')
                    ->limit(40)
                    ->wrap(),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->state(fn (Review $record) => str_repeat('⭐', (int) $record->rating))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Review $record) => ReviewResource::getUrl('index', [
                        'tableFilters' => [
                            'only_id' => [
                                'id' => $record->getKey(),
                            ],
                        ],
                        'tablePage' => 1,
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}