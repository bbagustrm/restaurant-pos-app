<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TopProductsTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = '5 Produk Terlaris Bulan Ini';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        return $table
            ->query(fn (): Builder => Product::query()
                ->select('products.*')
                ->selectSub(
                    DB::table('order_items')
                        ->join('orders', 'orders.id', '=', 'order_items.order_id')
                        ->whereColumn('order_items.product_id', 'products.id')
                        ->whereBetween('orders.created_at', [$monthStart, $monthEnd])
                        ->where('orders.status', '!=', 'cancelled')
                        ->selectRaw('COALESCE(SUM(order_items.quantity), 0)'),
                    'qty_sold',
                )
                ->selectSub(
                    DB::table('order_items')
                        ->join('orders', 'orders.id', '=', 'order_items.order_id')
                        ->whereColumn('order_items.product_id', 'products.id')
                        ->whereBetween('orders.created_at', [$monthStart, $monthEnd])
                        ->where('orders.status', '!=', 'cancelled')
                        ->selectRaw('COALESCE(SUM(order_items.quantity * order_items.product_price), 0)'),
                    'revenue',
                )
                ->orderByDesc('qty_sold')
                ->limit(5)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Produk')
                    ->weight('semibold')
                    ->description(fn ($record) => $record->category?->name),

                TextColumn::make('qty_sold')
                    ->label('Qty')
                    ->numeric()
                    ->alignEnd(),

                TextColumn::make('revenue')
                    ->label('Revenue')
                    ->formatStateUsing(fn ($state) => rupiah((int) $state))
                    ->alignEnd(),
            ])
            ->emptyStateHeading('Belum ada penjualan bulan ini');
    }
}
