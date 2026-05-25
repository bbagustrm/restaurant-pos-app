<?php

namespace App\Events;

use App\Models\OrderItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when an order item changes status (pending → preparing → ready → served).
 * Kitchen Display updates the card border / status pill in real time.
 */
class OrderItemStatusUpdated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public OrderItem $orderItem) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('kitchen')];
    }

    public function broadcastAs(): string
    {
        return 'OrderItemStatusUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->orderItem->id,
            'order_id' => $this->orderItem->order_id,
            'status' => $this->orderItem->status,
        ];
    }
}
