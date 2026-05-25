<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $order->order_number }}</title>
    <style>
        @page { margin: 4mm 3mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans Mono', monospace; font-size: 10px; line-height: 1.35; margin: 0; padding: 0; color: #000; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .upper { text-transform: uppercase; letter-spacing: 0.05em; }
        .divider { border-top: 1px dashed #000; margin: 4px 0; }
        .row { display: table; width: 100%; }
        .row > div { display: table-cell; vertical-align: top; }
        .row .l { width: 60%; }
        .row .r { text-align: right; width: 40%; }
        h1 { font-size: 12px; margin: 0 0 2px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td.qty { width: 24px; }
        td.amount { text-align: right; white-space: nowrap; width: 64px; }
        .item-notes { font-size: 9px; padding-left: 4px; padding-bottom: 2px; }
        .footer { margin-top: 6px; padding-top: 4px; border-top: 1px dashed #000; white-space: pre-line; font-size: 9px; }
    </style>
</head>
<body>
    <div class="center">
        <h1 class="bold upper">{{ $settings->restaurant_name }}</h1>
        <div>{{ $settings->restaurant_address }}</div>
        <div>{{ $settings->restaurant_phone }}</div>
    </div>
    <div class="divider"></div>

    <div class="row">
        <div class="l">No</div>
        <div class="r bold">{{ $order->order_number }}</div>
    </div>
    <div class="row">
        <div class="l">Tgl</div>
        <div class="r">{{ $order->created_at?->format('d/m/Y H:i') }}</div>
    </div>
    <div class="row">
        <div class="l">Kasir</div>
        <div class="r">{{ $order->cashier?->name ?? '—' }}</div>
    </div>
    <div class="row">
        <div class="l">Meja</div>
        <div class="r">{{ $order->table?->name ?? 'Takeaway' }}</div>
    </div>

    <div class="divider"></div>

    <table>
        @foreach ($order->orderItems as $item)
            <tr>
                <td colspan="3" class="bold">{{ $item->product_name }}</td>
            </tr>
            <tr>
                <td class="qty">{{ $item->quantity }}×</td>
                <td>{{ rupiah((int) $item->product_price) }}</td>
                <td class="amount">{{ rupiah((int) $item->product_price * $item->quantity) }}</td>
            </tr>
            @if (filled($item->notes))
                <tr><td colspan="3" class="item-notes">> {{ $item->notes }}</td></tr>
            @endif
        @endforeach
    </table>

    <div class="divider"></div>

    <div class="row">
        <div class="l">Subtotal</div>
        <div class="r">{{ rupiah((int) $order->subtotal) }}</div>
    </div>
    @if ((int) $order->discount_amount > 0)
        <div class="row">
            <div class="l">Diskon</div>
            <div class="r">- {{ rupiah((int) $order->discount_amount) }}</div>
        </div>
    @endif
    <div class="row">
        <div class="l">Pajak {{ rtrim(rtrim(number_format((float) $order->tax_percentage, 2, ',', '.'), '0'), ',') }}%</div>
        <div class="r">{{ rupiah((int) $order->tax_amount) }}</div>
    </div>
    <div class="divider"></div>
    <div class="row bold">
        <div class="l">TOTAL</div>
        <div class="r">{{ rupiah((int) $order->total_amount) }}</div>
    </div>
    <div class="row">
        <div class="l">Bayar</div>
        <div class="r upper">{{ $order->payment_method ?? '—' }}</div>
    </div>

    <div class="footer center">{{ $settings->receipt_footer }}</div>
</body>
</html>
