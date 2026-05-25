<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Events\OrderPlaced;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Settings\RestaurantSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Persist a finalized cart as an Order + OrderItems atomically.
 *
 * Inputs are intentionally primitive (cart array + scalars) so this class is
 * trivially callable from the Livewire POS component or any future API.
 */
class CheckoutOrder
{
    public function __construct(
        private readonly GenerateOrderNumber $orderNumbers,
        private readonly RestaurantSettings $settings,
    ) {}

    /**
     * @param  array<string, array{product_id: string, name: string, price: int, quantity: int, notes: ?string}>  $cart
     * @param  array{type: ?string, value: float}  $discount
     */
    public function handle(
        User $cashier,
        array $cart,
        ?string $tableId,
        string $paymentMethod,
        array $discount = ['type' => null, 'value' => 0.0],
        ?string $notes = null,
    ): Order {
        if ($cart === []) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        $taxPercentage = (float) $this->settings->tax_percentage;

        $subtotal = $this->subtotal($cart);
        $discountAmount = $this->discountAmount($subtotal, $discount['type'] ?? null, (float) ($discount['value'] ?? 0));
        $taxableBase = max(0, $subtotal - $discountAmount);
        $taxAmount = (int) round($taxableBase * $taxPercentage / 100);
        $totalAmount = max(0, $subtotal - $discountAmount + $taxAmount);

        $now = Carbon::now();

        // Retry on the off-chance another concurrent checkout grabs the same
        // order_number — the unique constraint will throw and we regenerate.
        $attempts = 0;
        $maxAttempts = 3;

        while (true) {
            try {
                $order = DB::transaction(function () use (
                    $cashier, $cart, $tableId, $paymentMethod, $discount,
                    $notes, $subtotal, $discountAmount, $taxPercentage,
                    $taxAmount, $totalAmount, $now,
                ) {
                    $order = Order::create([
                        'order_number' => $this->orderNumbers->handle($now),
                        'cashier_id' => $cashier->id,
                        'table_id' => $tableId,
                        'status' => 'confirmed',
                        'payment_method' => $paymentMethod,
                        'payment_status' => 'paid',
                        'subtotal' => $subtotal,
                        'discount_type' => $discount['type'] ?? null,
                        'discount_amount' => $discountAmount,
                        'tax_percentage' => $taxPercentage,
                        'tax_amount' => $taxAmount,
                        'total_amount' => $totalAmount,
                        'notes' => $notes,
                        'paid_at' => $now,
                    ]);

                    foreach ($cart as $item) {
                        // Re-fetch the product to lock the canonical name/price snapshot
                        // (cart values can be stale; the source of truth is the products table).
                        $product = Product::query()->findOrFail($item['product_id']);

                        OrderItem::create([
                            'order_id' => $order->id,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'product_price' => $product->price,
                            'quantity' => (int) $item['quantity'],
                            'notes' => $item['notes'] ?? null,
                            'status' => 'pending',
                        ]);
                    }

                    if ($tableId !== null) {
                        RestaurantTable::query()
                            ->whereKey($tableId)
                            ->update(['status' => 'occupied']);
                    }

                    return $order->fresh(['orderItems', 'table', 'cashier']);
                });

                // Broadcast outside the transaction so the queue worker only
                // sees the committed row when it picks up the job.
                OrderPlaced::dispatch($order);

                return $order;
            } catch (UniqueConstraintViolationException $e) {
                $attempts++;
                if ($attempts >= $maxAttempts) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param  array<string, array{price: int, quantity: int}>  $cart
     */
    private function subtotal(array $cart): int
    {
        return collect($cart)->sum(fn (array $item) => $item['price'] * $item['quantity']);
    }

    private function discountAmount(int $subtotal, ?string $type, float $value): int
    {
        if ($type === null || $value <= 0) {
            return 0;
        }

        if ($type === 'percentage') {
            return (int) round($subtotal * min(100.0, $value) / 100);
        }

        // 'fixed' — never exceed subtotal.
        return (int) min((int) $value, $subtotal);
    }
}
