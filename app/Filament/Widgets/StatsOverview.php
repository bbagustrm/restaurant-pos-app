<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $thisWeekStart = Carbon::now()->startOfWeek();
        $thisWeekEnd = Carbon::now()->endOfWeek();
        $lastWeekStart = Carbon::now()->subWeek()->startOfWeek();
        $lastWeekEnd = Carbon::now()->subWeek()->endOfWeek();

        $todayRevenue = self::completedRevenueBetween($today, $today->copy()->endOfDay());
        $yesterdayRevenue = self::completedRevenueBetween($yesterday, $yesterday->copy()->endOfDay());

        $thisWeekRevenue = self::completedRevenueBetween($thisWeekStart, $thisWeekEnd);
        $lastWeekRevenue = self::completedRevenueBetween($lastWeekStart, $lastWeekEnd);

        $todayOrdersCount = Order::whereBetween('created_at', [$today, $today->copy()->endOfDay()])
            ->where('status', '!=', 'cancelled')
            ->count();

        $todayAverage = $todayOrdersCount > 0 ? (int) round($todayRevenue / $todayOrdersCount) : 0;

        return [
            self::buildRevenueStat(
                label: 'Revenue Hari Ini',
                current: $todayRevenue,
                previous: $yesterdayRevenue,
                comparisonLabel: 'kemarin',
            ),

            self::buildRevenueStat(
                label: 'Revenue Minggu Ini',
                current: $thisWeekRevenue,
                previous: $lastWeekRevenue,
                comparisonLabel: 'minggu lalu',
            ),

            Stat::make('Order Hari Ini', number_format($todayOrdersCount, 0, ',', '.'))
                ->description('Tidak termasuk yang dibatalkan')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('Rata-rata Order Hari Ini', rupiah($todayAverage))
                ->description($todayOrdersCount > 0 ? "Dari {$todayOrdersCount} order" : 'Belum ada order')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),
        ];
    }

    /**
     * Sum total_amount for completed (paid) orders in a time window.
     */
    private static function completedRevenueBetween(Carbon $start, Carbon $end): int
    {
        return (int) Order::whereBetween('created_at', [$start, $end])
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->sum('total_amount');
    }

    private static function buildRevenueStat(string $label, int $current, int $previous, string $comparisonLabel): Stat
    {
        if ($previous === 0) {
            $delta = $current > 0 ? 100 : 0;
        } else {
            $delta = (int) round((($current - $previous) / $previous) * 100);
        }

        $isUp = $current >= $previous;

        return Stat::make($label, rupiah($current))
            ->description(($isUp ? '+' : '').$delta.'% vs '.$comparisonLabel)
            ->descriptionIcon($isUp ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($isUp ? 'success' : 'danger');
    }
}
