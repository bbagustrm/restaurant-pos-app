<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a cashier creates a new order. The Kitchen Display listens
 * to the public 'kitchen' channel and renders the new order card.
 */
class OrderPlaced implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order)
    {
        // Eager-load relations the Kitchen UI needs so the broadcast payload
        // is self-contained (no extra HTTP round-trips needed).
        $this->order->loadMissing(['orderItems.product', 'table', 'cashier']);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('kitchen')];
    }

    public function broadcastAs(): string
    {
        return 'OrderPlaced';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'table' => $this->order->table?->only(['id', 'name']),
            'cashier' => $this->order->cashier?->only(['id', 'name']),
            'items' => $this->order->orderItems->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'notes' => $item->notes,
                'status' => $item->status,
            ])->all(),
            'created_at' => $this->order->created_at?->toIso8601String(),
            'notes' => $this->order->notes,
        ];
    }
}
