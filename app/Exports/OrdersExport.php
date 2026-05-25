<?php

namespace App\Exports;

use App\Filament\Pages\Reports;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Multi-sheet Excel export: "Ringkasan" (per-day) + "Detail Transaksi".
 * Implements ShouldQueue so large date ranges run on the queue worker.
 */
class OrdersExport implements ShouldQueue, WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array{from: ?string, until: ?string, cashier_id: ?string, payment_method: ?string}  $filters
     */
    public function __construct(public array $filters) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new OrdersSummaryExport($this->filters),
            new OrdersDetailExport($this->filters),
        ];
    }
}

class OrdersDetailExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    use Exportable;

    /**
     * @param  array{from: ?string, until: ?string, cashier_id: ?string, payment_method: ?string}  $filters
     */
    public function __construct(public array $filters) {}

    public function query()
    {
        $page = new Reports;
        $page->filters = $this->filters;

        return $page->buildOrdersQuery()
            ->with(['table', 'cashier'])
            ->orderByDesc('orders.created_at');
    }

    public function headings(): array
    {
        return [
            'No. Order',
            'Tanggal',
            'Kasir',
            'Meja',
            'Status',
            'Pembayaran',
            'Subtotal',
            'Diskon',
            'Pajak',
            'Total',
        ];
    }

    /**
     * @param  Order  $order
     */
    public function map($order): array
    {
        return [
            $order->order_number,
            $order->created_at?->format('Y-m-d H:i'),
            $order->cashier?->name,
            $order->table?->name ?? 'Takeaway',
            $order->status,
            $order->payment_method,
            (int) $order->subtotal,
            (int) $order->discount_amount,
            (int) $order->tax_amount,
            (int) $order->total_amount,
        ];
    }

    public function title(): string
    {
        return 'Detail Transaksi';
    }
}
