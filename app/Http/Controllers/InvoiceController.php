<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Settings\RestaurantSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    /**
     * Stream the A4 PDF invoice for an order. Inline so the cashier's "open
     * in new tab" flow displays it directly, with a download fallback via
     * ?download=1.
     */
    public function download(Request $request, Order $order, RestaurantSettings $settings): Response
    {
        Gate::authorize('view', $order);

        $order->load(['orderItems.product', 'table', 'cashier']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'order' => $order,
            'settings' => $settings,
        ])->setPaper('a4');

        $filename = "invoice-{$order->order_number}.pdf";

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /**
     * Stream a thermal 80mm receipt for an order (Issue #20).
     */
    public function receipt(Request $request, Order $order, RestaurantSettings $settings): Response
    {
        Gate::authorize('view', $order);

        $order->load(['orderItems.product', 'table', 'cashier']);

        // 80mm × dynamic height; DomPDF accepts custom paper sizes in points (1mm ≈ 2.83 points).
        $pdf = Pdf::loadView('pdf.receipt', [
            'order' => $order,
            'settings' => $settings,
        ])->setPaper([0, 0, 226.77, 800], 'portrait');

        $filename = "receipt-{$order->order_number}.pdf";

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
