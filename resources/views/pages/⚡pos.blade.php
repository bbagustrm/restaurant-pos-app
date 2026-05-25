<?php

use App\Actions\Pos\CheckoutOrder;
use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Settings\RestaurantSettings;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * POS — Cashier Point of Sale.
 *
 * Left column (≈60%): product browser with horizontal category tabs,
 * real-time search, and a 3–4 column product grid (Issue #13).
 *
 * Right column (≈40%): reactive cart with table picker, stepper qty,
 * per-item notes, discount, summary, and "Proses Order" trigger (Issue #14).
 *
 * The actual checkout modal + persistence lands in Issue #15.
 */
new #[Layout('layouts::pos')] #[Title('POS')] class extends Component
{
    /* ============================================================
     |  Product browser state (Issue #13)
     * ============================================================ */

    #[Url(as: 'cat')]
    public ?string $selectedCategoryId = null;

    #[Url(as: 'q')]
    public string $search = '';

    /* ============================================================
     |  Cart state (Issue #14)
     * ============================================================ */

    /**
     * In-memory cart, keyed by product UUID. Lives on the Livewire
     * component (not session) per the project rules.
     *
     * Each entry:
     *   [
     *     'product_id' => string,
     *     'name'       => string,
     *     'price'      => int,
     *     'quantity'   => int,
     *     'notes'      => ?string,
     *   ]
     *
     * @var array<string, array{product_id: string, name: string, price: int, quantity: int, notes: ?string}>
     */
    public array $cart = [];

    public ?string $tableId = null;

    public ?string $discountType = null; // 'fixed' | 'percentage'

    public float $discountValue = 0;

    /* ============================================================
     |  Checkout state (Issue #15)
     * ============================================================ */

    public bool $checkoutOpen = false;

    public string $paymentMethod = 'cash'; // cash | qris | transfer | card

    public ?int $cashReceived = null;

    public ?string $checkoutError = null;

    /* ============================================================
     |  Product browser actions
     * ============================================================ */

    public function selectCategory(?string $categoryId): void
    {
        $this->selectedCategoryId = $categoryId;
    }

    public function addToCart(string $productId): void
    {
        $product = Product::query()->where('id', $productId)->first();

        if ($product === null || ! $product->is_available) {
            return;
        }

        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity']++;

            return;
        }

        $this->cart[$productId] = [
            'product_id' => $productId,
            'name' => $product->name,
            'price' => (int) $product->price,
            'quantity' => 1,
            'notes' => null,
        ];
    }

    /* ============================================================
     |  Cart item actions
     * ============================================================ */

    public function incrementQty(string $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]['quantity']++;
    }

    public function decrementQty(string $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]['quantity']--;

        if ($this->cart[$productId]['quantity'] <= 0) {
            unset($this->cart[$productId]);
        }
    }

    public function removeItem(string $productId): void
    {
        unset($this->cart[$productId]);
    }

    public function setItemNotes(string $productId, ?string $notes): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $value = is_string($notes) ? trim($notes) : null;
        $this->cart[$productId]['notes'] = $value === '' ? null : $value;
    }

    /* ============================================================
     |  Discount + table
     * ============================================================ */

    public function applyDiscount(string $type, float $value): void
    {
        if (! in_array($type, ['fixed', 'percentage'], true) || $value <= 0) {
            return;
        }

        // Cap by business rule: percentage ≤ 100, fixed ≤ subtotal.
        if ($type === 'percentage') {
            $value = min(100.0, $value);
        } else {
            $value = (float) min((int) $value, $this->subtotal);
        }

        $this->discountType = $type;
        $this->discountValue = $value;
    }

    public function resetDiscount(): void
    {
        $this->discountType = null;
        $this->discountValue = 0;
    }

    public function setTable(?string $tableId): void
    {
        $this->tableId = $tableId === '' ? null : $tableId;
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->resetDiscount();
        $this->tableId = null;
    }

    /**
     * Triggered by the "Proses Order" button. Opens the checkout modal.
     */
    public function startCheckout(): void
    {
        if ($this->cart === []) {
            return;
        }

        $this->checkoutError = null;
        $this->cashReceived = null;
        $this->paymentMethod = 'cash';
        $this->checkoutOpen = true;
        $this->dispatch('checkout:open');
    }

    public function closeCheckout(): void
    {
        $this->checkoutOpen = false;
        $this->checkoutError = null;
    }

    public function setPaymentMethod(string $method): void
    {
        if (! in_array($method, ['cash', 'qris', 'transfer', 'card'], true)) {
            return;
        }

        $this->paymentMethod = $method;

        if ($method !== 'cash') {
            $this->cashReceived = null;
        }
    }

    /**
     * Persist the order, reset the cart, and open the invoice in a new tab.
     */
    public function processCheckout(CheckoutOrder $checkout): void
    {
        $this->checkoutError = null;

        if ($this->cart === []) {
            $this->checkoutError = 'Cart kosong.';

            return;
        }

        if ($this->paymentMethod === 'cash') {
            if ($this->cashReceived === null || $this->cashReceived < $this->total) {
                $this->checkoutError = 'Jumlah bayar kurang dari total.';

                return;
            }
        }

        try {
            $order = $checkout->handle(
                cashier: auth()->user(),
                cart: $this->cart,
                tableId: $this->tableId,
                paymentMethod: $this->paymentMethod,
                discount: ['type' => $this->discountType, 'value' => $this->discountValue],
            );
        } catch (\Throwable $e) {
            report($e);
            $this->checkoutError = 'Gagal menyimpan order. Coba lagi.';

            return;
        }

        $invoiceUrl = url("/orders/{$order->id}/invoice");

        $this->cart = [];
        $this->resetDiscount();
        $this->tableId = null;
        $this->checkoutOpen = false;
        $this->cashReceived = null;
        $this->paymentMethod = 'cash';

        // Dispatch a JS event the modal can listen to: open the invoice tab + show toast.
        $this->dispatch('order:created', orderId: $order->id, invoiceUrl: $invoiceUrl);
    }

    /* ============================================================
     |  Computed properties (reactive)
     * ============================================================ */

    /**
     * Active categories with a product count, ordered by sort_order.
     *
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Products filtered by selected category and search term, ordered by sort_order.
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        return Product::query()
            ->with('category')
            ->when($this->selectedCategoryId, fn ($q) => $q->where('category_id', $this->selectedCategoryId))
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Tables ordered alphabetically; null = Takeaway.
     *
     * @return Collection<int, RestaurantTable>
     */
    #[Computed]
    public function tables(): Collection
    {
        return RestaurantTable::query()->orderBy('name')->get();
    }

    #[Computed]
    public function cartItemsCount(): int
    {
        return collect($this->cart)->sum('quantity');
    }

    #[Computed]
    public function subtotal(): int
    {
        return collect($this->cart)
            ->sum(fn (array $item) => $item['price'] * $item['quantity']);
    }

    #[Computed]
    public function discountAmount(): int
    {
        if ($this->discountType === null || $this->discountValue <= 0) {
            return 0;
        }

        if ($this->discountType === 'percentage') {
            return (int) round($this->subtotal * min(100.0, $this->discountValue) / 100);
        }

        // 'fixed' — never exceed subtotal.
        return (int) min((int) $this->discountValue, $this->subtotal);
    }

    #[Computed]
    public function taxPercentage(): float
    {
        return (float) app(RestaurantSettings::class)->tax_percentage;
    }

    #[Computed]
    public function taxAmount(): int
    {
        $base = max(0, $this->subtotal - $this->discountAmount);

        return (int) round($base * $this->taxPercentage / 100);
    }

    #[Computed]
    public function total(): int
    {
        return max(0, $this->subtotal - $this->discountAmount + $this->taxAmount);
    }

    /* ============================================================
     |  Checkout computed (Issue #15)
     * ============================================================ */

    #[Computed]
    public function change(): int
    {
        if ($this->paymentMethod !== 'cash' || $this->cashReceived === null) {
            return 0;
        }

        return max(0, $this->cashReceived - $this->total);
    }

    #[Computed]
    public function isCheckoutSubmittable(): bool
    {
        if ($this->cart === []) {
            return false;
        }

        if ($this->paymentMethod === 'cash') {
            return $this->cashReceived !== null && $this->cashReceived >= $this->total;
        }

        return true;
    }
}; ?>

<div
    x-data="{
        focusSearch() {
            const el = document.getElementById('pos-search');
            if (el) el.focus();
        },
    }"
    x-on:keydown.window.f1.prevent="focusSearch()"
    x-on:keydown.window.f2.prevent="$wire.startCheckout()"
    x-on:keydown.window.escape="$wire.checkoutOpen && $wire.closeCheckout()"
    class="flex h-screen flex-col"
>
    {{-- Top bar --}}
    <header class="flex flex-shrink-0 items-center justify-between border-b border-slate-700/60 bg-slate-950/40 px-6 py-3">
        <div class="flex items-center gap-3">
            <span class="text-lg font-semibold tracking-tight">POS</span>
            <span class="text-sm text-slate-400">{{ auth()->user()?->name }}</span>
            <span class="rounded-full bg-amber-500/15 px-2.5 py-0.5 text-xs font-semibold text-amber-300 md:hidden">
                {{ $this->cartItemsCount }} item
            </span>
        </div>
        <div class="text-sm text-slate-400">
            {{ now()->format('d M Y') }}
        </div>
    </header>

    {{-- Body: 60/40 split --}}
    <div class="flex flex-1 overflow-hidden">
        {{-- LEFT: Product browser (≈60%) --}}
        <section class="flex w-3/5 min-w-0 flex-col border-r border-slate-700/60">
            {{-- Search bar --}}
            <div class="border-b border-slate-800/80 p-4">
                <label for="pos-search" class="sr-only">Cari produk</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.39 9.86l3.13 3.13a.75.75 0 1 0 1.06-1.06l-3.13-3.13A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <input
                        id="pos-search"
                        type="search"
                        wire:model.live.debounce.250ms="search"
                        placeholder="Cari produk... (F1)"
                        class="w-full rounded-xl border border-slate-700 bg-slate-800 py-3 pl-10 pr-4 text-base text-slate-100 placeholder:text-slate-500 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/30"
                    >
                </div>
            </div>

            {{-- Horizontal category tabs --}}
            <nav class="scrollbar-hide flex flex-shrink-0 gap-2 overflow-x-auto border-b border-slate-800/80 px-4 py-3">
                <button
                    type="button"
                    wire:click="selectCategory(null)"
                    @class([
                        'flex h-11 min-w-[80px] flex-shrink-0 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium transition',
                        'bg-amber-500 text-slate-900' => $selectedCategoryId === null,
                        'bg-slate-800 text-slate-300 hover:bg-slate-700' => $selectedCategoryId !== null,
                    ])
                >
                    Semua
                </button>

                @foreach ($this->categories as $category)
                    <button
                        type="button"
                        wire:key="cat-{{ $category->id }}"
                        wire:click="selectCategory('{{ $category->id }}')"
                        @class([
                            'flex h-11 min-w-[120px] flex-shrink-0 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium transition',
                            'bg-amber-500 text-slate-900' => $selectedCategoryId === $category->id,
                            'bg-slate-800 text-slate-300 hover:bg-slate-700' => $selectedCategoryId !== $category->id,
                        ])
                    >
                        @if ($category->icon)
                            <span class="text-base">{{ $category->icon }}</span>
                        @endif
                        <span>{{ $category->name }}</span>
                        <span class="rounded-full bg-slate-900/40 px-2 py-0.5 text-xs">
                            {{ $category->products_count }}
                        </span>
                    </button>
                @endforeach
            </nav>

            {{-- Product grid --}}
            <div class="flex-1 overflow-y-auto p-4">
                @if ($this->products->isEmpty())
                    <div class="flex h-full flex-col items-center justify-center text-slate-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mb-3 h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                        <p>Tidak ada produk yang cocok.</p>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                        @foreach ($this->products as $product)
                            <article
                                wire:key="prod-{{ $product->id }}"
                                @class([
                                    'group relative flex flex-col overflow-hidden rounded-xl border border-slate-700 bg-slate-800 transition',
                                    'opacity-50 grayscale' => ! $product->is_available,
                                    'hover:border-amber-500/60 hover:bg-slate-750' => $product->is_available,
                                ])
                            >
                                <div class="relative aspect-square w-full bg-slate-900">
                                    @if ($product->image)
                                        <img
                                            src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image) }}"
                                            alt="{{ $product->name }}"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        >
                                    @else
                                        <div class="flex h-full w-full items-center justify-center text-slate-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                            </svg>
                                        </div>
                                    @endif

                                    @if ($product->is_featured)
                                        <span class="absolute left-2 top-2 rounded-full bg-amber-500 px-2 py-0.5 text-xs font-semibold text-slate-900">
                                            ★ Featured
                                        </span>
                                    @endif

                                    @unless ($product->is_available)
                                        <span class="absolute right-2 top-2 rounded-full bg-rose-500/90 px-2 py-0.5 text-xs font-semibold text-white">
                                            Habis
                                        </span>
                                    @endunless
                                </div>

                                <div class="flex flex-1 flex-col p-3">
                                    <h3 class="line-clamp-2 text-sm font-semibold leading-snug text-slate-100">
                                        {{ $product->name }}
                                    </h3>
                                    <p class="mt-0.5 text-xs text-slate-400">{{ $product->category?->name }}</p>
                                    <div class="mt-auto flex items-center justify-between pt-3">
                                        <span class="text-base font-bold text-amber-400">
                                            {{ rupiah((int) $product->price) }}
                                        </span>
                                        <button
                                            type="button"
                                            wire:click="addToCart('{{ $product->id }}')"
                                            @disabled(! $product->is_available)
                                            aria-label="Tambah {{ $product->name }} ke cart"
                                            class="flex h-11 w-11 items-center justify-center rounded-lg bg-amber-500 text-slate-900 font-bold transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-500"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- RIGHT: Cart panel (≈40%) --}}
        <aside
            x-data="{
                discountModalOpen: false,
                discountType: 'percentage',
                discountValue: '',
                openDiscountModal() {
                    this.discountType = '{{ $discountType ?? 'percentage' }}';
                    this.discountValue = {{ $discountValue ?: 0 }};
                    this.discountModalOpen = true;
                },
            }"
            class="flex w-2/5 min-w-0 flex-col bg-slate-950/30"
        >
            {{-- Cart header: table picker --}}
            <div class="flex flex-shrink-0 items-center justify-between gap-3 border-b border-slate-800 px-5 py-4">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold">Keranjang</h2>
                    <p class="text-xs text-slate-400">
                        {{ $this->cartItemsCount }} item
                    </p>
                </div>
                <label class="flex flex-1 items-center gap-2">
                    <span class="sr-only">Meja</span>
                    <select
                        wire:model.live="tableId"
                        class="h-11 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 text-sm text-slate-100 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500/40"
                    >
                        <option value="">🥡 Takeaway</option>
                        @foreach ($this->tables as $table)
                            <option value="{{ $table->id }}">{{ $table->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            {{-- Item list --}}
            <div class="flex-1 overflow-y-auto px-3 py-2">
                @if (empty($cart))
                    <div class="flex h-full flex-col items-center justify-center px-6 text-center text-slate-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mb-3 h-14 w-14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                        <p class="text-sm font-medium text-slate-300">Cart kosong</p>
                        <p class="mt-1 text-xs">Pilih produk dari kiri untuk mulai order.</p>
                    </div>
                @else
                    <ul class="space-y-2">
                        @foreach ($cart as $productId => $item)
                            <li
                                wire:key="cart-{{ $productId }}"
                                x-data="{ showNotes: {{ filled($item['notes']) ? 'true' : 'false' }} }"
                                class="rounded-xl border border-slate-700 bg-slate-800 p-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-100">{{ $item['name'] }}</p>
                                        <p class="mt-0.5 text-xs text-slate-400">{{ rupiah($item['price']) }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        x-on:click="showNotes = !showNotes"
                                        aria-label="Catatan untuk {{ $item['name'] }}"
                                        @class([
                                            'flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg border transition',
                                            'border-amber-500/60 bg-amber-500/10 text-amber-300' => filled($item['notes']),
                                            'border-slate-700 bg-slate-900 text-slate-400 hover:border-slate-500' => empty($item['notes']),
                                        ])
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="removeItem('{{ $productId }}')"
                                        aria-label="Hapus {{ $item['name'] }}"
                                        class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg border border-slate-700 bg-slate-900 text-slate-400 transition hover:border-rose-500/60 hover:bg-rose-500/10 hover:text-rose-300"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>

                                {{-- Notes input (Alpine collapse) --}}
                                <div
                                    x-show="showNotes"
                                    x-collapse
                                    x-cloak
                                    class="mt-2"
                                >
                                    <input
                                        type="text"
                                        wire:change="setItemNotes('{{ $productId }}', $event.target.value)"
                                        value="{{ $item['notes'] }}"
                                        placeholder="Catatan: tanpa gula, pedas, dst..."
                                        maxlength="255"
                                        class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs text-slate-100 placeholder:text-slate-500 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500/40"
                                    >
                                </div>

                                {{-- Qty stepper + subtotal per item --}}
                                <div class="mt-3 flex items-center justify-between">
                                    <div class="flex h-10 items-center overflow-hidden rounded-lg border border-slate-700 bg-slate-900">
                                        <button
                                            type="button"
                                            wire:click="decrementQty('{{ $productId }}')"
                                            aria-label="Kurangi {{ $item['name'] }}"
                                            class="flex h-full w-10 items-center justify-center text-slate-300 transition hover:bg-slate-700"
                                        >
                                            −
                                        </button>
                                        <span class="flex h-full w-12 items-center justify-center border-x border-slate-700 text-sm font-semibold tabular-nums">
                                            {{ $item['quantity'] }}
                                        </span>
                                        <button
                                            type="button"
                                            wire:click="incrementQty('{{ $productId }}')"
                                            aria-label="Tambah {{ $item['name'] }}"
                                            class="flex h-full w-10 items-center justify-center text-amber-300 transition hover:bg-amber-500/10"
                                        >
                                            +
                                        </button>
                                    </div>
                                    <span class="text-sm font-semibold text-amber-400 tabular-nums">
                                        {{ rupiah($item['price'] * $item['quantity']) }}
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Summary + actions --}}
            @if (! empty($cart))
                <div class="flex flex-shrink-0 flex-col gap-3 border-t border-slate-800 bg-slate-950/50 px-5 py-4">
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between text-slate-300">
                            <dt>Subtotal</dt>
                            <dd class="tabular-nums">{{ rupiah($this->subtotal) }}</dd>
                        </div>

                        @if ($this->discountAmount > 0)
                            <div class="flex justify-between text-emerald-400">
                                <dt class="flex items-center gap-2">
                                    Diskon
                                    @if ($discountType === 'percentage')
                                        <span class="text-xs text-emerald-500/70">({{ rtrim(rtrim(number_format($discountValue, 2, ',', '.'), '0'), ',') }}%)</span>
                                    @endif
                                    <button
                                        type="button"
                                        wire:click="resetDiscount"
                                        aria-label="Hapus diskon"
                                        class="text-emerald-300/70 hover:text-rose-400"
                                    >
                                        ✕
                                    </button>
                                </dt>
                                <dd class="tabular-nums">- {{ rupiah($this->discountAmount) }}</dd>
                            </div>
                        @endif

                        <div class="flex justify-between text-slate-300">
                            <dt>Pajak {{ rtrim(rtrim(number_format($this->taxPercentage, 2, ',', '.'), '0'), ',') }}%</dt>
                            <dd class="tabular-nums">{{ rupiah($this->taxAmount) }}</dd>
                        </div>

                        <div class="flex justify-between border-t border-slate-700 pt-2 text-base font-bold text-slate-100">
                            <dt>Total</dt>
                            <dd class="tabular-nums text-amber-400">{{ rupiah($this->total) }}</dd>
                        </div>
                    </dl>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            x-on:click="openDiscountModal()"
                            class="flex h-11 flex-1 items-center justify-center rounded-lg border border-slate-700 bg-slate-800 text-sm font-medium text-slate-200 transition hover:border-amber-500/60 hover:text-amber-300"
                        >
                            {{ $this->discountAmount > 0 ? 'Edit Diskon' : 'Tambah Diskon' }}
                        </button>
                        <button
                            type="button"
                            wire:click="clearCart"
                            wire:confirm="Kosongkan keranjang?"
                            aria-label="Kosongkan keranjang"
                            class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg border border-slate-700 bg-slate-800 text-slate-400 transition hover:border-rose-500/60 hover:bg-rose-500/10 hover:text-rose-300"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>

                    <button
                        type="button"
                        wire:click="startCheckout"
                        class="flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-amber-500 text-base font-bold text-slate-900 transition hover:bg-amber-400"
                    >
                        Proses Order — {{ rupiah($this->total) }}
                    </button>
                </div>
            @endif

            {{-- Checkout modal (Issue #15) --}}
            <div
                x-data="{
                    init() {
                        Livewire.on('order:created', (payload) => {
                            const url = Array.isArray(payload) ? payload[0]?.invoiceUrl : payload?.invoiceUrl;
                            if (url) {
                                window.open(url, '_blank');
                            }
                            window.dispatchEvent(new CustomEvent('pos:toast', {
                                detail: { message: 'Order berhasil disimpan.' }
                            }));
                        });
                    }
                }"
                x-show="$wire.checkoutOpen"
                x-cloak
                x-on:keydown.escape.window="$wire.checkoutOpen && $wire.closeCheckout()"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm"
            >
                <div
                    x-on:click.outside="$wire.closeCheckout()"
                    x-transition.scale.opacity
                    class="w-[min(94vw,520px)] rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-100">Checkout</h3>
                            <p class="mt-1 text-xs text-slate-400">{{ $this->cartItemsCount }} item · pilih metode pembayaran</p>
                        </div>
                        <button
                            type="button"
                            wire:click="closeCheckout"
                            aria-label="Tutup checkout"
                            class="text-slate-400 hover:text-slate-200"
                        >
                            ✕
                        </button>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-2">
                        @foreach (['cash' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer', 'card' => 'Kartu'] as $value => $label)
                            <button
                                type="button"
                                wire:click="setPaymentMethod('{{ $value }}')"
                                @class([
                                    'h-12 rounded-lg border text-sm font-medium transition',
                                    'border-amber-500 bg-amber-500/10 text-amber-300' => $paymentMethod === $value,
                                    'border-slate-700 bg-slate-800 text-slate-300 hover:border-slate-500' => $paymentMethod !== $value,
                                ])
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-5 space-y-2 rounded-lg bg-slate-800/60 p-4 text-sm">
                        <div class="flex justify-between text-slate-300">
                            <span>Subtotal</span><span class="tabular-nums">{{ rupiah($this->subtotal) }}</span>
                        </div>
                        @if ($this->discountAmount > 0)
                            <div class="flex justify-between text-emerald-400">
                                <span>Diskon</span><span class="tabular-nums">- {{ rupiah($this->discountAmount) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-slate-300">
                            <span>Pajak {{ rtrim(rtrim(number_format($this->taxPercentage, 2, ',', '.'), '0'), ',') }}%</span>
                            <span class="tabular-nums">{{ rupiah($this->taxAmount) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-slate-700 pt-2 text-base font-bold text-slate-100">
                            <span>Total</span><span class="tabular-nums text-amber-400">{{ rupiah($this->total) }}</span>
                        </div>
                    </div>

                    @if ($paymentMethod === 'cash')
                        <label class="mt-4 block">
                            <span class="text-xs font-medium text-slate-300">Jumlah Bayar</span>
                            <input
                                type="number"
                                min="0"
                                step="any"
                                wire:model.live.debounce.250ms="cashReceived"
                                placeholder="0"
                                class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-3 text-base font-semibold tabular-nums text-slate-100 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500/40"
                            >
                        </label>

                        <div class="mt-3 flex items-center justify-between rounded-lg bg-slate-800/60 px-4 py-3 text-sm">
                            <span class="text-slate-300">Kembalian</span>
                            <span @class([
                                'tabular-nums text-base font-bold',
                                'text-emerald-400' => $this->change > 0,
                                'text-slate-400' => $this->change === 0,
                            ])>
                                {{ rupiah($this->change) }}
                            </span>
                        </div>

                        @if ($cashReceived !== null && $cashReceived < $this->total)
                            <p class="mt-2 text-xs font-medium text-rose-400">
                                Jumlah bayar kurang dari total.
                            </p>
                        @endif
                    @endif

                    @if ($checkoutError)
                        <p class="mt-3 rounded-md border border-rose-500/40 bg-rose-500/10 px-3 py-2 text-xs text-rose-300">
                            {{ $checkoutError }}
                        </p>
                    @endif

                    <div class="mt-6 flex gap-2">
                        <button
                            type="button"
                            wire:click="closeCheckout"
                            class="h-12 flex-1 rounded-lg border border-slate-700 bg-slate-800 text-sm font-medium text-slate-300 hover:bg-slate-700"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            wire:click="processCheckout"
                            wire:loading.attr="disabled"
                            wire:target="processCheckout"
                            @disabled(! $this->isCheckoutSubmittable)
                            class="h-12 flex-[2] rounded-lg bg-amber-500 text-base font-bold text-slate-900 transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-500"
                        >
                            <span wire:loading.remove wire:target="processCheckout">Bayar &amp; Selesai · {{ rupiah($this->total) }}</span>
                            <span wire:loading wire:target="processCheckout">Memproses...</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Toast (Alpine-only, Issue #23 will polish) --}}
            <div
                x-data="{ show: false, message: '' }"
                x-on:pos:toast.window="message = $event.detail.message; show = true; setTimeout(() => show = false, 3000)"
                x-show="show"
                x-cloak
                x-transition.opacity
                class="fixed bottom-6 right-6 z-50 rounded-lg border border-emerald-500/60 bg-emerald-500/15 px-4 py-3 text-sm font-medium text-emerald-200 shadow-lg"
            >
                <span x-text="message"></span>
            </div>

            {{-- Discount modal (Alpine.js) --}}
            <div
                x-show="discountModalOpen"
                x-cloak
                x-on:keydown.escape.window="discountModalOpen = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm"
            >
                <div
                    x-on:click.outside="discountModalOpen = false"
                    x-transition.scale.opacity
                    class="w-[min(92vw,420px)] rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl"
                >
                    <h3 class="text-lg font-semibold text-slate-100">Tambah Diskon</h3>
                    <p class="mt-1 text-xs text-slate-400">Diskon dihitung sebelum pajak.</p>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            x-on:click="discountType = 'percentage'"
                            x-bind:class="discountType === 'percentage' ? 'border-amber-500 bg-amber-500/10 text-amber-300' : 'border-slate-700 bg-slate-800 text-slate-300'"
                            class="h-11 rounded-lg border text-sm font-medium transition"
                        >
                            Persen (%)
                        </button>
                        <button
                            type="button"
                            x-on:click="discountType = 'fixed'"
                            x-bind:class="discountType === 'fixed' ? 'border-amber-500 bg-amber-500/10 text-amber-300' : 'border-slate-700 bg-slate-800 text-slate-300'"
                            class="h-11 rounded-lg border text-sm font-medium transition"
                        >
                            Nominal (Rp)
                        </button>
                    </div>

                    <label class="mt-4 block">
                        <span class="text-xs font-medium text-slate-300">Nilai diskon</span>
                        <input
                            type="number"
                            x-model="discountValue"
                            min="0"
                            step="any"
                            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500/40"
                        >
                    </label>

                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            x-on:click="discountModalOpen = false"
                            class="h-11 rounded-lg border border-slate-700 bg-slate-800 px-4 text-sm font-medium text-slate-300 hover:bg-slate-700"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            x-on:click="$wire.applyDiscount(discountType, Number(discountValue) || 0); discountModalOpen = false"
                            class="h-11 rounded-lg bg-amber-500 px-5 text-sm font-bold text-slate-900 hover:bg-amber-400"
                        >
                            Terapkan
                        </button>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>
