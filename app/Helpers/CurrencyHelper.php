<?php

declare(strict_types=1);

if (! function_exists('rupiah')) {
    /**
     * Format an integer amount as Indonesian Rupiah currency.
     *
     * Example: rupiah(25000) === 'Rp 25.000'
     */
    function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
