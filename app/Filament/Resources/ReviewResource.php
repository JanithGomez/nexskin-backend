<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationIcon = 'heroicon-o-star';

    // change to 'title' if your DB column is title
    private const REVIEW_TITLE_FIELD = 'review_title';

    /**
     * ✅ Robust: supports
     * - array (proper cast)
     * - JSON string: ["url1","url2"]
     * - comma string: url1,url2
     */
    private static function mediaArray(?Review $record): array
    {
        $media = $record?->media;

        // already array (best case)
        if (is_array($media)) {
            return array_values(array_filter($media));
        }

        // null / empty
        if (! $media) {
            return [];
        }

        // JSON string?
        if (is_string($media)) {
            $trim = trim($media);

            if (str_starts_with($trim, '[') || str_starts_with($trim, '{')) {
                $decoded = json_decode($trim, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // if it's a list of urls
                    if (is_array($decoded)) {
                        // could be ["url"] OR [{"url": "..."}]
                        // normalize to [url, url]
                        $urls = [];
                        foreach ($decoded as $item) {
                            if (is_string($item)) {
                                $urls[] = $item;
                            } elseif (is_array($item) && isset($item['url'])) {
                                $urls[] = $item['url'];
                            }
                        }

                        return array_values(array_filter($urls));
                    }
                }
            }

            // fallback: comma string
            return array_values(array_filter(array_map('trim', explode(',', $trim))));
        }

        return [];
    }

    private static function isVideoUrl(string $url): bool
    {
        $u = strtolower($url);

        // Cloudinary videos often contain /video/upload/
        if (str_contains($u, '/video/upload/')) {
            return true;
        }

        return str_ends_with($u, '.mp4')
            || str_ends_with($u, '.webm')
            || str_ends_with($u, '.mov')
            || str_ends_with($u, '.m4v');
    }

    private static function isImageUrl(string $url): bool
    {
        $u = strtolower($url);

        // Cloudinary images often contain /image/upload/
        if (str_contains($u, '/image/upload/')) {
            return true;
        }

        return str_ends_with($u, '.jpg')
            || str_ends_with($u, '.jpeg')
            || str_ends_with($u, '.png')
            || str_ends_with($u, '.webp')
            || str_ends_with($u, '.gif')
            || str_ends_with($u, '.avif');
    }

    private static function mediaLabel(string $url): string
    {
        if (self::isVideoUrl($url)) {
            return 'Video';
        }
        if (self::isImageUrl($url)) {
            return 'Image';
        }

        return 'File';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),

                TextColumn::make(self::REVIEW_TITLE_FIELD)
                    ->label('Title')
                    ->limit(30)
                    ->toggleable(),

                TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => str_repeat('⭐', (int) $state))
                    ->sortable(),

                TextColumn::make('comment')
                    ->label('Comment')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('guest_name')
                    ->label('Customer')
                    ->formatStateUsing(fn ($state, Review $record) => $record->is_anonymous ? 'Anonymous' : ($state ?: 'Guest')
                    )
                    ->toggleable(),

                // ✅ Media count
                TextColumn::make('media')
                    ->label('Media')
                    ->state(fn (Review $record) => count(self::mediaArray($record)))
                    ->formatStateUsing(fn ($state) => (int) $state > 0 ? $state.' file(s)' : '-')
                    ->sortable(),

                IconColumn::make('is_approved')
                    ->label('Approved')
                    ->boolean()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Review Details')
                    ->modalWidth('4xl'),

                // ✅ Approve
                Tables\Actions\Action::make('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Review $record) => $record->update(['is_approved' => true]))
                    ->visible(fn (Review $record) => ! $record->is_approved),

                // ✅ Unapprove
                Tables\Actions\Action::make('Unapprove')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Review $record) => $record->update(['is_approved' => false]))
                    ->visible(fn (Review $record) => $record->is_approved),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Overview')
                    ->schema([
                        TextEntry::make('product.name')->label('Product'),
                        TextEntry::make('rating')
                            ->label('Rating')
                            ->formatStateUsing(fn ($state) => str_repeat('⭐', (int) $state)),
                        IconEntry::make('is_approved')->label('Approved')->boolean(),
                    ])
                    ->columns(3),

                Section::make('Customer')
                    ->schema([
                        TextEntry::make('guest_name')
                            ->label('Name')
                            ->formatStateUsing(fn ($state, Review $record) => $record->is_anonymous ? 'Anonymous' : ($state ?: 'Guest')
                            ),
                        TextEntry::make('guest_email')->label('Email')->placeholder('-'),
                        IconEntry::make('is_anonymous')->label('Anonymous')->boolean(),
                    ])
                    ->columns(3),

                Section::make('Review')
                    ->schema([
                        TextEntry::make(self::REVIEW_TITLE_FIELD)
                            ->label('Title')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        TextEntry::make('comment')
                            ->label('Comment')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                // ✅ Media section
                Section::make('Media')
                    ->schema([
                        RepeatableEntry::make('media_items')
                            ->label(false)
                            ->state(function (Review $record) {
                                $urls = self::mediaArray($record);

                                return collect($urls)->map(fn ($url) => [
                                    'type' => self::mediaLabel($url),
                                    'url' => $url,
                                ])->values()->all();
                            })
                            ->schema([
                                TextEntry::make('type')
                                    ->label('Type')
                                    ->badge(),

                                TextEntry::make('url')
                                    ->label('Open')
                                    ->url(fn ($state) => $state)
                                    ->openUrlInNewTab()
                                    ->copyable()
                                    ->copyMessage('Copied URL')
                                    ->limit(90),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),

                        // Nice message if empty
                        TextEntry::make('media_empty')
                            ->label(false)
                            ->state(fn (Review $record) => count(self::mediaArray($record)) ? null : 'No media attached.')
                            ->visible(fn (Review $record) => count(self::mediaArray($record)) === 0),
                    ]),
            ]);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
        ];
    }
}
