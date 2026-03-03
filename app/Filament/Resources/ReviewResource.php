<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

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

        if (is_array($media)) {
            return array_values(array_filter($media));
        }

        if (! $media) {
            return [];
        }

        if (is_string($media)) {
            $trim = trim($media);

            if (str_starts_with($trim, '[') || str_starts_with($trim, '{')) {
                $decoded = json_decode($trim, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
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

            return array_values(array_filter(array_map('trim', explode(',', $trim))));
        }

        return [];
    }

    private static function isVideoUrl(string $url): bool
    {
        $u = strtolower($url);

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
        if (self::isVideoUrl($url)) return 'Video';
        if (self::isImageUrl($url)) return 'Image';
        return 'File';
    }

    private static function kvTable(string $rowsHtml): string
    {
        return <<<HTML
<div class="w-full block">
  <div class="w-full overflow-hidden rounded-xl border border-gray-200/60 dark:border-gray-700/60">
    <table class="min-w-full w-full text-sm">
      <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
        {$rowsHtml}
      </tbody>
    </table>
  </div>
</div>
HTML;
    }

    private static function overviewTableHtml(Review $record): string
    {
        $product = e($record->product?->name ?? '—');
        $title = e((string) data_get($record, self::REVIEW_TITLE_FIELD) ?: '—');
        $rating = e(str_repeat('⭐', (int) $record->rating) ?: '—');
        $approved = $record->is_approved ? 'Yes' : 'No';
        $approved = e($approved);
        $comment = e((string) ($record->comment ?? '—'));

        $rows = <<<HTML
<tr class="bg-gray-50/60 dark:bg-gray-900/40">
  <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Product</td>
  <td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100 break-words" colspan="3">{$product}</td>
</tr>

<tr>
  <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Title</td>
  <td class="px-4 py-3 text-gray-900 dark:text-gray-100 break-words" colspan="3">{$title}</td>
</tr>

<tr class="bg-gray-50/60 dark:bg-gray-900/40">
  <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Rating</td>
  <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{$rating}</td>
  <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Approved</td>
  <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{$approved}</td>
</tr>

<tr>
  <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Comment</td>
  <td class="px-4 py-3 text-gray-900 dark:text-gray-100 whitespace-pre-line break-words" colspan="3">{$comment}</td>
</tr>
HTML;

        return self::kvTable($rows);
    }

    private static function customerTableHtml(Review $record): string
    {
        $name = $record->is_anonymous ? 'Anonymous' : (($record->guest_name ?: 'Guest'));
        $email = $record->guest_email ?: '—';
        $anonymous = $record->is_anonymous ? 'Yes' : 'No';

        $name = e($name);
        $email = e($email);
        $anonymous = e($anonymous);

        $rows = <<<HTML
<tr class="bg-gray-50/60 dark:bg-gray-900/40">
  <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Name</td>
  <td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100 break-words" colspan="3">{$name}</td>
</tr>

<tr>
  <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Email</td>
  <td class="px-4 py-3 text-gray-900 dark:text-gray-100 break-all">{$email}</td>
  <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Anonymous</td>
  <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{$anonymous}</td>
</tr>
HTML;

        return self::kvTable($rows);
    }

    /**
     * ✅ Grid gallery (2–4 columns), click thumb to expand, videos play.
     */
    private static function mediaGalleryHtml(Review $record): string
    {
        $urls = self::mediaArray($record);

        if (count($urls) === 0) {
            return '<div class="text-sm text-gray-500">No media attached.</div>';
        }

        $gid = 'media_gallery_' . (int) $record->getKey();

        $itemsHtml = '';
        foreach ($urls as $i => $url) {
            $safeUrl = e($url);
            $type = self::mediaLabel($url);
            $typeEsc = e($type);

            if (self::isImageUrl($url)) {
                $thumb = <<<HTML
<img src="{$safeUrl}" alt="Media" class="h-full w-full object-cover">
HTML;
            } elseif (self::isVideoUrl($url)) {
                $thumb = <<<HTML
<div class="relative h-full w-full bg-black/40 flex items-center justify-center">
  <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-white/15 border border-white/20">▶</span>
</div>
HTML;
            } else {
                $thumb = <<<HTML
<div class="h-full w-full bg-gray-500/10 flex items-center justify-center text-xs text-gray-400 font-mono px-2">FILE</div>
HTML;
            }

            if (self::isImageUrl($url)) {
                $viewer = <<<HTML
<img src="{$safeUrl}" class="max-h-[70vh] w-full object-contain bg-black/10 dark:bg-white/5 rounded-lg">
HTML;
            } elseif (self::isVideoUrl($url)) {
                $viewer = <<<HTML
<video src="{$safeUrl}" controls playsinline class="max-h-[70vh] w-full object-contain bg-black/10 dark:bg-white/5 rounded-lg"></video>
HTML;
            } else {
                $viewer = <<<HTML
<div class="rounded-xl border border-gray-200/60 dark:border-gray-700/60 p-3 text-sm">
  <div class="text-gray-500 dark:text-gray-400">File URL</div>
  <div class="break-all font-mono text-gray-900 dark:text-gray-100">{$safeUrl}</div>
</div>
HTML;
            }

            $itemsHtml .= <<<HTML
<button type="button"
  class="group relative aspect-square overflow-hidden rounded-xl border border-gray-200/60 dark:border-gray-700/60 bg-gray-50/30 dark:bg-gray-900/30 hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
  onclick="
    const root=document.getElementById('{$gid}');
    root.querySelector('[data-viewer]').innerHTML = root.querySelector('[data-item-{$i}]').innerHTML;
    root.querySelector('[data-modal]').classList.remove('hidden');
  "
>
  {$thumb}
  <div class="absolute left-2 top-2">
    <span class="text-[11px] px-2 py-1 rounded-md bg-black/40 text-white border border-white/10">{$typeEsc}</span>
  </div>
</button>

<template data-item-{$i}>
  {$viewer}
  <div class="mt-2 text-xs text-gray-500 break-all">{$safeUrl}</div>
</template>
HTML;
        }

        return <<<HTML
<div id="{$gid}" class="w-full">
  <div class="grid gap-3 grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
    {$itemsHtml}
  </div>

  <div data-modal class="hidden fixed inset-0 z-[9999]">
    <div class="absolute inset-0 bg-black/70" onclick="
      const root=document.getElementById('{$gid}');
      root.querySelector('[data-modal]').classList.add('hidden');
      root.querySelector('[data-viewer]').innerHTML='';
    "></div>

    <div class="relative mx-auto mt-10 w-[min(1100px,92vw)] rounded-2xl border border-gray-200/20 bg-gray-950/90 p-4 shadow-2xl">
      <div class="flex items-center justify-between gap-3 mb-3">
        <div class="text-sm font-semibold text-white">Media Preview</div>
        <button type="button"
          class="rounded-lg px-3 py-1.5 text-sm text-white/90 border border-white/10 bg-white/5 hover:bg-white/10"
          onclick="
            const root=document.getElementById('{$gid}');
            root.querySelector('[data-modal]').classList.add('hidden');
            root.querySelector('[data-viewer]').innerHTML='';
          "
        >Close</button>
      </div>

      <div data-viewer class="w-full"></div>
    </div>
  </div>
</div>
HTML;
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
                    ->limit(60)
                    ->wrap()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => str_repeat('⭐', (int) $state))
                    ->sortable(),

                TextColumn::make('media')
                    ->label('Media')
                    ->state(fn (Review $record) => count(self::mediaArray($record)))
                    ->formatStateUsing(fn ($state) => (int) $state > 0 ? $state . ' file(s)' : '—')
                    ->sortable(),

                IconColumn::make('is_approved')
                    ->label('Approved')
                    ->boolean()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Review Details')
                    ->modalWidth('6xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalFooterActions(fn (Review $record) => [
                        TableAction::make('approve')
                            ->label('Approve')
                            ->icon('heroicon-o-check')
                            ->color('success')
                            ->requiresConfirmation()
                            ->modalHeading('Approve this review?')
                            ->modalDescription('This review will be marked as approved.')
                            ->action(fn () => $record->update(['is_approved' => true]))
                            ->visible(fn () => ! $record->is_approved),

                        TableAction::make('unapprove')
                            ->label('Unapprove')
                            ->icon('heroicon-o-x-mark')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('Unapprove this review?')
                            ->modalDescription('This review will be marked as not approved.')
                            ->action(fn () => $record->update(['is_approved' => false]))
                            ->visible(fn () => $record->is_approved),

                        TableAction::make('close')
                            ->label('Close')
                            ->color('gray')
                            ->close(),
                    ]),
            ])
                        ->filters([
                        Filter::make('only_id')
                            ->label('Only this review')
                            ->form([
                                TextInput::make('id')
                                    ->label('Review ID')
                                    ->numeric(),
                            ])
                            ->query(function (Builder $query, array $data): Builder {
                                if (blank($data['id'] ?? null)) {
                                    return $query;
                                }

                                return $query->whereKey((int) $data['id']);
                            })
                            ->indicateUsing(function (array $data): ?string {
                                if (blank($data['id'] ?? null)) {
                                    return null;
                                }

                                return 'Only review #' . $data['id'];
                            }),
                    ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Grid::make([
                'default' => 1,
                'lg' => 12,
            ])->schema([
                Section::make('Overview')
                    ->icon('heroicon-o-information-circle')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 7,
                    ])
                    ->schema([
                        TextEntry::make('overview_table')
                            ->label('')
                            ->state(fn (Review $record) => self::overviewTableHtml($record))
                            ->html()
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'w-full block']),
                    ]),

                Section::make('Customer')
                    ->icon('heroicon-o-user')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 5,
                    ])
                    ->schema([
                        TextEntry::make('customer_table')
                            ->label('')
                            ->state(fn (Review $record) => self::customerTableHtml($record))
                            ->html()
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'w-full block']),
                    ]),
            ]),

            Section::make('Media Gallery')
                ->icon('heroicon-o-photo')
                ->schema([
                    TextEntry::make('media_gallery')
                        ->label('')
                        ->state(fn (Review $record) => self::mediaGalleryHtml($record))
                        ->html()
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'w-full block']),
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