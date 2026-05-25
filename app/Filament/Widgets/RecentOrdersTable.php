<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentOrdersTable extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = '10 Order Terakhir';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Order::query()
                ->with(['table', 'cashier'])
                ->latest('created_at')
            )
            ->paginated([10])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Order')
                    ->weight('semibold'),

                TextColumn::make('table.name')
                    ->label('Meja')
                    ->placeholder('Takeaway'),

                TextColumn::make('cashier.name')
                    ->label('Kasir'),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => rupiah((int) $state))
                    ->alignEnd(),

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

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->since()
                    ->tooltip(fn ($record) => $record->created_at?->format('d M Y H:i')),
            ])
            ->emptyStateHeading('Belum ada order');
    }
}
