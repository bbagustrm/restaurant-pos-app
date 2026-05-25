<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class RestaurantSettings extends Settings
{
    public string $restaurant_name;

    public string $restaurant_address;

    public string $restaurant_phone;

    public float $tax_percentage;

    public string $currency_symbol;

    public string $receipt_footer;

    public bool $auto_print_receipt;

    public static function group(): string
    {
        return 'restaurant';
    }
}
