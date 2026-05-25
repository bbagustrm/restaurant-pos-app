<?php

namespace App\Exports;

use App\Filament\Pages\Reports;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * "Ringkasan" sheet — one row per day in the filter range with
 * revenue, transaction count, and average transaction value.
 */
class OrdersSummaryExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    use Exportable;

    /**
     * @param  array{from: ?string, until: ?string, cashier_id: ?string, payment_method: ?string}  $filters
     */
    public function __construct(public array $filters) {}

    public function collection()
    {
        $from = Carbon::parse($this->filters['from'] ?? Carbon::today()->subDays(29));
        $until = Carbon::parse($this->filters['until'] ?? Carbon::today());

        $rows = (new Reports);
        $rows->filters = $this->filters;

        $query = $rows->buildOrdersQuery()
            ->where('payment_status', 'paid')
            ->selectRaw('DATE(orders.created_at) as day, SUM(total_amount) as revenue, COUNT(*) as transactions')
            ->groupBy('day');

        $byDay = $query->get()->keyBy('day');

        $out = collect();
        for ($d = $from->copy(); $d->lte($until); $d->addDay()) {
            $key = $d->toDateString();
            $row = $byDay->get($key);
            $out->push([
                'day' => $key,
                'revenue' => (int) ($row->revenue ?? 0),
                'transactions' => (int) ($row->transactions ?? 0),
                'average' => ($row && $row->transactions > 0) ? (int) round($row->revenue / $row->transactions) : 0,
            ]);
        }

        return $out;
    }

    public function headings(): array
    {
        return ['Tanggal', 'Revenue', 'Jumlah Transaksi', 'Rata-rata Transaksi'];
    }

    /**
     * @param  array{day: string, revenue: int, transactions: int, average: int}  $row
     */
    public function map($row): array
    {
        return [
            $row['day'],
            $row['revenue'],
            $row['transactions'],
            $row['average'],
        ];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }
}
