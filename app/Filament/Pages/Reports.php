<?php

namespace App\Filament\Pages;

use App\Exports\OrdersExport;
use App\Models\Order;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class Reports extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected string $view = 'filament.pages.reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan Penjualan';

    public ?array $filters = [
        'from' => null,
        'until' => null,
        'cashier_id' => null,
        'payment_method' => null,
    ];

    public function mount(): void
    {
        $this->filters['from'] ??= Carbon::today()->subDays(29)->toDateString();
        $this->filters['until'] ??= Carbon::today()->toDateString();
        $this->form->fill($this->filters);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->components([
                Section::make('Filter')
                    ->columns(4)
                    ->components([
                        DatePicker::make('from')
                            ->label('Dari')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Sampai')
                            ->native(false),
                        Select::make('cashier_id')
                            ->label('Kasir')
                            ->options(fn () => User::query()
                                ->whereHas('roles', fn ($q) => $q->where('name', 'cashier'))
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                        Select::make('payment_method')
                            ->label('Pembayaran')
                            ->options([
                                'cash' => 'Tunai',
                                'qris' => 'QRIS',
                                'transfer' => 'Transfer',
                                'card' => 'Kartu',
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('apply')
                ->label('Terapkan Filter')
                ->icon(Heroicon::OutlinedFunnel)
                ->action(fn () => $this->resetTable()),

            Action::make('export')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->action(function () {
                    $from = $this->filters['from'] ?? Carbon::today()->toDateString();
                    $until = $this->filters['until'] ?? Carbon::today()->toDateString();
                    $filename = "laporan-{$from}-to-{$until}.xlsx";

                    return (new OrdersExport($this->filters))->download($filename);
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->buildOrdersQuery())
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('table.name')
                    ->label('Meja')
                    ->placeholder('Takeaway'),
                TextColumn::make('cashier.name')
                    ->label('Kasir'),
                TextColumn::make('payment_method')
                    ->label('Pembayaran')
                    ->badge(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => rupiah((int) $state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    /**
     * Base query that all stats, chart, and table share.
     */
    public function buildOrdersQuery(): Builder
    {
        $from = $this->filters['from'] ?? null;
        $until = $this->filters['until'] ?? null;

        return Order::query()
            ->with(['table', 'cashier'])
            ->when($from, fn (Builder $q, $d) => $q->whereDate('orders.created_at', '>=', $d))
            ->when($until, fn (Builder $q, $d) => $q->whereDate('orders.created_at', '<=', $d))
            ->when($this->filters['cashier_id'] ?? null, fn (Builder $q, $id) => $q->where('orders.cashier_id', $id))
            ->when($this->filters['payment_method'] ?? null, fn (Builder $q, $m) => $q->where('orders.payment_method', $m))
            ->where('orders.status', '!=', 'cancelled');
    }

    /**
     * @return array{revenue: int, transactions: int, average: int, items_sold: int}
     */
    public function getStats(): array
    {
        $base = $this->buildOrdersQuery();
        $revenue = (int) (clone $base)->where('payment_status', 'paid')->sum('total_amount');
        $transactions = (clone $base)->count();
        $average = $transactions > 0 ? (int) round($revenue / $transactions) : 0;

        $itemsSold = (int) (clone $base)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->sum('order_items.quantity');

        return compact('revenue', 'transactions', 'average', 'itemsSold');
    }

    /**
     * Per-day revenue for the selected range, used by the chart.
     *
     * @return array{labels: list<string>, data: list<int>}
     */
    public function getChartData(): array
    {
        $from = Carbon::parse($this->filters['from'] ?? Carbon::today()->subDays(29));
        $until = Carbon::parse($this->filters['until'] ?? Carbon::today());

        $buckets = [];
        for ($d = $from->copy(); $d->lte($until); $d->addDay()) {
            $buckets[$d->toDateString()] = 0;
        }

        $rows = (clone $this->buildOrdersQuery())
            ->where('payment_status', 'paid')
            ->selectRaw('DATE(orders.created_at) as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        foreach ($rows as $day => $total) {
            $buckets[$day] = (int) $total;
        }

        $labels = array_map(fn ($date) => Carbon::parse($date)->format('d M'), array_keys($buckets));

        return ['labels' => $labels, 'data' => array_values($buckets)];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
