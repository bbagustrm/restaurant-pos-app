<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 12px; margin: 0; padding: 32px; }
        h1 { font-size: 22px; margin: 0 0 4px; letter-spacing: 1px; }
        h2 { font-size: 14px; margin: 16px 0 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .center { text-align: center; }
        .header { display: table; width: 100%; margin-bottom: 24px; }
        .header > div { display: table-cell; vertical-align: top; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta td { padding: 4px 8px; vertical-align: top; }
        .meta td:first-child { color: #6b7280; width: 110px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th, table.items td { padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        table.items th { background: #f9fafb; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; }
        table.items td.qty { text-align: center; width: 50px; }
        table.items td.amount { text-align: right; width: 110px; white-space: nowrap; }
        .totals { width: 320px; margin-left: auto; margin-top: 16px; }
        .totals tr td { padding: 4px 8px; }
        .totals tr td:first-child { color: #6b7280; }
        .totals tr td:last-child { text-align: right; white-space: nowrap; }
        .totals .grand td { padding-top: 10px; border-top: 2px solid #111827; font-size: 14px; font-weight: bold; }
        .footer { margin-top: 36px; padding-top: 12px; border-top: 1px dashed #d1d5db; text-align: center; color: #6b7280; white-space: pre-line; font-size: 11px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 10px; font-weight: 600; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>{{ $settings->restaurant_name }}</h1>
            <div class="muted">{{ $settings->restaurant_address }}</div>
            <div class="muted">{{ $settings->restaurant_phone }}</div>
        </div>
        <div class="right">
            <h1>INVOICE</h1>
            <div class="muted">{{ $order->order_number }}</div>
            <div class="muted">{{ $order->created_at?->format('d M Y H:i') }}</div>
        </div>
    </div>

    <h2>Detail</h2>
    <table class="meta">
        <tr>
            <td>Kasir</td>
            <td>{{ $order->cashier?->name ?? '—' }}</td>
            <td>Status</td>
            <td><span class="badge">{{ strtoupper($order->status) }}</span></td>
        </tr>
        <tr>
            <td>Meja</td>
            <td>{{ $order->table?->name ?? 'Takeaway' }}</td>
            <td>Pembayaran</td>
            <td>{{ strtoupper($order->payment_method ?? '—') }}</td>
        </tr>
    </table>

    <h2>Item</h2>
    <table class="items">
        <thead>
            <tr>
                <th>Produk</th>
                <th class="qty">Qty</th>
                <th class="amount">Harga</th>
                <th class="amount">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->orderItems as $item)
                <tr>
                    <td>
                        {{ $item->product_name }}
                        @if (filled($item->notes))
                            <div class="muted" style="font-size: 10px;">⚠ {{ $item->notes }}</div>
                        @endif
                    </td>
                    <td class="qty">{{ $item->quantity }}</td>
                    <td class="amount">{{ rupiah((int) $item->product_price) }}</td>
                    <td class="amount">{{ rupiah((int) $item->product_price * $item->quantity) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td>{{ rupiah((int) $order->subtotal) }}</td>
        </tr>
        @if ((int) $order->discount_amount > 0)
            <tr>
                <td>Diskon ({{ $order->discount_type }})</td>
                <td>- {{ rupiah((int) $order->discount_amount) }}</td>
            </tr>
        @endif
        <tr>
            <td>Pajak ({{ rtrim(rtrim(number_format((float) $order->tax_percentage, 2, ',', '.'), '0'), ',') }}%)</td>
            <td>{{ rupiah((int) $order->tax_amount) }}</td>
        </tr>
        <tr class="grand">
            <td>Total</td>
            <td>{{ rupiah((int) $order->total_amount) }}</td>
        </tr>
    </table>

    @if ($order->notes)
        <h2>Catatan</h2>
        <p class="muted">{{ $order->notes }}</p>
    @endif

    <div class="footer">{{ $settings->receipt_footer }}</div>
</body>
</html>
