<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['table', 'cashier', 'orderItems.product']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Order')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->copyable(),

                TextColumn::make('table.name')
                    ->label('Meja')
                    ->placeholder('Takeaway')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('cashier.name')
                    ->label('Kasir')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'confirmed' => 'Dikonfirmasi',
                        'preparing' => 'Diproses',
                        'ready' => 'Siap',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'confirmed' => 'info',
                        'preparing' => 'warning',
                        'ready' => 'success',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('payment_method')
                    ->label('Pembayaran')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash' => 'Tunai',
                        'qris' => 'QRIS',
                        'transfer' => 'Transfer',
                        'card' => 'Kartu',
                        null => '—',
                        default => $state,
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'cash' => 'success',
                        'qris' => 'info',
                        'transfer' => 'primary',
                        'card' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => rupiah((int) $state))
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->since()
                    ->tooltip(fn ($record) => $record->created_at?->format('d M Y H:i'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Dikonfirmasi',
                        'preparing' => 'Diproses',
                        'ready' => 'Siap',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ])
                    ->multiple(),

                SelectFilter::make('payment_method')
                    ->label('Pembayaran')
                    ->options([
                        'cash' => 'Tunai',
                        'qris' => 'QRIS',
                        'transfer' => 'Transfer',
                        'card' => 'Kartu',
                    ])
                    ->multiple(),

                SelectFilter::make('cashier_id')
                    ->label('Kasir')
                    ->relationship('cashier', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('created_at')
                    ->label('Tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Dari: '.$data['from'];
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Sampai: '.$data['until'];
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Detail')
                    ->modalHeading(fn ($record) => 'Detail Order '.$record->order_number)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),

                Action::make('invoice')
                    ->label('Invoice')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->url(fn ($record) => "/orders/{$record->id}/invoice")
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Belum ada order')
            ->emptyStateDescription('Order yang dibuat dari POS akan tampil di sini.');
    }
}
