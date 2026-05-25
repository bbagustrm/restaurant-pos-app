<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class OrdersChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Revenue 30 Hari Terakhir';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $start = Carbon::today()->subDays(29);
        $end = Carbon::today()->endOfDay();

        // Pre-fill all 30 days with 0 so gaps render as zero rather than skipped points.
        $buckets = [];
        for ($i = 0; $i < 30; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $buckets[$date] = 0;
        }

        Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->selectRaw('DATE(created_at) as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->each(function ($total, $day) use (&$buckets) {
                $buckets[$day] = (int) $total;
            });

        $labels = array_map(
            fn (string $date) => Carbon::parse($date)->format('d M'),
            array_keys($buckets),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (Rp)',
                    'data' => array_values($buckets),
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(217, 119, 6, 0.15)',
                    'borderColor' => 'rgb(217, 119, 6)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
