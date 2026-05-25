<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Super admins always allowed; other policy methods short-circuit via this.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    /**
     * Anyone authenticated can list (filtering happens in resources/queries).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * The cashier who created the order or an admin can view it.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->cashier_id;
    }

    /**
     * Cancellation: only the cashier owner, only while still cancellable.
     */
    public function cancel(User $user, Order $order): bool
    {
        if (! in_array($order->status, ['pending', 'confirmed'], true)) {
            return false;
        }

        return $user->id === $order->cashier_id;
    }

    /**
     * Refund: admin only — handled by `before()`.
     */
    public function refund(User $user, Order $order): bool
    {
        return false;
    }
}
