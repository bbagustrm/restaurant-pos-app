<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Category;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category'))
            ->defaultSort('sort_order', 'asc')
            ->columns([
                ImageColumn::make('image')
                    ->label('Foto')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn () => asset('images/product-placeholder.png')),

                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn ($record) => str($record->slug)->limit(40)),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn ($state) => rupiah((int) $state))
                    ->sortable(),

                ToggleColumn::make('is_available')
                    ->label('Tersedia'),

                ToggleColumn::make('is_featured')
                    ->label('Featured'),

                TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('deleted_at')
                    ->label('Dihapus')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->options(fn () => Category::orderBy('sort_order')->pluck('name', 'id'))
                    ->multiple()
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_available')
                    ->label('Tersedia')
                    ->placeholder('Semua')
                    ->trueLabel('Tersedia')
                    ->falseLabel('Tidak tersedia'),

                TernaryFilter::make('is_featured')
                    ->label('Featured')
                    ->placeholder('Semua')
                    ->trueLabel('Featured')
                    ->falseLabel('Reguler'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_available')
                        ->label('Tandai Tersedia')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_available' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_unavailable')
                        ->label('Tandai Tidak Tersedia')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_available' => false]))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada produk')
            ->emptyStateDescription('Tambahkan produk untuk mulai mengelola menu.');
    }
}
