<?php

use App\Events\OrderItemStatusUpdated;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Kitchen Display System.
 *
 * Shows active kitchen orders (status confirmed | preparing) in a card grid
 * sorted PENDING → PREPARING → READY. Realtime listener (Issue #18) refreshes
 * the list when OrderPlaced/OrderCancelled events arrive.
 */
new #[Layout('layouts::kitchen')] #[Title('Kitchen Display')] class extends Component
{
    /**
     * Move an order from confirmed → preparing. Each contained item also
     * flips to 'preparing' so OrderItemStatusUpdated fires for the broadcast.
     */
    public function startPreparing(string $orderId): void
    {
        $order = Order::query()->with('orderItems')->find($orderId);

        if ($order === null || $order->status !== 'confirmed') {
            return;
        }

        $order->update(['status' => 'preparing']);

        foreach ($order->orderItems as $item) {
            $item->update(['status' => 'preparing']);
            OrderItemStatusUpdated::dispatch($item->fresh());
        }
    }

    /**
     * Move an order from preparing → ready. Items also flip to 'ready' so
     * the kitchen card auto-dismisses after a short interval (Issue #18).
     */
    public function markReady(string $orderId): void
    {
        $order = Order::query()->with('orderItems')->find($orderId);

        if ($order === null || $order->status !== 'preparing') {
            return;
        }

        $order->update(['status' => 'ready']);

        foreach ($order->orderItems as $item) {
            $item->update(['status' => 'ready']);
            OrderItemStatusUpdated::dispatch($item->fresh());
        }
    }

    /**
     * Reload the order list when a realtime event arrives. Triggered by the
     * Echo listeners on the front end (Issue #18).
     */
    #[On('refresh-kitchen')]
    public function refresh(): void
    {
        // No-op — re-running the render is enough to re-evaluate $orders.
        unset($this->orders);
    }

    /**
     * Echo listener for OrderPlaced. The browser dispatches this with the
     * payload from broadcastWith() and we just trigger a re-render.
     *
     * @param  array<int, array<string, mixed>>  $event
     */
    #[On('echo:kitchen,OrderPlaced')]
    public function onOrderPlaced(array $event = []): void
    {
        unset($this->orders);
    }

    /**
     * Echo listener for OrderCancelled.
     *
     * @param  array<int, array<string, mixed>>  $event
     */
    #[On('echo:kitchen,OrderCancelled')]
    public function onOrderCancelled(array $event = []): void
    {
        unset($this->orders);
    }

    /**
     * Echo listener for OrderItemStatusUpdated.
     *
     * @param  array<int, array<string, mixed>>  $event
     */
    #[On('echo:kitchen,OrderItemStatusUpdated')]
    public function onOrderItemStatusUpdated(array $event = []): void
    {
        unset($this->orders);
    }

    /**
     * Active kitchen orders: confirmed + preparing always; recently-ready
     * (last 30s) included so the card auto-dismisses on the front end.
     * Sort priority: confirmed (PENDING) → preparing → ready, oldest first within each.
     *
     * @return Collection<int, Order>
     */
    #[Computed(persist: false)]
    public function orders(): Collection
    {
        return Order::query()
            ->where(function ($q) {
                $q->whereIn('status', ['confirmed', 'preparing'])
                    ->orWhere(function ($qq) {
                        $qq->where('status', 'ready')
                            ->where('updated_at', '>=', now()->subSeconds(30));
                    });
            })
            ->with(['orderItems', 'table'])
            ->orderByRaw("FIELD(status, 'confirmed', 'preparing', 'ready')")
            ->orderBy('created_at')
            ->get();
    }
}; ?>

<div
    x-data="{
        clock: '',
        connected: false,
        tick() {
            const now = new Date();
            this.clock = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        },
        playBeep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.value = 880;
                gain.gain.setValueAtTime(0.0001, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.4, ctx.currentTime + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.5);
                osc.start();
                osc.stop(ctx.currentTime + 0.55);
            } catch (e) {
                // Audio context can fail without user interaction; silently ignore.
            }
        },
        wireSocketStatus() {
            const echo = window.Echo;
            if (!echo || !echo.connector) {
                this.connected = false;
                return;
            }
            const conn = echo.connector.pusher?.connection;
            if (!conn) return;
            this.connected = conn.state === 'connected';
            conn.bind('state_change', (states) => {
                this.connected = states.current === 'connected';
            });
        },
        init() {
            this.tick();
            setInterval(() => this.tick(), 1000);
            this.wireSocketStatus();

            window.addEventListener('echo:kitchen,OrderPlaced', () => this.playBeep());
            window.Echo?.channel?.('kitchen')
                .listen('.OrderPlaced', () => this.playBeep());
        },
    }"
    class="flex h-screen flex-col"
>
    {{-- Header --}}
    <header class="flex flex-shrink-0 items-center justify-between border-b border-slate-800 bg-slate-950 px-8 py-5">
        <div class="flex items-baseline gap-3">
            <h1 class="text-2xl font-bold tracking-tight text-amber-400">Kitchen Display</h1>
            <span class="text-base text-slate-400">{{ app(\App\Settings\RestaurantSettings::class)->restaurant_name }}</span>
            <span class="flex items-center gap-2 text-xs">
                <span x-bind:class="connected ? 'bg-emerald-500' : 'bg-rose-500'" class="inline-block h-2.5 w-2.5 rounded-full"></span>
                <span class="text-slate-400" x-text="connected ? 'Online' : 'Offline'">Offline</span>
            </span>
        </div>
        <div class="text-3xl font-bold tabular-nums text-slate-100" x-text="clock">--:--:--</div>
    </header>

    {{-- Order grid --}}
    <main class="flex-1 overflow-y-auto p-6">
        @if ($this->orders->isEmpty())
            <div class="flex h-full flex-col items-center justify-center text-slate-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="mb-4 h-16 w-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a6.759 6.759 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.991l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281Z" />
                </svg>
                <p class="text-xl font-medium text-slate-300">Tidak ada order aktif</p>
                <p class="mt-2 text-sm">Order baru akan muncul di sini secara otomatis.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->orders as $order)
                    <article
                        wire:key="order-{{ $order->id }}"
                        x-data="{
                            visible: false,
                            init() {
                                this.$nextTick(() => this.visible = true);
                                @if ($order->status === 'ready')
                                    // Auto-dismiss READY cards after 30s.
                                    setTimeout(() => {
                                        this.visible = false;
                                        setTimeout(() => $wire.dispatch('refresh-kitchen'), 350);
                                    }, 30000);
                                @endif
                            },
                        }"
                        x-show="visible"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-x-8"
                        x-transition:enter-end="opacity-100 translate-x-0"
                        x-transition:leave="transition ease-in duration-300"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        @class([
                            'flex flex-col rounded-2xl border-4 bg-slate-900 transition-all',
                            'border-amber-400 shadow-[0_0_30px_-10px_rgba(251,191,36,0.6)]' => $order->status === 'confirmed',
                            'border-sky-500 shadow-[0_0_30px_-10px_rgba(56,189,248,0.6)]' => $order->status === 'preparing',
                            'border-emerald-500 shadow-[0_0_30px_-10px_rgba(16,185,129,0.6)]' => $order->status === 'ready',
                        ])
                    >
                        {{-- Card header --}}
                        <header @class([
                            'flex items-center justify-between rounded-t-xl px-5 py-3 text-base font-bold',
                            'bg-amber-400 text-slate-900' => $order->status === 'confirmed',
                            'bg-sky-500 text-white' => $order->status === 'preparing',
                            'bg-emerald-500 text-white' => $order->status === 'ready',
                        ])>
                            <span>
                                @if ($order->status === 'confirmed')
                                    PENDING
                                @elseif ($order->status === 'preparing')
                                    PREPARING
                                @else
                                    READY ✓
                                @endif
                            </span>
                            <span
                                class="text-sm font-medium tabular-nums opacity-90"
                                x-data="{
                                    elapsed: '',
                                    update() {
                                        const start = new Date('{{ $order->created_at?->toIso8601String() }}');
                                        const diff = Math.floor((Date.now() - start.getTime()) / 1000);
                                        const m = Math.floor(diff / 60);
                                        const s = diff % 60;
                                        this.elapsed = m + 'm ' + String(s).padStart(2, '0') + 's';
                                    },
                                    init() { this.update(); setInterval(() => this.update(), 1000); },
                                }"
                                x-text="elapsed"
                            >
                                0m 00s
                            </span>
                        </header>

                        {{-- Card body --}}
                        <div class="flex flex-1 flex-col gap-4 p-5">
                            <div class="flex items-baseline justify-between">
                                <span class="text-2xl font-bold tracking-tight text-slate-100">
                                    {{ $order->order_number }}
                                </span>
                                <span class="rounded-full bg-slate-800 px-3 py-1 text-sm font-medium text-slate-300">
                                    {{ $order->table?->name ?? 'Takeaway' }}
                                </span>
                            </div>

                            <ul class="space-y-2">
                                @foreach ($order->orderItems as $item)
                                    <li class="rounded-lg bg-slate-800/70 p-3">
                                        <div class="flex items-baseline gap-2">
                                            <span class="text-2xl font-bold tabular-nums text-amber-400">
                                                {{ $item->quantity }}×
                                            </span>
                                            <span class="text-lg font-semibold text-slate-100">
                                                {{ $item->product_name }}
                                            </span>
                                        </div>
                                        @if (filled($item->notes))
                                            <p class="mt-1 text-base italic text-amber-300">
                                                ⚠ {{ $item->notes }}
                                            </p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            @if (filled($order->notes))
                                <p class="rounded-md bg-slate-800/50 px-3 py-2 text-base italic text-slate-300">
                                    Catatan order: {{ $order->notes }}
                                </p>
                            @endif
                        </div>

                        {{-- Action button --}}
                        <footer class="border-t border-slate-700 p-3">
                            @if ($order->status === 'confirmed')
                                <button
                                    type="button"
                                    wire:click="startPreparing('{{ $order->id }}')"
                                    class="flex h-14 w-full items-center justify-center rounded-lg bg-amber-400 text-lg font-bold text-slate-900 transition hover:bg-amber-300"
                                >
                                    Mulai Masak →
                                </button>
                            @elseif ($order->status === 'preparing')
                                <button
                                    type="button"
                                    wire:click="markReady('{{ $order->id }}')"
                                    class="flex h-14 w-full items-center justify-center rounded-lg bg-emerald-500 text-lg font-bold text-white transition hover:bg-emerald-400"
                                >
                                    Selesai ✓
                                </button>
                            @else
                                <div class="flex h-14 w-full items-center justify-center text-emerald-400 font-medium">
                                    Hilang otomatis dalam 30 detik
                                </div>
                            @endif
                        </footer>
                    </article>
                @endforeach
            </div>
        @endif
    </main>
</div>
