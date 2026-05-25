<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Generate the next order number in the form ORD-YYYYMMDD-XXXX.
 *
 * The XXXX counter resets each day. This is a best-effort generator; the
 * unique constraint on orders.order_number is the final safeguard against
 * collisions, and the caller is expected to retry on UniqueConstraintError.
 */
class GenerateOrderNumber
{
    public function handle(?Carbon $now = null): string
    {
        $now ??= Carbon::now();

        $datePrefix = 'ORD-'.$now->format('Ymd');

        $todayCount = Order::query()
            ->whereDate('created_at', $now->toDateString())
            ->count();

        return $datePrefix.'-'.str_pad((string) ($todayCount + 1), 4, '0', STR_PAD_LEFT);
    }
}
